<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ============================================
// VERIFICATION DIRECTE EN BASE DE DONNEES
// ============================================
$userId = (int)$_SESSION['user_id'];

// Compter les abonnements actifs en BDD directe
$premiumCount = 0;
db_safe_exec(function(PDO $pdo) use ($userId, &$premiumCount) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM abonnement_pro 
        WHERE id_pro = ? 
        AND statut = "actif" 
        AND (date_fin IS NULL OR date_fin >= CURDATE())
    ');
    $stmt->execute([$userId]);
    $premiumCount = (int)$stmt->fetchColumn();
    return true;
}, false);

// Récupérer l'abonnement actuel via API
$abonnement = callAPI('GET', '/me/abonnement', $_SESSION['token'])['data'] ?? [];

// Le statut premium est vrai si BDD directe OU API
$isPremium = ($premiumCount > 0) || (($abonnement['formule'] ?? 'gratuit') !== 'gratuit' && ($abonnement['statut'] ?? '') === 'actif');

// Si BDD a trouve un abonnement mais pas l'API, on force les infos
if ($premiumCount > 0 && ($abonnement['formule'] ?? '') === 'gratuit') {
    db_safe_exec(function(PDO $pdo) use ($userId, &$abonnement) {
        $stmt = $pdo->prepare('
            SELECT formule, date_debut, date_fin, prix 
            FROM abonnement_pro 
            WHERE id_pro = ? AND statut = "actif"
            ORDER BY id_abonnement DESC LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $abonnement['formule'] = $row['formule'];
            $abonnement['date_debut'] = $row['date_debut'];
            $abonnement['date_fin'] = $row['date_fin'];
            $abonnement['prix'] = $row['prix'];
            $abonnement['statut'] = 'actif';
        }
        return true;
    }, false);
}

$dateFin = $abonnement['date_fin'] ?? null;

// ============================================
// STATISTIQUES DES PAIEMENTS (Version BDD directe)
// ============================================

// 🔥 Récupération directe en BDD au lieu de l'API
$paiements = [];
db_safe_exec(function(PDO $pdo) use (&$paiements, $userId) {
    $stmt = $pdo->prepare("
        SELECT id_paiement, provider, payment_ref, montant, devise, statut, 
               paid_at, created_at, id_inscription, user_id, metadata
        FROM paiement 
        WHERE user_id = ?
        ORDER BY id_paiement DESC
    ");
    $stmt->execute([$userId]);
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return true;
}, false);

// Statistiques globales
$totalPaid = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'paid'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$totalPending = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'pending'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$totalFailed = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'failed'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$countPaid = count(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'paid'));

// Statistiques d'utilisation des annonces
$annonces = api_get_my_annonces()['data'] ?? [];
$statsAnnonces = [
    'total' => count($annonces),
    'validees' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'validee')),
    'en_attente' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'en_attente')),
];

// Statistiques par mois pour le graphique
$monthlyStats = [];
db_safe_exec(function(PDO $pdo) use (&$monthlyStats, $userId) {
    $stmt = $pdo->prepare('
        SELECT 
            DATE_FORMAT(paid_at, "%Y-%m") as month,
            SUM(montant) as total,
            COUNT(*) as count
        FROM paiement 
        WHERE user_id = ? AND statut = "paid" AND paid_at IS NOT NULL
        GROUP BY DATE_FORMAT(paid_at, "%Y-%m")
        ORDER BY month DESC
        LIMIT 6
    ');
    $stmt->execute([$userId]);
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}, false);

$annonceLimit = $isPremium ? 999 : 5;
$pourcentageAnnonces = min(100, round(($statsAnnonces['total'] / $annonceLimit) * 100));

// Filtres pour l'historique
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$periodFilter = trim((string)($_GET['period'] ?? 'all'));

$filteredPaiements = $paiements;
if ($statusFilter !== 'all') {
    $filteredPaiements = array_values(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === $statusFilter));
}
if ($periodFilter !== 'all') {
    $now = time();
    $filteredPaiements = array_values(array_filter($filteredPaiements, function($p) use ($periodFilter, $now) {
        $date = strtotime($p['paid_at'] ?? $p['created_at'] ?? '');
        if ($periodFilter === 'month') return $date > strtotime('-30 days', $now);
        if ($periodFilter === 'quarter') return $date > strtotime('-90 days', $now);
        if ($periodFilter === 'year') return $date > strtotime('-365 days', $now);
        return true;
    }));
}
$totalFiltered = array_reduce($filteredPaiements, fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);

