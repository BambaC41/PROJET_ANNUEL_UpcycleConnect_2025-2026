<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/notifications.php';

$users = api_get_users($_SESSION['token']);
$events = api_get_events($_SESSION['token']);
$pendingProsRes = api_get_pending_pros($_SESSION['token']);
$pendingPros = (($pendingProsRes['status'] ?? 0) === 200 && is_array($pendingProsRes['data'] ?? null)) ? $pendingProsRes['data'] : [];
$pendingAnnoncesRes = api_get_pending_annonces();
$pendingAnnonces = (($pendingAnnoncesRes['status'] ?? 0) === 200 && is_array($pendingAnnoncesRes['data'] ?? null)) ? $pendingAnnoncesRes['data'] : [];
$paiementsRes = api_get_all_paiements();
$paiements = (($paiementsRes['status'] ?? 0) === 200 && is_array($paiementsRes['data'] ?? null)) ? $paiementsRes['data'] : [];
$demandesRes = api_get_all_demandes_depot();
$demandes = (($demandesRes['status'] ?? 0) === 200 && is_array($demandesRes['data'] ?? null)) ? $demandesRes['data'] : [];
$revenu = array_reduce($paiements, fn($c, $p) => $c + (float)($p['montant'] ?? 0), 0);
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
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>

<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-content">
        <section class="pro-card">
            <h1>🏠 Administration générale - Dashboard</h1>
            
            <div class="pro-kpis">
                <article class="pro-kpi"><h3>👥 Utilisateurs</h3><p><?= e(is_array($users) ? count($users) : 0) ?></p></article>
                <article class="pro-kpi"><h3>👔 PRO en attente</h3><p><?= e(count($pendingPros)) ?></p></article>
                <article class="pro-kpi"><h3>📦 Annonces en attente</h3><p><?= e(count($pendingAnnonces)) ?></p></article>
                <article class="pro-kpi"><h3>📅 Événements</h3><p><?= e(is_array($events) ? count($events) : 0) ?></p></article>
            </div>
            
            <div class="pro-kpis">
                <article class="pro-kpi"><h3>🗳️ Dépôts conteneur</h3><p><?= e(count($demandes)) ?></p></article>
                <article class="pro-kpi"><h3>💳 Paiements</h3><p><?= e(count($paiements)) ?></p></article>
                <article class="pro-kpi"><h3>💰 Revenus</h3><p><?= e(number_format($revenu, 2, ',', ' ')) ?> €</p></article>
                <article class="pro-kpi"><h3>⚙️ Modules actifs</h3><p>8</p></article>
            </div>
        </section>

        <section class="pro-card">
            <h2>🚀 Accès rapide aux modules</h2>
            <div class="pro-grid" style="grid-template-columns:repeat(auto-fill, minmax(200px,1fr));">
                <a class="pro-card pro-link" href="admin_users.php"><h3>👥 Utilisateurs</h3><p>Gestion et validation PRO</p></a>
                <a class="pro-card pro-link" href="admin_annonces.php"><h3>📦 Annonces</h3><p>Validation des annonces</p></a>
                <a class="pro-card pro-link" href="admin_events.php"><h3>📅 Événements</h3><p>Gestion du calendrier</p></a>
                <a class="pro-card pro-link" href="admin_planning.php"><h3>🗓️ Planning</h3><p>Vue globale</p></a>
                <a class="pro-card pro-link" href="admin_conteneurs.php"><h3>🗳️ Conteneurs</h3><p>Gestion des dépôts</p></a>
                <a class="pro-card pro-link" href="admin_catalog.php"><h3>🛍️ Catalogue</h3><p>Offres et prestations</p></a>
                <a class="pro-card pro-link" href="admin_finance.php"><h3>💰 Finance</h3><p>Paiements et factures</p></a>
                <a class="pro-card pro-link" href="admin_forum.php"><h3>💬 Forum</h3><p>Modération</p></a>
            </div>
        </section>

        <section class="pro-card">
            <?php
            $dash_uid = (int)($_SESSION['user_id'] ?? 0);
            include __DIR__ . '/includes/dashboard_notifications.php';
            ?>
        </section>
    </main>
</div>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>