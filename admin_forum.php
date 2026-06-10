<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum_local.php';
require_once 'includes/functions/forum.php';
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
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <?php include 'includes/flash_toast.php'; ?>
    <section class="admin-section">
        <h1>Forum — supervision</h1>
        <p class="muted">
            <a href="forum.php">Hub communautaire</a> ·
            <a href="admin_forum_reports.php">Signalements (<?= count($pendingReports) ?>)</a> ·
            <a href="admin_forum_categories.php">Catégories</a>
        </p>

        <?php if (!forum_schema_ready()): ?>
            <?php render_empty_state('Forum non installé', 'Exécutez forum_migration_v2.sql ou réimportez upcycleconnect_v2.sql.'); ?>
        <?php else: ?>

        <?php if (!empty($pendingReports)): ?>
            <div class="alert-error" style="background:#fef3c7;color:#92400e;border-radius:8px;padding:12px;margin:12px 0;">
                <?= count($pendingReports) ?> signalement(s) en attente —
                <a href="admin_forum_reports.php">Traiter</a>
            </div>
        <?php endif; ?>

        <h2>Sujets</h2>
        <?php if (empty($allTopics)): ?>
            <?php render_empty_state('Aucun sujet', 'Le forum est vide pour le moment.', 'Voir le hub', 'forum.php'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Titre</th><th>Catégorie</th><th>Auteur</th><th>Vues</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($allTopics as $t): ?>
                <tr>
                    <td><?= (int)$t['id'] ?></td>
                    <td><a href="forum_topic.php?id=<?= (int)$t['id'] ?>"><?= e($t['title'] ?? '') ?></a></td>
                    <td><?= e($t['category_name'] ?? '') ?></td>
                    <td><?= e($t['author_pseudo'] ?? '') ?></td>
                    <td><?= (int)($t['views_count'] ?? 0) ?></td>
                    <td>
                        <?php foreach (forum_topic_badges($t) as $b): ?>
                            <span class="badge-status <?= e($b['class']) ?>"><?= e($b['label']) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <form method="POST" class="actions-compact">
                            <input type="hidden" name="mod_target" value="topic">
                            <input type="hidden" name="mod_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="mod_action" value="<?= !empty($t['is_locked']) ? 'unlock' : 'lock' ?>">
                            <button class="btn-outline" type="submit"><?= !empty($t['is_locked']) ? 'Déverr.' : 'Verrou.' ?></button>
                        </form>
                        <form method="POST" class="actions-compact" style="margin-top:4px;">
                            <input type="hidden" name="mod_target" value="topic">
                            <input type="hidden" name="mod_id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="mod_action" value="<?= !empty($t['is_hidden']) ? 'restore' : 'hide' ?>">
                            <button class="btn-outline" type="submit"><?= !empty($t['is_hidden']) ? 'Réafficher' : 'Masquer' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <h2 style="margin-top:24px;">Journal de modération</h2>
        <?php if (empty($logs)): ?>
            <?php render_empty_state('Aucune action', 'Les actions de modération apparaîtront ici.'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Modérateur</th><th>Action</th><th>Cible</th><th>Raison</th></tr></thead>
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
</section>
</main>
<style>.badge-status{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:600;margin-right:4px}.badge-open{background:#dcfce7;color:#166534}.badge-closed{background:#f1f5f9;color:#475569}.badge-reported{background:#fee2e2;color:#991b1b}.badge-pinned{background:#dbeafe;color:#1d4ed8}</style>
</body>
</html>
