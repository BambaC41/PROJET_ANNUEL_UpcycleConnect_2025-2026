<?php
session_start();
require_once 'includes/i18n.php';
set_lang_from_request();

$isConnected = !empty($_SESSION['token']);
$targetDashboard = 'login.php';
if ($isConnected) {
    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId === 1) {
        $targetDashboard = 'admin.php';
    } elseif ($roleId === 4) {
        $targetDashboard = 'salarie.php';
    } elseif ($roleId === 3) {
        $targetDashboard = 'pro.php';
    } elseif ($roleId === 2) {
        $targetDashboard = 'particulier.php';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title>UpcycleConnect - Accueil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
</head>
<body>
<header class="navbar">
    <div class="logo"><a href="index.php" style="text-decoration:none;color:#16a34a;">UpcycleConnect</a></div>
    <nav class="auth-buttons">
        <a href="index.php"><?= htmlspecialchars(t('nav.home')) ?></a>
        <a class="btn-outline" href="login.php"><?= htmlspecialchars(t('nav.login')) ?></a>
        <a class="btn-primary" href="register.php"><?= htmlspecialchars(t('nav.register')) ?></a>
        <a class="btn-outline" href="?lang=fr">FR</a>
        <a class="btn-outline" href="?lang=en">EN</a>
    </nav>
</header>

<main class="landing">
    <section class="hero-block">
        <h1><?= htmlspecialchars(t('landing.title')) ?></h1>
        <p><?= htmlspecialchars(t('landing.subtitle')) ?></p>
        <div class="cta-actions">
            <a class="btn-primary" href="register.php"><?= htmlspecialchars(t('landing.start')) ?></a>
            <a class="btn-outline" href="<?= htmlspecialchars($targetDashboard) ?>"><?= htmlspecialchars(t('landing.space')) ?></a>
        </div>
    </section>

    <section class="feature-grid">
        <article class="feature-card">
            <h3>Publier facilement</h3>
            <p>Creez vos annonces et suivez leur validation depuis votre espace particulier.</p>
        </article>
        <article class="feature-card">
            <h3>Deposer en conteneur</h3>
            <p>Demandez un depot, recevez vos codes d'acces et suivez le statut de chaque demande.</p>
        </article>
        <article class="feature-card">
            <h3>Apprendre et progresser</h3>
            <p>Consultez des conseils pertinents et augmentez votre score d'upcycling.</p>
        </article>
    </section>

    <section class="hero-block soft">
        <h2>Creer un particulier</h2>
        <p>Besoin d'un formulaire rapide pour inscrire un particulier ?</p>
        <a class="btn-primary" href="create_particulier.php">Acceder au formulaire particulier</a>
    </section>
</main>
</body>
</html>
