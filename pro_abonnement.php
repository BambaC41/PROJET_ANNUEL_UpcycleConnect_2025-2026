<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Récupérer l'abonnement actuel
$abonnement = callAPI('GET', '/me/abonnement', $_SESSION['token'])['data'] ?? [];
$isPremium = ($abonnement['formule'] ?? 'gratuit') === 'premium' && ($abonnement['statut'] ?? '') === 'actif';
$dateFin = $abonnement['date_fin'] ?? null;

// Récupérer l'historique des paiements
$paiements = api_get_my_paiements()['data'] ?? [];
$paiementsPremium = array_filter($paiements, fn($p) => str_contains($p['payment_ref'] ?? '', 'PREMIUM'));

// Statistiques d'utilisation
$annonces = api_get_my_annonces()['data'] ?? [];
$statsAnnonces = [
    'total' => count($annonces),
    'validees' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'validee')),
    'en_attente' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'en_attente')),
];

// Traitement souscription (redirection Stripe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_premium'])) {
    $formule = $_POST['formule'] ?? 'monthly';
    $amount = $formule === 'monthly' ? 2999 : 29900;
    $itemName = $formule === 'monthly' ? 'Abonnement Premium Mensuel' : 'Abonnement Premium Annuel';
    
    $_SESSION['pending_subscription'] = [
        'formule' => $formule,
        'amount' => $amount,
        'item_name' => $itemName
    ];
    
    header("Location: paiement_stripe.php?amount=$amount&item=" . urlencode($itemName) . "&type=abonnement&formule=$formule");
    exit;
}

// Traitement résiliation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
    $res = callAPI('POST', '/me/abonnement/cancel', $_SESSION['token']);
    
    if (($res['status'] ?? 0) === 200) {
        $_SESSION['flash_message'] = '✅ Abonnement résilié. Vous resterez premium jusqu\'à la fin de la période en cours.';
        $_SESSION['flash_type'] = 'success';
        notif_create((int)$_SESSION['user_id'], 'abonnement', 'Abonnement résilié', 
            'Votre abonnement Premium a été résilié. Il sera actif jusqu\'au ' . formatDateFr($dateFin));
    } else {
        $_SESSION['flash_message'] = '❌ Impossible de résilier. Contactez le support.';
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: pro_abonnement.php');
    exit;
}

