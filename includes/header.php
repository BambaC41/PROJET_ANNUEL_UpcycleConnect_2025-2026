<?php
// includes/header.php - Header pour toutes les pages (admin, pro, particulier, salarie)
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = str_starts_with($currentPage, 'admin_') || $currentPage === 'admin.php';
$isPro = str_starts_with($currentPage, 'pro_') || $currentPage === 'pro.php';
$isSalarie = str_starts_with($currentPage, 'salarie_') || $currentPage === 'salarie.php';
$isParticulier = str_starts_with($currentPage, 'particulier_') || $currentPage === 'particulier.php';

// Déterminer le titre et la classe du header
if ($isAdmin) {
    $headerClass = 'admin-header';
    $siteTitle = 'UpcycleConnect Admin';
} elseif ($isPro) {
    $headerClass = 'pro-header';
    $siteTitle = 'UpcycleConnect Pro';
} elseif ($isSalarie) {
    $headerClass = 'salarie-header';
    $siteTitle = 'UpcycleConnect Salarié';
} else {
    $headerClass = 'particulier-header';
    $siteTitle = 'UpcycleConnect';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteTitle) ?></title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/header.css">
</head>
<body>
<header class="<?= e($headerClass) ?>">
    <div class="header-container">
        <div class="logo">
            <a href="<?= $isAdmin ? 'admin.php' : ($isPro ? 'pro.php' : ($isSalarie ? 'salarie.php' : 'index.php')) ?>">
                <img src="assets/logo.png" alt="UpcycleConnect" style="height:45px;">
                <span class="logo-text"><?= e($siteTitle) ?></span>
            </a>
        </div>
        
        <nav class="main-nav">
            <ul>
                <?php if ($isAdmin): ?>
                    <li><a href="admin.php">🏠 Dashboard</a></li>
                    <li><a href="admin_users.php">👥 Utilisateurs</a></li>
                    <li><a href="admin_events.php">📅 Événements</a></li>
                    <li><a href="admin_catalog.php">🛍️ Catalogue</a></li>
                    <li><a href="admin_finance.php">💰 Finance</a></li>
                    <li><a href="admin_forum.php">💬 Forum</a></li>
                    <li><a href="admin_profile.php">👤 Profil</a></li>
                <?php elseif ($isPro): ?>
                    <li><a href="pro.php">🏠 Dashboard</a></li>
                    <li><a href="pro_annonces.php">📦 Annonces</a></li>
                    <li><a href="pro_conteneurs.php">🗳️ Conteneurs</a></li>
                    <li><a href="pro_planning.php">🗓️ Planning</a></li>
                    <li><a href="pro_billing.php">💰 Facturation</a></li>
                    <li><a href="pro_projects.php">📁 Projets</a></li>
                    <li><a href="pro_profile.php">👤 Profil</a></li>
                <?php elseif ($isSalarie): ?>
                    <li><a href="salarie.php">🏠 Dashboard</a></li>
                    <li><a href="salarie_events.php">📅 Événements</a></li>
                    <li><a href="salarie_planning.php">🗓️ Planning</a></li>
                    <li><a href="salarie_conseils.php">💡 Conseils</a></li>
                    <li><a href="salarie_forum.php">💬 Forum</a></li>
                    <li><a href="salarie_profile.php">👤 Profil</a></li>
                <?php else: ?>
                    <li><a href="index.php">🏠 Accueil</a></li>
                    <li><a href="particulier_catalogue.php">🛍️ Catalogue</a></li>
                    <li><a href="particulier_annonces.php">📦 Annonces</a></li>
                    <li><a href="particulier_conteneurs.php">🗳️ Conteneurs</a></li>
                    <li><a href="particulier_planning.php">🗓️ Planning</a></li>
                    <li><a href="particulier_profile.php">👤 Profil</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="header-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-menu">
                    <span class="user-name">
                        <?php if (!empty($_SESSION['user_pseudo'])): ?>
                            <?= e($_SESSION['user_pseudo']) ?>
                        <?php elseif (!empty($_SESSION['user_email'])): ?>
                            <?= e(explode('@', $_SESSION['user_email'])[0]) ?>
                        <?php else: ?>
                            Utilisateur
                        <?php endif; ?>
                    </span>
                    <a href="logout.php" class="logout-btn">🔓 Déconnexion</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">🔐 Connexion</a>
                <a href="register.php" class="register-btn">📝 Inscription</a>
            <?php endif; ?>
        </div>
    </div>
</header>