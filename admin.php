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
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>Administration générale - Dashboard</h1>
        <div class="admin-kpis">
            <div class="admin-card"><h3>Utilisateurs</h3><p><?= e(is_array($users) ? count($users) : 0) ?></p></div>
            <div class="admin-card"><h3>PRO en attente</h3><p><?= e(count($pendingPros)) ?></p></div>
            <div class="admin-card"><h3>Annonces en attente</h3><p><?= e(count($pendingAnnonces)) ?></p></div>
            <div class="admin-card"><h3>Événements</h3><p><?= e(is_array($events) ? count($events) : 0) ?></p></div>
        </div>
        <div class="admin-kpis" style="margin-top:14px;">
            <div class="admin-card"><h3>Dépôts conteneur</h3><p><?= e(count($demandes)) ?></p></div>
            <div class="admin-card"><h3>Paiements</h3><p><?= e(count($paiements)) ?></p></div>
            <div class="admin-card"><h3>Revenus</h3><p><?= e(number_format($revenu, 2, ',', ' ')) ?>€</p></div>
            <div class="admin-card"><h3>Modules actifs</h3><p>8</p></div>
        </div>
    </section>

    <section class="admin-section">
        <h2>Accès rapide modules</h2>
        <div class="admin-kpis">
            <a class="admin-card" href="admin_users.php"><h3>👥 Utilisateurs & validation PRO</h3><p>✅</p></a>
            <a class="admin-card" href="admin_annonces.php"><h3>📦 Validation annonces</h3><p>🧾</p></a>
            <a class="admin-card" href="admin_events.php"><h3>🎯 Événements</h3><p>📅</p></a>
            <a class="admin-card" href="admin_planning.php"><h3>🗓️ Planning</h3><p>⏱️</p></a>
            <a class="admin-card" href="admin_conteneurs.php"><h3>🗳️ Conteneurs</h3><p>📍</p></a>
            <a class="admin-card" href="admin_catalog.php"><h3>🛍️ Catalogue</h3><p>🏷️</p></a>
            <a class="admin-card" href="admin_finance.php"><h3>💳 Finance</h3><p>💶</p></a>
            <a class="admin-card" href="pro.php"><h3>🧰 Espace pro</h3><p>🚀</p></a>
        </div>
    </section>
    <section class="admin-section">
    <?php
    $dash_uid = (int)($_SESSION['user_id'] ?? 0);
    $dash_wrap_class = 'admin-card';
    include __DIR__ . '/includes/dashboard_notifications.php';
    ?>
    </section>
</section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
