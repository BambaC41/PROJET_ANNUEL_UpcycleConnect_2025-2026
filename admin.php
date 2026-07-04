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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
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