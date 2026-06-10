<?php
require_once __DIR__ . '/functions/view_context.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/i18n.php';
$cu = vc_current_user();
$displayPseudo = $cu['pseudo'] ?? 'Pro';
$displayPhoto = vc_media_url($cu['photo_profil'] ?? '');
$displayInitial = strtoupper(substr((string)$displayPseudo, 0, 1));
$notifCount = notif_unread_count((int)($_SESSION['user_id'] ?? 0));
?>
<header class="navbar">
    <div class="logo"><a href="pro.php" style="text-decoration:none;color:#16a34a;">UpcycleConnect Pro</a></div>
    <nav class="auth-buttons">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:rgba(22,163,74,.08);">
            <span style="width:28px;height:28px;border-radius:50%;overflow:hidden;background:#16a34a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">
                <?php if ($displayPhoto !== ''): ?>
                    <img src="<?= vc_escape($displayPhoto) ?>" alt="Photo profil" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= vc_escape($displayInitial) ?>
                <?php endif; ?>
            </span>
            <strong><?= vc_escape($displayPseudo) ?></strong>
        </span>
        <a class="btn-outline" href="pro.php">Mon espace</a>
        <a class="btn-outline" href="pro_annonces.php">Annonces</a>
        <a class="btn-outline" href="pro_conteneurs.php">Conteneurs</a>
        <a class="btn-outline" href="pro_planning.php">Planning</a>
        <a class="btn-outline" href="pro_billing.php">Facturation</a>
        <a class="btn-outline" href="pro_projects.php">Projets</a>
        <a class="btn-outline" href="pro_documents.php">Documents</a>
        <a class="btn-outline" href="forum.php">Forum</a>
        <a class="btn-outline" href="notifications.php">Notifications<?= $notifCount > 0 ? ' (' . (int)$notifCount . ')' : '' ?></a>
        <a class="btn-outline" href="pro_profile.php">Profil</a>
        <a class="btn-outline" href="<?= vc_escape(lang_url('fr')) ?>">FR</a>
        <a class="btn-outline" href="<?= vc_escape(lang_url('en')) ?>">EN</a>
        <a class="btn-primary" href="logout.php">Deconnexion</a>
    </nav>
</header>
