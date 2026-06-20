<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/notifications.php';

$users = api_get_users($_SESSION['token']);
$events = api_get_events($_SESSION['token']);
$pendingProsRes = api_get_pending_pros($_SESSION['token']);
$pendingPros = (($pendingProsRes['status'] ?? 0) === 200 && is_array($pendingProsRes['data'] ?? null)) ? $pendingProsRes['data'] : [];
$pendingAnnoncesRes = api_get_pending_annonces();
$pendingAnnonces = (($pendingAnnoncesRes['status'] ?? 0) === 200 && is_array($pendingAnnoncesRes['data'] ?? null)) ? $pendingAnnoncesRes['data'] : [];
$demandesRes = api_get_all_demandes_depot();
$demandes = (($demandesRes['status'] ?? 0) === 200 && is_array($demandesRes['data'] ?? null)) ? $demandesRes['data'] : [];

$stripeData = api_get_stripe_balance();
$totalRevenue = 0;
$transactionsCount = 0;

if (($stripeData['status'] ?? 0) === 200) {
    if (isset($stripeData['data']['data'])) {
        $stripeBalance = $stripeData['data']['data'];
        $totalRevenue = $stripeBalance['total_revenue'] ?? $stripeBalance['balance_pending'] ?? 0;
        $transactionsCount = $stripeBalance['transaction_count'] ?? 0;
    } elseif (isset($stripeData['data']['balance_pending'])) {
        $totalRevenue = $stripeData['data']['balance_pending'];
        $transactionsCount = $stripeData['data']['transaction_count'] ?? 0;
    }
}

if ($totalRevenue == 0) {
    $totalRevenue = (float)db_safe_exec(function (PDO $pdo) {
        $st = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM paiement WHERE statut = 'paid'");
        return (float)$st->fetchColumn();
    }, 0);
    $transactionsCount = (int)db_safe_exec(function (PDO $pdo) {
        $st = $pdo->query("SELECT COUNT(*) FROM paiement WHERE statut = 'paid'");
        return (int)$st->fetchColumn();
    }, 0);
}

$pendingConseils = (int)db_safe_exec(function (PDO $pdo) {
    $st = $pdo->query('SELECT COUNT(*) FROM conseil WHERE is_active = 0');
    return (int)$st->fetchColumn();
}, 0);

$userId = (int)($_SESSION['user_id'] ?? 0);

$recentNotifications = (array)db_safe_exec(function (PDO $pdo) use ($userId) {
    $st = $pdo->prepare('SELECT * FROM notification WHERE id_user = ? ORDER BY created_at DESC LIMIT 5');
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}, []);

