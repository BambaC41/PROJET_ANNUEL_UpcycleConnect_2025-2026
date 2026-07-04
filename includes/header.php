<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleConnect Admin</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/header.css">
    <link rel="stylesheet" href="styles/admin_global.css">
</head>
<body>
<header class="admin-header">
    <div class="header-container">
        <div class="logo">
            <a href="admin.php">
                <span class="logo-text">UpcycleConnect Admin</span>
            </a>
        </div>

        <nav class="main-nav">
            <ul>
                <li><a href="admin.php">🏠 Dashboard</a></li>
                <li><a href="admin_users.php">👥 Utilisateurs</a></li>
                <li><a href="admin_events.php">📅 Événements</a></li>
                <li><a href="admin_catalog.php">🛍️ Catalogue</a></li>
                <li><a href="admin_finance.php">💰 Finance</a></li>
                <li><a href="admin_forum.php">💬 Forum</a></li>
                <li><a href="admin_chat.php">💬 Chat</a></li>
                <li><a href="admin_profile.php">👤 Profil</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <div class="user-menu">
                <a href="logout.php" class="logout-btn">🔓 Déconnexion</a>
            </div>
        </div>
    </div>
</header>