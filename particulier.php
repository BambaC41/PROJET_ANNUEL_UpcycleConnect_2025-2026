<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/notifications.php';
require_once 'includes/functions/documents.php';
require_once 'includes/functions/local_db.php';

$annonces = api_get_my_annonces()['data'] ?? [];
$inscriptions = api_get_my_inscriptions()['data'] ?? [];
$demandes = api_get_my_demandes_depot()['data'] ?? [];
$score = api_get_my_score()['data'] ?? [];
$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
$localTutorial = db_safe_exec(function (PDO $pdo) {
    $stmt = $pdo->prepare('SELECT tutorial_completed FROM utilisateur WHERE id_user = ?');
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    return (int)$stmt->fetchColumn();
}, 0);
$docs = document_list_for_user((int)($_SESSION['user_id'] ?? 0));
$notifUnread = notif_unread_count((int)($_SESSION['user_id'] ?? 0));
$tutorialCompleted = ((int)($me['tutorial_completed'] ?? -1) === 1) || ((int)$localTutorial === 1);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Particulier</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-kpis">
        <article class="pro-kpi"><h3>📦 Mes annonces</h3><p><?= e(count($annonces)) ?></p></article>
        <article class="pro-kpi"><h3>🗓️ Inscriptions</h3><p><?= e(count($inscriptions)) ?></p></article>
        <article class="pro-kpi"><h3>🗳️ Dépôts</h3><p><?= e(count($demandes)) ?></p></article>
        <article class="pro-kpi"><h3>♻️ Upcycling score</h3><p><?= e((int)($score['score_global'] ?? 0)) ?></p></article>
        <article class="pro-kpi"><h3>🔔 Notifications</h3><p><?= e($notifUnread) ?></p></article>
        <article class="pro-kpi"><h3>📄 Documents</h3><p><?= e(count($docs)) ?></p></article>
    </section>
    <section class="pro-grid">
        <a class="pro-card pro-link" href="particulier_annonces.php"><h2>📦 Gérer mes annonces</h2><p>Dépôt don/vente, suivi des validations.</p></a>
        <a class="pro-card pro-link" href="particulier_conteneurs.php"><h2>🗳️ Dépôts conteneur</h2><p>Codes d'accès et code-barres.</p></a>
        <a class="pro-card pro-link" href="particulier_conseils.php"><h2>💡 Espace conseils</h2><p>Guides et astuces upcycling.</p></a>
        <a class="pro-card pro-link" href="particulier_catalogue.php"><h2>🛍️ Catalogue</h2><p>Formations, services, événements.</p></a>
        <a class="pro-card pro-link" href="particulier_planning.php"><h2>🗓️ Planning</h2><p>Vue emploi du temps hebdo.</p></a>
        <a class="pro-card pro-link" href="particulier_documents.php"><h2>📄 Mes documents</h2><p>Reçus, attestations et fiches de dépôt.</p></a>
        <a class="pro-card pro-link" href="notifications.php"><h2>🔔 Notifications</h2><p>Suivre validations et confirmations importantes.</p></a>
        <a class="pro-card pro-link" href="particulier_profile.php"><h2>👤 Mon profil</h2><p><?= e($me['prenom'] ?? 'Utilisateur') ?>, gérer mon compte.</p></a>
    </section>
    <?php $dash_uid = (int)($_SESSION['user_id'] ?? 0); include __DIR__ . '/includes/dashboard_notifications.php'; ?>
</main>

<?php if (!$tutorialCompleted): ?>
<script>
    window.firstConnection = true;
</script>
<script src="scripts/tutorial.js"></script>
<script>
    if (typeof startTour === 'function') {
        setTimeout(startTour, 500);
    }
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>