// Confirmation après paiement Stripe
if (isset($_GET['success']) && $_GET['success'] === '1') {
    if (isset($_SESSION['pending_subscription'])) {
        $sub = $_SESSION['pending_subscription'];
        
        // 🔥 METTRE À JOUR L'ABONNEMENT EN BASE DE DONNÉES 🔥
        db_safe_exec(function(PDO $pdo) use ($sub) {
            $formule = $sub['formule'] === 'monthly' ? 'premium_mensuel' : 'premium_annuel';
            $prix = $sub['amount'] / 100;
            $dateDebut = date('Y-m-d');
            $dateFin = $sub['formule'] === 'monthly' 
                ? date('Y-m-d', strtotime('+1 month')) 
                : date('Y-m-d', strtotime('+1 year'));
            
            $stmt = $pdo->prepare('
                INSERT INTO abonnement_pro (id_pro, formule, statut, date_debut, date_fin, prix, created_at)
                VALUES (?, ?, "actif", ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    formule = VALUES(formule),
                    statut = "actif",
                    date_debut = VALUES(date_debut),
                    date_fin = VALUES(date_fin),
                    prix = VALUES(prix)
            ');
            $stmt->execute([(int)$_SESSION['user_id'], $formule, $dateDebut, $dateFin, $prix]);
            return true;
        }, false);
        
        $_SESSION['flash_message'] = '✅ Abonnement ' . $sub['item_name'] . ' activé avec succès !';
        $_SESSION['flash_type'] = 'success';
        
        notif_create((int)$_SESSION['user_id'], 'abonnement', 'Abonnement Premium activé', 
            'Votre abonnement Premium est maintenant actif. Profitez de tous les avantages !');
        
        unset($_SESSION['pending_subscription']);
    }
    header('Location: pro_abonnement.php');
    exit;
}

$annonceLimit = $isPremium ? 999 : 5;
$pourcentageAnnonces = min(100, round(($statsAnnonces['total'] / $annonceLimit) * 100));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement Premium - UpcycleConnect Pro</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .pricing-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .pricing-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .pricing-grid {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .pricing-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            flex: 1;
            min-width: 280px;
            max-width: 380px;
        }
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        .pricing-card.popular {
            border: 2px solid #ffd700;
            transform: scale(1.02);
            position: relative;
            overflow: visible;
        }
        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #ffd700;
            color: #2e7d32;
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .pricing-card-header {
            padding: 32px 24px;
            text-align: center;
            color: white;
        }
        .pricing-card-header.free { background: linear-gradient(135deg, #6c757d, #495057); }
        .pricing-card-header.premium-monthly { background: linear-gradient(135deg, #2e7d32, #4caf50); }
        .pricing-card-header.premium-yearly { background: linear-gradient(135deg, #1b5e20, #388e3c); }
        .pricing-card-header h2 {
            margin: 0 0 16px 0;
            font-size: 24px;
        }
        .pricing-price {
            font-size: 48px;
            font-weight: 700;
        }
        .pricing-price small {
            font-size: 14px;
            font-weight: normal;
        }
        .pricing-price-period {
            font-size: 14px;
            opacity: 0.8;
        }
        .pricing-savings {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 12px;
        }
        .pricing-features {
            padding: 24px;
        }
        .pricing-features ul {
            list-style: none;
            padding: 0;
            margin: 0 0 24px 0;
        }
        .pricing-features li {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pricing-features li::before {
            content: "✓";
            color: #4caf50;
            font-weight: bold;
            font-size: 18px;
        }
        .pricing-features li.disabled::before {
            content: "✗";
            color: #dc2626;
        }
        .pricing-features li.disabled {
            opacity: 0.5;
        }
        .btn-subscribe {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-subscribe-free {
            background: #e0e0e0;
            color: #666;
            cursor: not-allowed;
        }
        .btn-subscribe-premium {
            background: #4caf50;
            color: white;
        }
        .btn-subscribe-premium:hover {
            background: #2e7d32;
            transform: scale(1.02);
        }
        .btn-cancel {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-cancel:hover {
            background: #b91c1c;
        }
        .current-plan {
            background: #e8f5e9;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .progress-bar-container {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            margin: 16px 0;
        }
        .progress-bar {
            background: #4caf50;
            border-radius: 10px;
            height: 8px;
            width: <?= $pourcentageAnnonces ?>%;
            transition: width 0.3s;
        }
        .usage-stats {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-top: 24px;
        }
        .feature-highlight {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
            text-align: center;
        }
        .create-annonce-link {
            display: inline-block;
            margin-top: 16px;
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
        }
        .create-annonce-link:hover {
            background: #2e7d32;
        }
        @media (max-width: 768px) {
            .pricing-grid {
                flex-direction: column;
                align-items: center;
            }
            .pricing-card {
                max-width: 100%;
            }
            .pricing-card.popular {
                transform: scale(1);
            }
            .current-plan {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <div class="pricing-header">
            <h1>⭐ Abonnement Premium</h1>
            <p>Débloquez toutes les fonctionnalités pour développer votre activité</p>
        </div>

        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>">
                <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <?php if ($isPremium): ?>
            <!-- Plan actuel -->
            <div class="current-plan">
                <div>
                    <h2 style="margin: 0 0 8px 0;">✅ Vous êtes abonné Premium</h2>
                    <p class="muted" style="margin: 0;">
                        Formule: <strong><?= e($abonnement['formule'] ?? 'Premium Mensuel') ?></strong><br>
                        Actif depuis le: <?= e(formatDateFr($abonnement['date_debut'] ?? '')) ?><br>
                        <?php if ($dateFin): ?>
                            Valable jusqu'au: <strong><?= e(formatDateFr($dateFin)) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <form method="POST">
                    <input type="hidden" name="cancel_subscription" value="1">
                    <button type="submit" class="btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir résilier votre abonnement ?')">
                        ❌ Résilier mon abonnement
                    </button>
                </form>
            </div>

            <!-- Statistiques d'utilisation -->
            <div class="usage-stats">
                <h3 style="margin: 0 0 16px 0;">📊 Statistiques d'utilisation</h3>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Annonces publiées</span>
                        <span><strong><?= $statsAnnonces['total'] ?></strong> / <?= $annonceLimit ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 20px; text-align: center;">
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #2e7d32;"><?= $statsAnnonces['validees'] ?></div>
                            <div class="muted">Annonces validées</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #ef6c00;"><?= $statsAnnonces['en_attente'] ?></div>
                            <div class="muted">En attente</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #2196f3;"><?= count($paiementsPremium) ?></div>
                            <div class="muted">Paiements effectués</div>
                        </div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="particulier_annonces.php" class="create-annonce-link">➕ Créer une annonce</a>
                </div>
            </div>

            <!-- Avantages Premium -->
            <div class="feature-highlight">
                <div style="font-size: 48px; margin-bottom: 16px;">🏆</div>
                <h3 style="margin: 0 0 16px 0;">Vous profitez de tous les avantages Premium</h3>
                <p>Statistiques avancées, mise en avant prioritaire, accès anticipé aux annonces et support prioritaire.</p>
            </div>

        <?php else: ?>
            <!-- Offres d'abonnement -->
            <div class="pricing-grid">
                <!-- Offre Gratuite -->
                <div class="pricing-card">
                    <div class="pricing-card-header free">
                        <h2>📋 Gratuit</h2>
                        <div class="pricing-price">0€<small>/mois</small></div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>5 annonces maximum</li>
                            <li>Statistiques basiques</li>
                            <li>Accès catalogue formations</li>
                            <li>Support standard</li>
                            <li class="disabled">Mise en avant prioritaire</li>
                            <li class="disabled">Accès anticipé aux annonces</li>
                            <li class="disabled">Support prioritaire 24/7</li>
                        </ul>
                        <button class="btn-subscribe btn-subscribe-free" disabled>
                            Plan actuel
                        </button>
                    </div>
                </div>

                <!-- Offre Premium Mensuel -->
                <div class="pricing-card popular">
                    <div class="popular-badge">⭐ RECOMMANDÉ</div>
                    <div class="pricing-card-header premium-monthly">
                        <h2>⭐ Premium Mensuel</h2>
                        <div class="pricing-price">29€<small>/mois</small></div>
                        <div class="pricing-price-period">Sans engagement</div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Annonces illimitées</li>
                            <li>Statistiques avancées</li>
                            <li>Mise en avant prioritaire</li>
                            <li>Accès anticipé aux annonces</li>
                            <li>Support prioritaire 24/7</li>
                            <li>Badge "Premium" sur votre profil</li>
                            <li>Accès aux rapports d'activité</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="subscribe_premium" value="1">
                            <input type="hidden" name="formule" value="monthly">
                            <button type="submit" class="btn-subscribe btn-subscribe-premium">
                                🔥 S'abonner (29€/mois)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Offre Premium Annuel -->
                <div class="pricing-card">
                    <div class="pricing-card-header premium-yearly">
                        <h2>⭐ Premium Annuel</h2>
                        <div class="pricing-price">299€<small>/an</small></div>
                        <div class="pricing-savings">Économisez 49€</div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Tous les avantages Premium</li>
                            <li>2 mois offerts</li>
                            <li>Facture annuelle unique</li>
                            <li>Renouvellement automatique</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="subscribe_premium" value="1">
                            <input type="hidden" name="formule" value="yearly">
                            <button type="submit" class="btn-subscribe btn-subscribe-premium">
                                🔥 S'abonner (299€/an)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lien pour créer une annonce -->
            <div style="text-align: center; margin-bottom: 24px;">
                <a href="particulier_annonces.php" class="create-annonce-link">➕ Créer une annonce</a>
            </div>

            <!-- Comparaison détaillée -->
            <div class="pro-card" style="margin-top: 0;">
                <h3 style="margin: 0 0 16px 0;">📋 Comparaison détaillée</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Fonctionnalité</th><th style="text-align: center;">Gratuit</th><th style="text-align: center;">Premium</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Annonces</td><td style="text-align: center;">5 max</td><td style="text-align: center; color: #4caf50;">Illimité</td></tr>
                            <tr><td>Statistiques avancées</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Mise en avant</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Accès anticipé annonces</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Support prioritaire</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅ 24/7</td></tr>
                            <tr><td>Badge professionnel</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Rapports d'activité</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FAQ -->
            <div class="pro-card" style="margin-top: 0;">
                <h3 style="margin: 0 0 16px 0;">❓ Questions fréquentes</h3>
                <div style="display: grid; gap: 16px;">
                    <div>
                        <strong>Comment résilier mon abonnement ?</strong>
                        <p class="muted" style="margin: 4px 0 0 0;">Vous pouvez résilier à tout moment depuis cette page. Votre abonnement restera actif jusqu'à la fin de la période en cours.</p>
                    </div>
                    <div>
                        <strong>Puis-je passer du mensuel à l'annuel ?</strong>
                        <p class="muted" style="margin: 4px 0 0 0;">Oui, contactez notre support pour effectuer le changement.</p>
                    </div>
                    <div>
                        <strong>Comment sont traités les paiements ?</strong>
                        <p class="muted" style="margin: 4px 0 0 0;">Les paiements sont sécurisés par Stripe. Vos informations bancaires ne sont jamais stockées sur nos serveurs.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>