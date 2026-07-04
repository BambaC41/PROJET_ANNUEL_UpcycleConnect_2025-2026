<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/i18n.php';
set_lang_from_request();
require_once __DIR__ . '/includes/functions/view_context.php';
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once __DIR__ . '/includes/functions/forum_api.php';
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

function forum_is_user_banned_check(int $userId): bool {
    return (bool)db_safe_exec(function (PDO $pdo) use ($userId): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM forum_bans WHERE user_id = ? AND banned_until > NOW()');
        $st->execute([$userId]);
        return $st->fetchColumn() > 0;
    }, false);
}

if (forum_is_user_banned_check($userId)) {
    $banInfo = db_safe_exec(function (PDO $pdo) use ($userId) {
        $st = $pdo->prepare('SELECT reason, banned_until FROM forum_bans WHERE user_id = ? AND banned_until > NOW() LIMIT 1');
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }, []);
    $banUntil = isset($banInfo['banned_until']) ? date('d/m/Y H:i', strtotime($banInfo['banned_until'])) : 'date inconnue';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head><meta charset="UTF-8"><title>Accès restreint - Forum</title><link rel="stylesheet" href="styles/style.css"><link rel="stylesheet" href="styles/admin_global.css"></head>
    <body class="pro-page">
        <main style="max-width: 600px; margin: 80px auto; padding: 20px;">
            <div class="error-box" style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 16px;">🚫</div>
                <h2>Vous êtes banni du forum</h2>
                <p><strong>Raison :</strong> <?= e($banInfo['reason'] ?? 'Non spécifiée') ?></p>
                <p><strong>Banni jusqu'au :</strong> <?= e($banUntil) ?></p>
                <a href="<?= nav_role_dashboard_url() ?>" class="btn-primary" style="margin-top: 20px;">Retour au tableau de bord</a>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$navFile = match ($roleId) {
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
    
    if (mb_strlen($title) > 200) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Le titre ne peut pas dépasser 200 caractères.'];
    } elseif (mb_strlen($content) > 1000) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Le message ne peut pas dépasser 1000 caractères.'];
    } elseif ($categoryId <= 0 || $title === '' || $content === '') {
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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php

include __DIR__ . '/includes/header.php';

echo '<main class="page-shell" style="max-width:1200px;margin:0 auto;padding:24px 20px 48px;">';

if ($roleId !== 1) {
    include __DIR__ . '/' . $navFile;
}

include __DIR__ . '/includes/flash_toast.php';
?>

<div class="forum-header">
    <h1>💬 Forum communautaire</h1>
    <p>Échangez, partagez et apprenez avec la communauté UpcycleConnect</p>
</div>

<?php if (!forum_schema_ready()): ?>
    <div class="error-box">Forum non configuré.</div>
<?php else: ?>

<form method="GET" style="margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un sujet..." style="flex: 1; padding: 12px;">
    <select class="input" name="cat" style="width: 200px;">
        <option value="0">Toutes catégories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn-primary" type="submit">🔍 Rechercher</button>
</form>

<?php if ($showCreate): ?>
<div class="create-topic-card">
    <details>
        <summary class="create-topic-summary">✏️ Créer un nouveau sujet</summary>
        <form method="POST" style="margin-top: 20px; display: grid; gap: 16px;">
            <input type="hidden" name="create_topic" value="1">
            <select class="input" name="category_id" required>
                <option value="">— Choisir une catégorie —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="text" name="title" maxlength="200" placeholder="Titre du sujet (max 200 caractères)" required>
            <textarea class="input" name="content" rows="5" maxlength="1000" placeholder="Votre message (max 1000 caractères)" required oninput="updateCharCount(this)"></textarea>
            <div class="char-counter" id="topicCounter">0 / 1000 caractères</div>
            <button class="btn-primary" type="submit">📤 Publier le sujet</button>
        </form>
    </details>
</div>
<?php endif; ?>

<h2 style="margin: 0 0 16px 0;">📂 Catégories</h2>
<div class="forum-categories-grid">
    <?php foreach ($categories as $c): ?>
        <a class="forum-category-card" href="forum.php?cat=<?= (int)$c['id'] ?>">
            <div class="forum-category-icon">💬</div>
            <div class="forum-category-name"><?= e($c['name']) ?></div>
            <div class="forum-category-desc"><?= e($c['description'] ?? '') ?></div>
        </a>
    <?php endforeach; ?>
</div>

<h2 style="margin: 32px 0 16px 0;">📋 Sujets récents</h2>
<div class="forum-topics-list">
    <div class="forum-topics-header">
        <div style="flex:1;">Sujet</div>
        <div style="width: 100px; text-align: center;">Réponses</div>
        <div style="width: 100px; text-align: center;">Vues</div>
    </div>
    
    <?php if (empty($topics)): ?>
        <div class="empty-state">Aucun sujet pour le moment.</div>
    <?php else: ?>
        <?php foreach ($topics as $t):
            if ((int)($t['id'] ?? 0) <= 0) continue;
            $badges = forum_topic_badges($t);
        ?>
            <div class="forum-topic-item">
                <div class="forum-topic-main">
                    <a class="forum-topic-title" href="forum_topic.php?id=<?= (int)$t['id'] ?>">
                        <?= e(mb_substr($t['title'] ?? '', 0, 200)) ?>
                        <?php foreach ($badges as $b): ?>
                            <span class="badge-topic <?= e($b['class']) ?>"><?= e($b['label']) ?></span>
                        <?php endforeach; ?>
                    </a>
                    <div class="forum-topic-meta">
                        <span>📂 <?= e($t['category_name'] ?? '') ?></span>
                        <span>👤 <?= e($t['author_pseudo'] ?? '') ?></span>
                        <span>🕐 <?= forum_timeAgo($t['created_at'] ?? '') ?></span>
                    </div>
                </div>
                <div class="forum-topic-stats">
                    <div class="forum-topic-replies">💬 <?= (int)($t['posts_count'] ?? 0) ?></div>
                    <div class="forum-topic-views">👁️ <?= (int)($t['views_count'] ?? 0) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php endif; ?>
<?php

echo '</main>';
?>
<script>
function updateCharCount(textarea) {
    let len = textarea.value.length;
    let counter = document.getElementById('topicCounter');
    if (counter) {
        counter.textContent = len + ' / 1000 caractères';
        if (len >= 1000) counter.classList.add('warning');
        else counter.classList.remove('warning');
    }
}
</script>
</body>
</html>