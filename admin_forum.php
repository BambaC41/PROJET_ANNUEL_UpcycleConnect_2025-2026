<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum_local.php';
require_once 'includes/functions/forum_api.php';  
require_once 'includes/ui_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['mod_action'] ?? ''));
    $target = trim((string)($_POST['mod_target'] ?? ''));
    $id = (int)($_POST['mod_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? 'Modération admin'));
    $modId = (int)($_SESSION['user_id'] ?? 0);
    $ok = false;
    if ($target === 'topic' && $id > 0) {
        $ok = forum_moderate_topic($id, $modId, $action, $reason);
    } elseif ($target === 'post' && $id > 0) {
        $ok = forum_moderate_post($id, $modId, $action, $reason);
    }
    $_SESSION['flash_toast'] = $ok
        ? ['type' => 'success', 'message' => 'Modération enregistrée.']
        : ['type' => 'error', 'message' => 'Modération impossible.'];
    header('Location: admin_forum.php');
    exit;
}

$allTopics = forum_get_topics(['per_page' => 50, 'status' => 'all', 'include_hidden' => true]);
$allTopics = array_values(array_filter($allTopics, static fn($t) => (int)($t['id'] ?? 0) > 0 && trim((string)($t['title'] ?? '')) !== ''));
$pendingReports = forum_get_pending_reports(10);
$logs = forum_get_moderation_logs(12);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Supervision forum</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    <section class="pro-card">
        <h1>💬 Forum — supervision</h1>
        <p class="muted">
            <a href="forum.php">🌐 Hub communautaire</a> ·
            <a href="admin_forum_reports.php">⚠️ Signalements (<?= count($pendingReports) ?>)</a> ·
            <a href="admin_forum_categories.php">📂 Catégories</a>
        </p>

        <?php if (!forum_schema_ready()): ?>
            <div class="error-box">⚠️ Forum non installé. Exécutez forum_migration_v2.sql ou réimportez upcycleconnect_v2.sql.</div>
        <?php else: ?>

        <?php if (!empty($pendingReports)): ?>
            <div class="warning-box" style="background:#fef3c7; color:#92400e; border-radius:12px; padding:12px; margin-bottom:16px;">
                🚨 <?= count($pendingReports) ?> signalement(s) en attente —
                <a href="admin_forum_reports.php" style="color:#92400e; font-weight:bold;">Traiter maintenant</a>
            </div>
        <?php endif; ?>

        <h2>📋 Sujets du forum</h2>
        <?php if (empty($allTopics)): ?>
            <div class="info-box" style="text-align:center; padding:24px;">Aucun sujet pour le moment.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Titre</th><th>Catégorie</th><th>Auteur</th><th>Vues</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($allTopics as $t): ?>
                    <tr>
                        <td><?= (int)($t['id'] ?? 0) ?></td>
                        <td><a href="forum_topic.php?id=<?= (int)($t['id'] ?? 0) ?>" target="_blank"><strong><?= e($t['title'] ?? '') ?></strong></a></td>
                        <td><?= e($t['category_name'] ?? '') ?></td>
                        <td><?= e($t['author_pseudo'] ?? '') ?></td>
                        <td><?= (int)($t['views_count'] ?? 0) ?></td>
                        <td class="row-actions">
                            <?php foreach (forum_topic_badges($t) as $b): ?>
                                <span class="status-badge <?= e($b['class']) ?>"><?= e($b['label']) ?></span>
                            <?php endforeach; ?>
                         </td>
                        <td class="row-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mod_target" value="topic">
                                <input type="hidden" name="mod_id" value="<?= (int)($t['id'] ?? 0) ?>">
                                <input type="hidden" name="mod_action" value="<?= !empty($t['is_locked']) ? 'unlock' : 'lock' ?>">
                                <button class="btn-outline" type="submit"><?= !empty($t['is_locked']) ? '🔓 Déverr.' : '🔒 Verrou.' ?></button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mod_target" value="topic">
                                <input type="hidden" name="mod_id" value="<?= (int)($t['id'] ?? 0) ?>">
                                <input type="hidden" name="mod_action" value="<?= !empty($t['is_hidden']) ? 'restore' : 'hide' ?>">
                                <button class="btn-outline" type="submit"><?= !empty($t['is_hidden']) ? '👁️ Réafficher' : '🙈 Masquer' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <h2 style="margin-top:24px;">📜 Journal de modération</h2>
        <?php if (empty($logs)): ?>
            <div class="info-box" style="text-align:center; padding:16px;">Aucune action de modération enregistrée.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Modérateur</th><th>Action</th><th>Cible</th><th>Raison</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= e(formatDateFr($l['created_at'] ?? '')) ?></td>
                        <td><?= e($l['moderator_pseudo'] ?? '') ?></td>
                        <td><?= e($l['action'] ?? '') ?></td>
                        <td><?= e(($l['target_type'] ?? '') . ' #' . ($l['target_id'] ?? '')) ?></td>
                        <td><?= e($l['reason'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>