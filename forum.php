<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/i18n.php';
set_lang_from_request();
require_once __DIR__ . '/includes/functions/view_context.php';
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once __DIR__ . '/includes/functions/forum_api.php';  // ← MODIFIÉ
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

// Fonction temps relatif
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'à l\'instant';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    if ($diff < 604800) return floor($diff / 86400) . ' j';
    return date('d/m/Y', $timestamp);
}
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
    <style>
        /* Forum moderne style Quora-like */
        .forum-header {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
        }
        .forum-header h1 { margin: 0 0 8px 0; font-size: 32px; }
        .forum-header p { margin: 0; opacity: 0.9; }
        
        .forum-categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 24px 0 32px 0;
        }
        .forum-category-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: block;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .forum-category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #4caf50;
        }
        .forum-category-icon { font-size: 32px; margin-bottom: 12px; }
        .forum-category-name { font-size: 18px; font-weight: 700; color: #2e7d32; margin: 0 0 8px 0; }
        .forum-category-desc { color: #6c757d; font-size: 13px; margin: 0 0 12px 0; line-height: 1.5; }
        .forum-category-stats { display: flex; gap: 16px; font-size: 12px; color: #6c757d; border-top: 1px solid #e9ecef; padding-top: 12px; margin-top: 8px; }
        
        .forum-topics-list { background: white; border-radius: 16px; border: 1px solid #e9ecef; overflow: hidden; }
        .forum-topics-header {
            display: flex;
            background: #f8f9fa;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 1px solid #e9ecef;
        }
        .forum-topic-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        .forum-topic-item:hover { background: #f8f9fa; }
        .forum-topic-main { flex: 1; min-width: 0; }
        .forum-topic-title {
            font-weight: 600;
            color: #1a1a2e;
            text-decoration: none;
            font-size: 16px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .forum-topic-title:hover { color: #4caf50; }
        .forum-topic-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #6c757d;
        }
        .forum-topic-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .forum-topic-stats { text-align: right; min-width: 100px; font-size: 13px; }
        .forum-topic-replies { font-weight: 600; color: #2e7d32; }
        .forum-topic-views { color: #6c757d; font-size: 12px; }
        .badge-topic {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-pinned { background: #fff3e0; color: #ef6c00; }
        .badge-locked { background: #f1f5f9; color: #475569; }
        
        .create-topic-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .create-topic-summary {
            cursor: pointer;
            font-weight: 600;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .char-counter { font-size: 11px; color: #666; margin-top: 4px; text-align: right; }
        .char-counter.warning { color: #f44336; }
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .forum-topics-header { display: none; }
            .forum-topic-item { flex-direction: column; align-items: flex-start; gap: 12px; }
            .forum-topic-stats { text-align: left; }
            .forum-categories-grid { grid-template-columns: 1fr; }
            .forum-header { padding: 20px; }
            .forum-header h1 { font-size: 24px; }
        }
    </style>
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
    echo '<main class="page-shell" style="max-width:1200px;margin:0 auto;padding:24px 20px 48px;">';
}
include __DIR__ . '/includes/flash_toast.php';
?>

<!-- En-tête forum -->
<div class="forum-header">
    <h1>💬 Forum communautaire</h1>
    <p>Échangez, partagez et apprenez avec la communauté UpcycleConnect</p>
</div>

<?php if (!forum_schema_ready()): ?>
    <div class="error-box">Forum non configuré. Contactez l'administrateur.</div>
<?php else: ?>

<!-- Barre de recherche -->
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

<!-- Créer un sujet -->
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
            <button class="btn-primary" type="submit" style="align-self: flex-start;">📤 Publier le sujet</button>
        </form>
    </details>
</div>
<?php endif; ?>

<!-- Catégories -->
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

<!-- Sujets récents -->
<h2 style="margin: 32px 0 16px 0;">📋 Sujets récents</h2>
<div class="forum-topics-list">
    <div class="forum-topics-header">
        <div style="flex:1;">Sujet</div>
        <div style="width: 100px; text-align: center;">Réponses</div>
        <div style="width: 100px; text-align: center;">Vues</div>
    </div>
    
    <?php if (empty($topics)): ?>
        <div class="empty-state">
            <p>Aucun sujet pour le moment.</p>
            <?php if ($showCreate): ?>
                <p>Soyez le premier à lancer une discussion !</p>
            <?php endif; ?>
        </div>
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
                        <span>🕐 <?= timeAgo($t['created_at'] ?? '') ?></span>
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
if ($roleId === 1) {
    echo '</section></section></main>';
} else {
    echo '</main>';
}
?>
<script>
function updateCharCount(textarea) {
    let len = textarea.value.length;
    let counter = document.getElementById('topicCounter');
    if (counter) {
        counter.textContent = len + ' / 1000 caractères';
        if (len >= 1000) {
            counter.classList.add('warning');
        } else {
            counter.classList.remove('warning');
        }
    }
}
const topicTextarea = document.querySelector('textarea[name="content"]');
if (topicTextarea) {
    updateCharCount(topicTextarea);
    topicTextarea.addEventListener('input', function() { updateCharCount(this); });
}
</script>
</body>
</html>