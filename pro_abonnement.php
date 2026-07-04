<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$userId = (int)$_SESSION['user_id'];

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

$abonnement = callAPI('GET', '/me/abonnement', $_SESSION['token'])['data'] ?? [];

$isPremium = ($premiumCount > 0) || (($abonnement['formule'] ?? 'gratuit') !== 'gratuit' && ($abonnement['statut'] ?? '') === 'actif');

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

$totalPaid = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'paid'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$totalPending = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'pending'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$totalFailed = array_reduce(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'failed'), 
    fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
$countPaid = count(array_filter($paiements, fn($p) => ($p['statut'] ?? '') === 'paid'));

$annonces = api_get_my_annonces()['data'] ?? [];
$statsAnnonces = [
    'total' => count($annonces),
    'validees' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'validee')),
    'en_attente' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'en_attente')),
];

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
    <link rel="stylesheet" href="styles/admin_global.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">

    <?php if ($flash !== ''): ?>
        <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>">
            <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=abonnement" class="tab-btn <?= $activeTab === 'abonnement' ? 'active' : '' ?>">💰 Mon abonnement</a>
        <a href="?tab=historique" class="tab-btn <?= $activeTab === 'historique' ? 'active' : '' ?>">📋 Historique des paiements</a>
    </div>

    <?php if ($activeTab === 'abonnement'): ?>
        
        <?php if ($isPremium): ?>
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

            <div class="pro-card" style="margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px 0;">📊 Votre utilisation</h3>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Annonces publiées</span>
                        <span><strong><?= $statsAnnonces['total'] ?></strong> / <?= $annonceLimit ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?= $pourcentageAnnonces ?>%;"></div>
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

            <div class="feature-highlight" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); border-radius: 16px; padding: 24px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">🏆</div>
                <h3 style="margin: 0 0 16px 0;">Vous profitez de tous les avantages Premium</h3>
                <p>Statistiques avancées, mise en avant prioritaire, accès anticipé aux annonces et support prioritaire.</p>
            </div>

        <?php else: ?>
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

            <div class="pricing-grid">
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

    <?php if ($activeTab === 'historique'): ?>

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

        <?php if (!empty($monthlyStats)): ?>
        <div class="chart-container">
            <h3 style="margin: 0 0 16px 0;">📊 Dépenses mensuelles</h3>
            <canvas id="expenseChart" height="80" style="max-height: 200px;"></canvas>
        </div>
        <?php endif; ?>

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