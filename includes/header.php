<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/functions/view_context.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/i18n.php';
$cu = vc_current_user();
$roleId = (int)($_SESSION['role_id'] ?? 0);
$displayPseudo = $cu['pseudo'] ?? ($roleId === 1 ? 'Admin' : 'Compte');
$displayPhoto = vc_media_url($cu['photo_profil'] ?? '');
$displayInitial = strtoupper(substr((string)$displayPseudo, 0, 1));
$notifCount = notif_unread_count((int)($_SESSION['user_id'] ?? 0));
?>
<header class="navbar">
    <div class="logo">
        <a href="admin.php">
            <img src="assets/logo.png" alt="Logo UpcycleConnect">
        </a>
    </div>
    <nav>
        <a href="admin.php">🏠 Mon espace</a>
        <?php if (isset($_SESSION['token'])): ?>
            <span style="display:inline-flex;align-items:center;gap:8px;margin-right:10px;padding:6px 10px;border-radius:999px;background:rgba(22,163,74,.08);">
                <span style="width:28px;height:28px;border-radius:50%;overflow:hidden;background:#16a34a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">
                    <?php if ($displayPhoto !== ''): ?>
                        <img src="<?= vc_escape($displayPhoto) ?>" alt="Photo profil" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?= vc_escape($displayInitial) ?>
                    <?php endif; ?>
                </span>
                <strong><?= vc_escape($displayPseudo) ?></strong>
            </span>
            <div class="dropdown">
                <a href="#" class="dropbtn" onclick="toggleHeaderDropdown(event, this)">Admin ▾</a>
                <div class="dropdown-content">
                    <a href="admin.php">🏠 Mon espace</a>
                    <a href="admin.php">📊 Tableau de bord</a>
                    <a href="admin_users.php">👥 Utilisateurs</a>
                    <a href="admin_documents.php">📄 Documents</a>
                    <a href="notifications.php">🔔 Notifications<?= $notifCount > 0 ? ' (' . (int)$notifCount . ')' : '' ?></a>
                    <a href="admin_profile.php">👤 Mon profil</a>
                    <a href="<?= e(lang_url('fr')) ?>">FR</a>
                    <a href="<?= e(lang_url('en')) ?>">EN</a>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 0;">
                    <a href="logout.php" style="color: #dc2626; font-weight: bold;">🚪 Déconnexion</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php">Connexion</a>
        <?php endif; ?>
    </nav>
    <script src="scripts/header.js" defer></script>
</header>