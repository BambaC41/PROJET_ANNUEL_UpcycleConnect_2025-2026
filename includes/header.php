<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<header class="navbar">
    <div class="logo">
        <a href="admin.php">
            <img src="#logo.png" alt="Logo">
        </a>
    </div>
    <nav>
        <a href="admin.php">Accueil</a>
        <?php if (isset($_SESSION['token'])): ?>
            <div class="dropdown">
                <a href="#" class="dropbtn" onclick="toggleHeaderDropdown(event, this)">Admin ▾</a>
                <div class="dropdown-content">
                    <a href="admin.php">Tableau de bord</a>
                    <a href="#profile">Mon compte</a>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 0;">
                    <a href="logout.php" style="color: #dc2626; font-weight: bold;">Déconnexion</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php">Connexion</a>
        <?php endif; ?>
    </nav>
    <script src="scripts/header.js" defer></script>
</header>