$unreadCount = (int)db_safe_exec(function (PDO $pdo) use ($userId) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notification WHERE id_user = ? AND is_read = 0');
    $st->execute([$userId]);
    return $st->fetchColumn();
}, 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .admin-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 32px;
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
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e5e7eb;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 32px;
            margin-bottom: 12px;
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
        .stat-sub {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
            display: block;
            position: relative;
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
        .badge-pending {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #f44336;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .notif-dropdown {
            position: relative;
            display: inline-block;
        }
        .btn-notif {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-notif:hover {
            background: rgba(255,255,255,0.3);
        }
        .notif-badge {
            background: #f44336;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 12px;
        }
        .notif-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 320px;
            max-width: 400px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            margin-top: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .notif-dropdown-content.show {
            display: block;
            animation: dropdownFadeIn 0.2s ease;
        }
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .notif-dropdown-header {
            padding: 12px 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-dropdown-header a {
            font-size: 12px;
            color: #2e7d32;
            text-decoration: none;
        }
        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }
        .notif-item:hover {
            background: #f8f9fa;
        }
        .notif-item.unread {
            background: #fff8f0;
            border-left: 3px solid #ff9800;
        }
        .notif-title {
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 4px;
        }
        .notif-message {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        .notif-date {
            font-size: 10px;
            color: #999;
        }
        .notif-empty {
            padding: 30px;
            text-align: center;
            color: #999;
        }
        .notif-footer {
            padding: 10px 16px;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #e5e7eb;
        }
        .notif-footer a {
            color: #2e7d32;
            text-decoration: none;
            font-size: 13px;
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .actions-grid {
                grid-template-columns: 1fr;
            }
            .welcome-banner {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }
            .notif-dropdown-content {
                position: fixed;
                top: auto;
                left: 10px;
                right: 10px;
                width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>

<main class="admin-shell">
    <div class="welcome-banner">
        <div>
            <h1>🏠 Bonjour, Administrateur !</h1>
            <p>Bienvenue sur votre espace d'administration UpcycleConnect</p>
        </div>
        <div class="notif-dropdown">
            <button onclick="toggleNotifDropdown()" class="btn-notif">
                🔔 Dernières notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
            <div id="notifDropdown" class="notif-dropdown-content">
                <div class="notif-dropdown-header">
                    <span>📬 Notifications récentes</span>
                    <a href="notifications.php">Tout voir</a>
                </div>
                <div id="notifDropdownList">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="notif-empty">📭 Aucune notification</div>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $notif): ?>
                            <div class="notif-item <?= empty($notif['is_read']) ? 'unread' : '' ?>">
                                <div class="notif-title"><?= e($notif['titre'] ?? 'Notification') ?></div>
                                <div class="notif-message"><?= e(mb_substr($notif['contenu'] ?? '', 0, 80)) ?></div>
                                <div class="notif-date">📅 <?= formatDateFr($notif['created_at'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notif-footer">
                    <a href="notifications.php">📋 Voir toutes les notifications</a>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= e(is_array($users) ? count($users) : 0) ?></div>
            <div class="stat-label">Utilisateurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= e(count($pendingAnnonces)) ?></div>
            <div class="stat-label">Annonces à valider</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💡</div>
            <div class="stat-value"><?= e($pendingConseils) ?></div>
            <div class="stat-label">Conseils en attente</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?= e(is_array($events) ? count($events) : 0) ?></div>
            <div class="stat-label">Événements</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🗳️</div>
            <div class="stat-value"><?= e(count($demandes)) ?></div>
            <div class="stat-label">Dépôts conteneur</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= number_format($totalRevenue, 2, ',', ' ') ?> €</div>
            <div class="stat-label">Chiffre d'affaires </div>
            <div class="stat-sub"><?= $transactionsCount ?> transactions</div>
        </div>
    </div>

    <div class="section-title">
        <span>🚀 Actions rapides</span>
    </div>
    <div class="actions-grid">
        <a class="action-card" href="admin_users.php">
            <div class="action-icon">👥</div>
            <div class="action-title">Utilisateurs</div>
            <div class="action-desc">Gérer les comptes et valider les PRO</div>
        </a>
        <a class="action-card" href="admin_annonces.php">
            <div class="action-icon">📦</div>
            <div class="action-title">Annonces</div>
            <div class="action-desc">Valider les annonces en attente</div>
            <?php if (count($pendingAnnonces) > 0): ?>
                <span class="badge-pending"><?= count($pendingAnnonces) ?></span>
            <?php endif; ?>
        </a>
        <a class="action-card" href="admin_conseils.php">
            <div class="action-icon">💡</div>
            <div class="action-title">Conseils & News</div>
            <div class="action-desc">Publier les conseils des salariés</div>
            <?php if ($pendingConseils > 0): ?>
                <span class="badge-pending"><?= $pendingConseils ?></span>
            <?php endif; ?>
        </a>
        <a class="action-card" href="admin_events.php">
            <div class="action-icon">📅</div>
            <div class="action-title">Événements</div>
            <div class="action-desc">Gérer le calendrier des formations</div>
        </a>
        <a class="action-card" href="admin_conteneurs.php">
            <div class="action-icon">🗳️</div>
            <div class="action-title">Conteneurs</div>
            <div class="action-desc">Gérer les conteneurs et dépôts</div>
        </a>
        <a class="action-card" href="admin_demandes_depot.php">
            <div class="action-icon">📋</div>
            <div class="action-title">Demandes dépôt</div>
            <div class="action-desc">Valider les demandes de dépôt</div>
        </a>
        <a class="action-card" href="admin_finance.php">
            <div class="action-icon">💰</div>
            <div class="action-title">Finance</div>
            <div class="action-desc">Suivi des paiements et factures</div>
        </a>
        <a class="action-card" href="admin_catalog.php">
            <div class="action-icon">🛍️</div>
            <div class="action-title">Catalogue</div>
            <div class="action-desc">Gérer les offres et prestations</div>
        </a>
        <a class="action-card" href="admin_documents.php">
            <div class="action-icon">📄</div>
            <div class="action-title">Documents</div>
            <div class="action-desc">Gérer les documents générés</div>
        </a>
        <a class="action-card" href="admin_audit.php">
            <div class="action-icon">🔍</div>
            <div class="action-title">Audit</div>
            <div class="action-desc">Consulter les logs d'activité</div>
        </a>
        <a class="action-card" href="admin_forum.php">
            <div class="action-icon">💬</div>
            <div class="action-title">Forum</div>
            <div class="action-desc">Modération et supervision</div>
        </a>
        <a class="action-card" href="admin_profile.php">
            <div class="action-icon">👤</div>
            <div class="action-title">Mon profil</div>
            <div class="action-desc">Gérer votre compte admin</div>
        </a>
    </div>
</main>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<script>
function toggleNotifDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('show');
}

window.onclick = function(event) {
    if (!event.target.matches('.btn-notif') && !event.target.closest('.btn-notif')) {
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
}
</script>
<?php  ?>
</body>
</html>