// ============================================
// TRAITEMENT SOUSCRIPTION
// ============================================
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

// ============================================
// TRAITEMENT RESILIATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
    $res = callAPI('POST', '/me/abonnement/cancel', $_SESSION['token']);
    
    if (($res['status'] ?? 0) === 200) {
        $_SESSION['flash_message'] = '✅ Abonnement resilie. Vous resterez premium jusqu\'a la fin de la periode en cours.';
        $_SESSION['flash_type'] = 'success';
        notif_create($userId, 'abonnement', 'Abonnement resilie', 
            'Votre abonnement Premium a ete resilie. Il sera actif jusqu\'au ' . formatDateFr($dateFin));
    } else {
        $_SESSION['flash_message'] = '❌ Impossible de resilier. Contactez le support.';
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: pro_abonnement.php');
    exit;
}

// Onglet actif
$activeTab = $_GET['tab'] ?? 'abonnement';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement & Facturation - UpcycleConnect Pro</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        /* Onglets */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
        }
        .tab-btn {
            padding: 10px 24px;
            border-radius: 30px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: #4caf50;
            color: white;
        }
        .tab-btn:hover:not(.active) {
            background: #e0e0e0;
        }
        
        /* Cartes statistiques */
        .stats-billing {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2e7d32;
        }
        .stat-label {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }
        .stat-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        /* Section abonnement */
        .subscription-card {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .subscription-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
        }
        
        /* Offres */
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
            transition: transform 0.2s;
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
        }
        .pricing-card-header {
            padding: 32px 24px;
            text-align: center;
            color: white;
        }
        .pricing-card-header.free { background: linear-gradient(135deg, #6c757d, #495057); }
        .pricing-card-header.premium-monthly { background: linear-gradient(135deg, #2e7d32, #4caf50); }
        .pricing-card-header.premium-yearly { background: linear-gradient(135deg, #1b5e20, #388e3c); }
        .pricing-price {
            font-size: 48px;
            font-weight: 700;
        }
        .pricing-features {
            padding: 24px;
        }
        .pricing-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
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
        }
        .pricing-features li.disabled::before {
            content: "✗";
            color: #dc2626;
        }
        .btn-subscribe {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            background: #4caf50;
            color: white;
        }
        .btn-subscribe:hover {
            background: #2e7d32;
        }
        .btn-subscribe-free {
            background: #e0e0e0;
            color: #666;
            cursor: not-allowed;
        }
        .btn-cancel {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
        }
        
        /* Tableau historique */
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .table-payments {
            width: 100%;
            border-collapse: collapse;
        }
        .table-payments th, .table-payments td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .table-payments th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-paid {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
        }
        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
        }
        .status-failed {
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
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
        }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .stats-billing {
                grid-template-columns: repeat(2, 1fr);
            }
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
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">

    <!-- Message flash -->
    <?php if ($flash !== ''): ?>
        <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>">
            <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <!-- Onglets -->
    <div class="tabs">
        <a href="?tab=abonnement" class="tab-btn <?= $activeTab === 'abonnement' ? 'active' : '' ?>">💰 Mon abonnement</a>
        <a href="?tab=historique" class="tab-btn <?= $activeTab === 'historique' ? 'active' : '' ?>">📋 Historique des paiements</a>
    </div>

    <!-- ============================================ -->
    <!-- ONGLET 1 : ABONNEMENT -->
    <!-- ============================================ -->
    <?php if ($activeTab === 'abonnement'): ?>
        
        <?php if ($isPremium): ?>
            <!-- Abonnement actuel -->
            <div class="subscription-card">
                <div>
                    <h3 style="margin: 0 0 8px 0;">⭐ Votre abonnement Premium</h3>
                    <p style="margin: 0; opacity: 0.9;">
                        Formule: <strong><?= e($abonnement['formule'] ?? 'Premium Mensuel') ?></strong><br>
                        Actif depuis le <?= e(formatDateFr($abonnement['date_debut'] ?? '')) ?>
                        <?php if ($dateFin): ?> · Valable jusqu'au <?= e(formatDateFr($dateFin)) ?><?php endif; ?>
                    </p>
                </div>
                <div class="subscription-badge">✅ Actif</div>
            </div>

            <!-- Statistiques d'utilisation -->
            <div class="pro-card" style="margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px 0;">📊 Votre utilisation</h3>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Annonces publiées</span>
                        <span><strong><?= $statsAnnonces['total'] ?></strong> / <?= $annonceLimit ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 20px;">
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #2e7d32;"><?= $statsAnnonces['validees'] ?></div>
                            <div class="muted">Annonces validées</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: #ef6c00;"><?= $statsAnnonces['en_attente'] ?></div>
                            <div class="muted">En attente</div>
                        </div>
                    </div>
                </div>
                <form method="POST" style="margin-top: 20px; text-align: center;">
                    <input type="hidden" name="cancel_subscription" value="1">
                    <button type="submit" class="btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir résilier votre abonnement ?')">
                        ❌ Résilier mon abonnement
                    </button>
                </form>
            </div>

            <!-- Avantages Premium -->
            <div class="feature-highlight" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); border-radius: 16px; padding: 24px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">🏆</div>
                <h3 style="margin: 0 0 16px 0;">Vous profitez de tous les avantages Premium</h3>
                <p>Statistiques avancées, mise en avant prioritaire, accès anticipé aux annonces et support prioritaire.</p>
            </div>

        <?php else: ?>
            <!-- Cartes statistiques gratuites -->
            <div class="stats-billing">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-value"><?= $statsAnnonces['total'] ?> / 5</div>
                    <div class="stat-label">Annonces utilisées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?= e(formatPriceEur($totalPaid)) ?></div>
                    <div class="stat-label">Total dépensé</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?= $countPaid ?></div>
                    <div class="stat-label">Paiements réussis</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-value">Gratuit</div>
                    <div class="stat-label">Formule actuelle</div>
                </div>
            </div>

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
                        <button class="btn-subscribe-free" disabled>Plan actuel</button>
                    </div>
                </div>

                <!-- Offre Premium Mensuel -->
                <div class="pricing-card popular">
                    <div class="popular-badge">⭐ RECOMMANDÉ</div>
                    <div class="pricing-card-header premium-monthly">
                        <h2>⭐ Premium Mensuel</h2>
                        <div class="pricing-price">29€<small>/mois</small></div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Annonces illimitées</li>
                            <li>Statistiques avancées</li>
                            <li>Mise en avant prioritaire</li>
                            <li>Accès anticipé aux annonces</li>
                            <li>Support prioritaire 24/7</li>
                            <li>Badge "Premium" sur votre profil</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="subscribe_premium" value="1">
                            <input type="hidden" name="formule" value="monthly">
                            <button type="submit" class="btn-subscribe">🔥 S'abonner (29€/mois)</button>
                        </form>
                    </div>
                </div>

                <!-- Offre Premium Annuel -->
                <div class="pricing-card">
                    <div class="pricing-card-header premium-yearly">
                        <h2>⭐ Premium Annuel</h2>
                        <div class="pricing-price">299€<small>/an</small></div>
                        <div class="pricing-savings" style="background: rgba(255,255,255,0.2); display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Économisez 49€</div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Tous les avantages Premium</li>
                            <li>2 mois offerts</li>
                            <li>Facture annuelle unique</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="subscribe_premium" value="1">
                            <input type="hidden" name="formule" value="yearly">
                            <button type="submit" class="btn-subscribe">🔥 S'abonner (299€/an)</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Comparaison -->
            <div class="pro-card" style="margin-top: 0;">
                <h3 style="margin: 0 0 16px 0;">📋 Comparaison détaillée</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Fonctionnalité</th><th style="text-align: center;">Gratuit</th><th style="text-align: center;">Premium</th></tr></thead>
                        <tbody>
                            <tr><td>Annonces</td><td style="text-align: center;">5 max</td><td style="text-align: center; color: #4caf50;">Illimité</td></tr>
                            <tr><td>Statistiques avancées</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Mise en avant</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅</td></tr>
                            <tr><td>Support prioritaire</td><td style="text-align: center;">❌</td><td style="text-align: center; color: #4caf50;">✅ 24/7</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FAQ -->
            <div class="pro-card" style="margin-top: 0;">
                <h3 style="margin: 0 0 16px 0;">❓ Questions fréquentes</h3>
                <div style="display: grid; gap: 16px;">
                    <div><strong>Comment résilier mon abonnement ?</strong><p class="muted">Vous pouvez résilier à tout moment depuis cette page.</p></div>
                    <div><strong>Puis-je passer du mensuel à l'annuel ?</strong><p class="muted">Oui, contactez notre support.</p></div>
                    <div><strong>Comment sont traités les paiements ?</strong><p class="muted">Paiements sécurisés par Stripe.</p></div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ============================================ -->
    <!-- ONGLET 2 : HISTORIQUE DES PAIEMENTS -->
    <!-- ============================================ -->
    <?php if ($activeTab === 'historique'): ?>

        <!-- Statistiques -->
        <div class="stats-billing">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?= e(formatPriceEur($totalPaid)) ?></div>
                <div class="stat-label">Total dépensé</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $countPaid ?></div>
                <div class="stat-label">Paiements réussis</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?= e(formatPriceEur($totalPending)) ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?= e(formatPriceEur($totalFailed)) ?></div>
                <div class="stat-label">Échoués</div>
            </div>
        </div>

        <!-- Graphique des dépenses mensuelles -->
        <?php if (!empty($monthlyStats)): ?>
        <div class="chart-container">
            <h3 style="margin: 0 0 16px 0;">📊 Dépenses mensuelles</h3>
            <canvas id="expenseChart" height="80" style="max-height: 200px;"></canvas>
        </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="tab" value="historique">
                <select class="input" name="status" style="width: auto;">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Payés</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Échoués</option>
                </select>
                <select class="input" name="period" style="width: auto;">
                    <option value="all" <?= $periodFilter === 'all' ? 'selected' : '' ?>>Toute la période</option>
                    <option value="month" <?= $periodFilter === 'month' ? 'selected' : '' ?>>30 derniers jours</option>
                    <option value="quarter" <?= $periodFilter === 'quarter' ? 'selected' : '' ?>>90 derniers jours</option>
                    <option value="year" <?= $periodFilter === 'year' ? 'selected' : '' ?>>365 derniers jours</option>
                </select>
                <button class="btn-outline" type="submit">Filtrer</button>
                <a class="btn-outline" href="?tab=historique">Reset</a>
            </form>
        </div>

        <!-- Tableau des paiements -->
        <div class="pro-card" style="padding: 0; overflow: hidden;">
            <?php if (empty($filteredPaiements)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 64px; margin-bottom: 16px;">📭</div>
                    <p>Aucun paiement trouvé</p>
                    <p class="muted" style="font-size: 13px;">Souscrivez à un abonnement pour voir vos paiements apparaître</p>
                    <a href="?tab=abonnement" class="btn-primary" style="margin-top: 16px; display: inline-block;">Découvrir les offres</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-payments">
                        <thead>
                            <tr><th>Référence</th><th>Date</th><th>Montant</th><th>Statut</th><th>Facture</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredPaiements as $p): ?>
                            <?php
                            $statut = $p['statut'] ?? 'pending';
                            $statusClass = match($statut) {
                                'paid' => 'status-paid',
                                'pending' => 'status-pending',
                                'failed' => 'status-failed',
                                default => 'status-pending'
                            };
                            $statusLabel = match($statut) {
                                'paid' => '✅ Payé',
                                'pending' => '⏳ En attente',
                                'failed' => '❌ Échoué',
                                default => $statut
                            };
                            ?>
                            <tr>
                                <td><code><?= e(substr($p['payment_ref'] ?? '', 0, 20)) ?>...</code></td>
                                <td><?= e(formatDateFr($p['paid_at'] ?? $p['created_at'] ?? '')) ?></td>
                                <td><strong><?= e(formatPriceEur($p['montant'] ?? 0)) ?></strong></td>
                                <td><span class="<?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                <td>
                                    <?php if ($statut === 'paid'): ?>
                                        <a href="document_download.php?type=paiement&id=<?= (int)($p['id_paiement'] ?? 0) ?>" class="btn-outline" style="padding: 4px 12px;">📄 PDF</a>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding: 16px; text-align: right; border-top: 1px solid #e5e7eb;">
                    <p class="muted">Total : <strong><?= e(formatPriceEur($totalFiltered)) ?></strong> pour <?= count($filteredPaiements) ?> transaction(s)</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</main>

<script>
<?php if (!empty($monthlyStats) && $activeTab === 'historique'): ?>
const ctx = document.getElementById('expenseChart').getContext('2d');
const monthlyData = <?= json_encode(array_reverse($monthlyStats)) ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(item => item.month),
        datasets: [{
            label: 'Dépenses (€)',
            data: monthlyData.map(item => parseFloat(item.total)),
            backgroundColor: 'rgba(76, 175, 80, 0.6)',
            borderColor: '#4caf50',
            borderWidth: 1,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return value + ' €'; } } } }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>