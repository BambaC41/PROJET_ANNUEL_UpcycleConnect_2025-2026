<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/documents.php';
require_once 'includes/notifications.php';

$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
$annonces = api_get_my_annonces()['data'] ?? [];
$annoncesPubliques = api_get_annonces()['data'] ?? [];
$demandes = api_get_my_demandes_depot()['data'] ?? [];
$paiements = api_get_my_paiements()['data'] ?? [];

// Statistiques
$stats = [
    'annonces' => count($annonces),
    'annonces_validees' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'validee')),
    'annonces_attente' => count(array_filter($annonces, fn($a) => ($a['statut'] ?? '') === 'en_attente')),
    'annonces_publiques' => count($annoncesPubliques),
    'demandes' => count($demandes),
    'paiements' => count($paiements),
    'total_revenue' => array_reduce($paiements, fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0),
];

// Abonnement
$abonnement = callAPI('GET', '/me/abonnement', $_SESSION['token'])['data'] ?? [];
$isPremium = ($abonnement['formule'] ?? 'gratuit') === 'premium' && ($abonnement['statut'] ?? '') === 'actif';

// Projets
$projectsCount = (int)db_safe_exec(function(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_upcycling WHERE id_pro = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}, 0);
$stats['projets'] = $projectsCount;

// Notifications
$notifUnread = notif_unread_count((int)($_SESSION['user_id'] ?? 0));

// Documents
$docs = document_list_for_user((int)($_SESSION['user_id'] ?? 0));
$stats['documents'] = count($docs);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Professionnels & Artisans - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <!-- Chart.js pour graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .welcome-banner h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
        }
        .premium-badge {
            background: #ffd700;
            color: #2e7d32;
            padding: 6px 16px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 14px;
        }
        .free-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
            display: block;
        }
        .action-card:hover {
            border-color: #4caf50;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .action-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .action-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 8px 0;
        }
        .action-desc {
            color: #666;
            font-size: 13px;
            margin: 0;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-table th, .recent-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .recent-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        .status-validee { background: #e8f5e9; color: #2e7d32; }
        .status-en_attente { background: #fff3e0; color: #ef6c00; }
        .status-rejetee { background: #fee2e2; color: #dc2626; }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .actions-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>

<main class="pro-shell page-shell">
    <!-- Bannière de bienvenue -->
    <div class="welcome-banner">
        <div>
            <h1>🏢 Bonjour, <?= e($me['pseudo'] ?? $me['prenom'] ?? 'Professionnel') ?> !</h1>
            <p>Bienvenue sur votre espace professionnel UpcycleConnect</p>
        </div>
        <div>
            <?php if ($isPremium): ?>
                <span class="premium-badge">⭐ Abonnement Premium actif</span>
            <?php else: ?>
                <span class="free-badge">📋 Compte Gratuit - <a href="pro_abonnement.php" style="color: white; text-decoration: underline;">Passer Premium</a></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques KPI -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $stats['annonces'] ?></div>
            <div class="stat-label">Mes annonces</div>
            <small class="muted"><?= $stats['annonces_validees'] ?> validées</small>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔄</div>
            <div class="stat-value"><?= $stats['projets'] ?></div>
            <div class="stat-label">Projets upcycling</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= e(formatPriceEur($stats['total_revenue'])) ?></div>
            <div class="stat-label">Chiffre d'affaires</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🗑️</div>
            <div class="stat-value"><?= $stats['demandes'] ?></div>
            <div class="stat-label">Récupérations conteneurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔔</div>
            <div class="stat-value"><?= $notifUnread ?></div>
            <div class="stat-label">Notifications non lues</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📄</div>
            <div class="stat-value"><?= $stats['documents'] ?></div>
            <div class="stat-label">Documents</div>
        </div>
    </div>

    <!-- Graphique d'activité -->
    <div class="chart-container">
        <h3 style="margin: 0 0 16px 0;">📊 Activité du mois</h3>
        <canvas id="activityChart" height="80" style="max-height: 200px;"></canvas>
    </div>

    <!-- Actions rapides -->
    <div class="actions-grid">
        <a href="pro_annonces.php?mode=all" class="action-card">
            <div class="action-icon">🏪</div>
            <div class="action-title">Marketplace</div>
            <div class="action-desc">Consulter et acheter des annonces</div>
        </a>
        <a href="pro_annonces.php" class="action-card">
            <div class="action-icon">✏️</div>
            <div class="action-title">Créer une annonce</div>
            <div class="action-desc">Publier une nouvelle annonce</div>
        </a>
        <a href="pro_projects.php" class="action-card">
            <div class="action-icon">🔄</div>
            <div class="action-title">Projets upcycling</div>
            <div class="action-desc">Gérer vos projets de transformation</div>
        </a>
        <a href="pro_conteneurs.php" class="action-card">
            <div class="action-icon">🗑️</div>
            <div class="action-title">Récupération conteneurs</div>
            <div class="action-desc">Scanner et récupérer les objets</div>
        </a>
        <a href="pro_billing.php" class="action-card">
            <div class="action-icon">💰</div>
            <div class="action-title">Facturation</div>
            <div class="action-desc">Gérer vos abonnements et factures</div>
        </a>
        <a href="pro_abonnement.php" class="action-card">
            <div class="action-icon">⭐</div>
            <div class="action-title">Abonnement Premium</div>
            <div class="action-desc">Débloquez toutes les fonctionnalités</div>
        </a>
    </div>

    <!-- Dernières annonces disponibles -->
    <div class="action-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0;">📦 Dernières annonces disponibles</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="recent-table">
                <thead>
                    <tr><th>Titre</th><th>Type</th><th>Prix</th><th>Statut</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($annoncesPubliques)): ?>
                    <tr><td colspan="5" style="text-align: center;">Aucune annonce disponible</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($annoncesPubliques, 0, 5) as $a): ?>
                        <tr>
                            <td><?= e($a['titre'] ?? '') ?></td>
                            <td><?= ($a['mode'] ?? '') === 'don' ? '🎁 Don' : '💰 Vente' ?></td>
                            <td><?= (($a['mode'] ?? '') === 'vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></td>
                            <td><span class="status-badge status-validee">✓ Disponible</span></td>
                            <td><a href="pro_annonces.php?mode=all" class="btn-outline" style="padding: 4px 12px;">Voir</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// Graphique d'activité avec données réelles (à alimenter depuis l'API)
const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [{
            label: 'Vues',
            data: [12, 19, 15, 17, 14, 10, 8],
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Inscriptions',
            data: [2, 3, 1, 4, 2, 1, 0],
            borderColor: '#2196f3',
            backgroundColor: 'rgba(33, 150, 243, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>