<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$userId = session_ensure_user_id();
if ($userId <= 0 || $roleId < 1 || $roleId > 4) {
    header('Location: login.php');
    exit;
}
bootstrap_flash_unread_notifications();

$topicId = (int)($_GET['id'] ?? 0);
$isMod = in_array($roleId, [1, 4], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reply'])) {
        $content = trim((string)($_POST['content'] ?? ''));
        if ($content === '') {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Message vide.'];
        } elseif (forum_add_post($topicId, $userId, $content)) {
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Réponse publiée.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Impossible de répondre (sujet verrouillé ou fermé).'];
        }
    } elseif (isset($_POST['report_post_id'])) {
        $pid = (int)$_POST['report_post_id'];
        $reason = trim((string)($_POST['report_reason'] ?? 'inappropriate'));
        if (forum_report_post($pid, $userId, $reason, trim((string)($_POST['report_details'] ?? '')))) {
            forum_notify_moderators('Signalement forum', 'Nouveau signalement sur le sujet #' . $topicId);
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Signalement envoyé.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Signalement impossible.'];
        }
    } elseif ($isMod && isset($_POST['mod_action'], $_POST['mod_target'])) {
        $action = trim((string)$_POST['mod_action']);
        $target = trim((string)$_POST['mod_target']);
        $id = (int)($_POST['mod_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $ok = false;
        if ($target === 'topic') {
            $ok = forum_moderate_topic($id, $userId, $action, $reason);
        } elseif ($target === 'post') {
            $ok = forum_moderate_post($id, $userId, $action, $reason);
        }
        $_SESSION['flash_toast'] = $ok
            ? ['type' => 'success', 'message' => 'Modération appliquée.']
            : ['type' => 'error', 'message' => 'Action de modération impossible.'];
    }
    header('Location: forum_topic.php?id=' . $topicId);
    exit;
}

$topic = forum_get_topic($topicId, $isMod);
if ($topic === null) {
    http_response_code(404);
    $notFound = true;
    $posts = [];
} else {
    $notFound = false;
    forum_record_view($topicId, $userId);
    $topic = forum_get_topic($topicId, $isMod) ?? $topic;
    $posts = forum_get_posts($topicId, $isMod);
}

$navFile = match ($roleId) {
    3 => 'includes/pro_nav.php',
    4 => 'includes/employee_nav.php',
    1 => null,
    default => 'includes/particulier_nav.php',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $notFound ? 'Sujet introuvable' : e($topic['title'] ?? 'Forum') ?> — UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/ui-components.css">
</head>
<body>
<?php if ($navFile) {
    include __DIR__ . '/' . $navFile;
} ?>
<main class="page-shell" style="max-width:900px;margin:0 auto;padding:24px 16px;">
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
    <p><a href="forum.php" class="btn-outline" style="display:inline-block;">← Retour au forum</a></p>

    <?php if ($notFound): ?>
        <?php render_empty_state('Sujet introuvable', 'Ce sujet n’existe pas ou a été supprimé.', 'Retour au forum', 'forum.php'); ?>
    <?php else:
        $badges = forum_topic_badges($topic);
    ?>
        <h1><?= e($topic['title'] ?? '') ?></h1>
        <p class="muted">
            <?= e($topic['category_name'] ?? '') ?> · par <?= e($topic['author_pseudo'] ?? '') ?>
            · <?= (int)($topic['views_count'] ?? 0) ?> vues · <?= (int)($topic['posts_count'] ?? 0) ?> messages
            <?php foreach ($badges as $b): ?><span class="badge-status <?= e($b['class']) ?>"><?= e($b['label']) ?></span><?php endforeach; ?>
        </p>

        <?php if ($isMod): ?>
        <div class="actions-compact" style="margin:12px 0;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="mod_target" value="topic">
                <input type="hidden" name="mod_id" value="<?= (int)$topic['id'] ?>">
                <input type="hidden" name="mod_action" value="<?= !empty($topic['is_locked']) ? 'unlock' : 'lock' ?>">
                <button class="btn-outline" type="submit"><?= !empty($topic['is_locked']) ? 'Déverrouiller' : 'Verrouiller' ?></button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="mod_target" value="topic">
                <input type="hidden" name="mod_id" value="<?= (int)$topic['id'] ?>">
                <input type="hidden" name="mod_action" value="<?= !empty($topic['is_hidden']) ? 'restore' : 'hide' ?>">
                <button class="btn-outline" type="submit"><?= !empty($topic['is_hidden']) ? 'Réafficher' : 'Masquer' ?></button>
            </form>
        </div>
        <?php endif; ?>

        <?php foreach ($posts as $p): ?>
            <article class="forum-post <?= !empty($p['is_hidden']) ? 'is-hidden' : '' ?>">
                <div class="forum-post-meta">
                    <strong><?= e($p['author_pseudo'] ?? '') ?></strong>
                    · <?= e(formatDateFr($p['created_at'] ?? '')) ?>
                    <?php if (!empty($p['is_hidden'])): ?><span class="badge-reported">Masqué</span><?php endif; ?>
                </div>
                <div><?= nl2br(e($p['content'] ?? '')) ?></div>
                <div class="actions-compact" style="margin-top:8px;">
                    <?php if ((int)($p['author_id'] ?? 0) !== $userId): ?>
                    <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
                        <input type="hidden" name="report_post_id" value="<?= (int)$p['id'] ?>">
                        <select class="input" name="report_reason" style="max-width:120px;">
                            <option value="spam">Spam</option>
                            <option value="off_topic">Hors sujet</option>
                            <option value="inappropriate">Inapproprié</option>
                        </select>
                        <button class="btn-outline" type="submit">Signaler</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($isMod): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="mod_target" value="post">
                        <input type="hidden" name="mod_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="mod_action" value="<?= !empty($p['is_hidden']) ? 'restore' : 'hide' ?>">
                        <button class="btn-outline" type="submit"><?= !empty($p['is_hidden']) ? 'Réafficher' : 'Masquer' ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (empty($topic['is_locked'])): ?>
        <section style="margin-top:20px;">
            <h2>Répondre</h2>
            <form method="POST">
                <input type="hidden" name="add_reply" value="1">
                <textarea class="input" name="content" rows="4" required placeholder="Votre réponse…"></textarea>
                <button class="btn-primary" type="submit" style="margin-top:8px;">Publier la réponse</button>
            </form>
        </section>
        <?php else: ?>
            <p class="muted">Ce sujet est verrouillé — les réponses sont désactivées.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
