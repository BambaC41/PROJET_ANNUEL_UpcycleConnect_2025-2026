<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/documents.php';
require_once 'includes/notifications.php';
$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
$annonces = api_get_annonces()['data'] ?? [];
$demandes = api_get_my_demandes_depot()['data'] ?? [];
$inscriptions = api_get_my_inscriptions()['data'] ?? [];
$paiements = api_get_my_paiements()['data'] ?? [];
$totalRevenue = array_reduce($paiements, function($c, $p){ return $c + (float)($p['montant'] ?? 0); }, 0);
$docs = document_list_for_user((int)($_SESSION['user_id'] ?? 0));
$notifUnread = notif_unread_count((int)($_SESSION['user_id'] ?? 0));
$projectsCount = (int)db_safe_exec(function(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_upcycling WHERE id_pro = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}, 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Professionnels & Artisans - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>

<main class="pro-shell page-shell">
    <section class="pro-kpis">
        <article class="pro-kpi">
            <h3>Abonnements & contrats</h3>
            <p><?= e(count($inscriptions)) ?></p>
            <small>Contrats actifs et suivis</small>
        </article>
        <article class="pro-kpi">
            <h3>Facturation</h3>
            <p><?= e(formatPriceEur($totalRevenue)) ?></p>
            <small>Paiements enregistres</small>
        </article>
        <article class="pro-kpi">
            <h3>Annonces achetables</h3>
            <p><?= e(count($annonces)) ?></p>
            <small>Flux d'annonces publiques</small>
        </article>
        <article class="pro-kpi">
            <h3>Objets conteneur</h3>
            <p><?= e(count($demandes)) ?></p>
            <small>Demandes avec codes et retraits</small>
        </article>
        <article class="pro-kpi">
            <h3>Projets d'upcycling</h3>
            <p><?= e($projectsCount) ?></p>
            <small>Projets crees par votre structure</small>
        </article>
        <article class="pro-kpi">
            <h3>Notifications</h3>
            <p><?= e($notifUnread) ?></p>
            <small>Elements non lus</small>
        </article>
        <article class="pro-kpi">
            <h3>Documents</h3>
            <p><?= e(count($docs)) ?></p>
            <small>Factures et recus telechargeables</small>
        </article>
    </section>

    <section class="pro-grid">
        <a class="pro-card pro-link" href="pro_billing.php"><h2>Contrats / Abonnements / Publicités</h2><p>Gérer les contrats actifs, échéances et factures.</p></a>
        <a class="pro-card pro-link" href="pro_annonces.php"><h2>Annonces et achats</h2><p>Consulter les annonces et effectuer des achats.</p></a>
        <a class="pro-card pro-link" href="pro_conteneurs.php"><h2>Récupération conteneurs</h2><p>Suivre les objets et leurs codes-barres.</p></a>
        <a class="pro-card pro-link" href="pro_projects.php"><h2>Projets d'Upcycling</h2><p>Suivi, mise en avant et avancement des projets.</p></a>
        <a class="pro-card pro-link" href="pro_profile.php"><h2>👤 Mon profil pro</h2><p>Consulter les informations de mon compte professionnel.</p></a>
        <a class="pro-card pro-link" href="pro_planning.php"><h2>Planning hebdomadaire</h2><p>Vue agenda type emploi du temps.</p></a>
        <div class="pro-card"><h2>🧭 Navigation pro</h2><p>Utilise les modules en haut pour gérer ton activité de manière détaillée.</p></div>
    </section>
    <?php $dash_uid = (int)($_SESSION['user_id'] ?? 0); include __DIR__ . '/includes/dashboard_notifications.php'; ?>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
