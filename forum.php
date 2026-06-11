<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/i18n.php';
set_lang_from_request();
require_once __DIR__ . '/includes/functions/view_context.php';
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once __DIR__ . '/includes/functions/forum.php';
require_once __DIR__ . '/includes/functions/bootstrap_notify.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ui_helpers.php';

if (empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($roleId < 1 || $roleId > 4) {
    header('Location: ' . nav_role_dashboard_url());
    exit;
}

$userId = session_ensure_user_id();
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}
bootstrap_flash_unread_notifications();

$navFile = match ($roleId) {
    1 => null,
    3 => 'includes/pro_nav.php',
    4 => 'includes/employee_nav.php',
    default => 'includes/particulier_nav.php',
};
$bodyClass = match ($roleId) {
    1 => 'admin-page',
    3 => 'pro-page',
    4 => 'employee-page',
    default => 'particulier-page',
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    if ($categoryId <= 0 || $title === '' || $content === '') {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Catégorie, titre et message sont obligatoires.'];
    } elseif (!forum_schema_ready()) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Forum indisponible : tables non installées.'];
    } else {
        $tid = forum_create_topic($userId, $categoryId, $title, $content);
        if ($tid > 0) {
            forum_notify_moderators('Nouveau sujet forum', 'Sujet « ' . $title . ' » publié.');
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Sujet publié avec succès.'];
            header('Location: forum_topic.php?id=' . $tid);
            exit;
        }
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Impossible de publier le sujet.'];
    }
    header('Location: forum.php?' . http_build_query(['cat' => (int)($_GET['cat'] ?? 0), 'q' => $_GET['q'] ?? '']));
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$catFilter = (int)($_GET['cat'] ?? 0);
$categories = forum_get_categories();
$topics = forum_get_topics(['category_id' => $catFilter, 'q' => $q, 'per_page' => 40]);
$showCreate = $roleId >= 1 && $roleId <= 4;
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum — UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/ui-components.css">
    <?php if ($roleId === 1): ?><link rel="stylesheet" href="styles/admin.css"><?php endif; ?>
    <?php if ($roleId === 3): ?><link rel="stylesheet" href="styles/pro.css"><?php endif; ?>
    <?php if ($roleId === 4): ?><link rel="stylesheet" href="styles/employee.css"><?php endif; ?>
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
if ($roleId === 1) {
    include __DIR__ . '/includes/header.php';
    echo '<main class="admin-layout">';
    include __DIR__ . '/includes/sidebar.php';
    echo '<section class="admin-content"><section class="admin-section">';
} elseif ($navFile) {
    include __DIR__ . '/' . $navFile;
    echo '<main class="page-shell" style="max-width:1100px;margin:0 auto;padding:24px 16px 48px;">';
}
include __DIR__ . '/includes/flash_toast.php';
?>
    <h1>Forum communautaire</h1>
    <p class="muted"><a href="<?= e(nav_role_dashboard_url()) ?>">← Mon espace</a></p>

    <?php if (!forum_schema_ready()): ?>
        <?php render_empty_state('Forum non configuré', 'Importez le schéma forum (upcycleconnect_v2.sql ou forum_migration_v2.sql).'); ?>
    <?php else: ?>

    <form method="GET" class="row-actions" style="margin:16px 0;flex-wrap:wrap;">
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un sujet…">
        <select class="input" name="cat">
            <option value="0">Toutes catégories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn-outline" type="submit">Filtrer</button>
    </form>

    <?php if ($showCreate): ?>
    <details class="emp-card" style="margin-bottom:18px;padding:14px;">
        <summary style="cursor:pointer;font-weight:600;">＋ Créer un sujet</summary>
        <form method="POST" style="margin-top:14px;display:grid;gap:10px;">
            <input type="hidden" name="create_topic" value="1">
            <select class="input" name="category_id" required>
                <option value="">— Catégorie —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="text" name="title" maxlength="200" placeholder="Titre du sujet" required>
            <textarea class="input" name="content" rows="4" placeholder="Votre message initial" required></textarea>
            <button class="btn-primary" type="submit">Publier le sujet</button>
        </form>
    </details>
    <?php endif; ?>

    <div class="forum-hub-grid">
        <?php foreach ($categories as $c): ?>
            <a class="forum-cat-card" href="forum.php?cat=<?= (int)$c['id'] ?>" style="text-decoration:none;color:inherit;">
                <strong><?= e($c['name']) ?></strong>
                <p class="muted" style="margin:6px 0 0;font-size:13px;"><?= e($c['description'] ?? '') ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <h2 style="margin-top:24px;">Sujets récents</h2>
    <?php if (empty($topics)): ?>
        <?php render_empty_state('Aucun sujet', 'Soyez le premier à lancer une discussion.', $showCreate ? 'Créer un sujet' : null, $showCreate ? '#' : null); ?>
    <?php else: ?>
        <?php foreach ($topics as $t):
            if ((int)($t['id'] ?? 0) <= 0) {
                continue;
            }
            $badges = forum_topic_badges($t);
        ?>
            <div class="forum-topic-row">
                <div>
                    <a href="forum_topic.php?id=<?= (int)$t['id'] ?>" style="font-weight:600;"><?= e($t['title'] ?? '') ?></a>
                    <div class="muted" style="font-size:13px;">
                        <?= e($t['category_name'] ?? '') ?> · <?= e($t['author_pseudo'] ?? '') ?> · <?= e(formatDateFr($t['created_at'] ?? '')) ?>
                        <?php foreach ($badges as $b): ?>
                            <span class="badge-status <?= e($b['class']) ?>"><?= e($b['label']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="muted" style="text-align:right;font-size:13px;">
                    <?= (int)($t['posts_count'] ?? 0) ?> rép.<br><?= (int)($t['views_count'] ?? 0) ?> vues
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
<?php
if ($roleId === 1) {
    echo '</section></section></main>';
} else {
    echo '</main>';
}
?>
</body>
</html>
