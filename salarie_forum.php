<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once 'includes/functions/forum_api.php';  
require_once __DIR__ . '/includes/ui_helpers.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['mod_action'] ?? ''));
    $target = trim((string)($_POST['mod_target'] ?? ''));
    $id = (int)($_POST['mod_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? 'Modération salarié'));
    $ok = false;
    if ($target === 'topic' && $id > 0) {
        $ok = forum_moderate_topic($id, $userId, $action, $reason);
    } elseif ($target === 'post' && $id > 0) {
        $ok = forum_moderate_post($id, $userId, $action, $reason);
    } elseif (isset($_POST['report_id'], $_POST['report_status'])) {
        $rid = (int)$_POST['report_id'];
        $st = trim((string)$_POST['report_status']);
        $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($rid, $st, $userId): bool {
            $u = $pdo->prepare('UPDATE forum_reports SET status = ?, handled_by = ?, handled_at = NOW() WHERE id_report = ?');
            return $u->execute([$st, $userId, $rid]);
        }, false);
    }
    toast_redirect('salarie_forum.php', $ok ? 'success' : 'error', $ok ? 'Action enregistrée.' : 'Action impossible.');
}

$pending = forum_get_pending_reports(25);
$topics = forum_get_topics(['per_page' => 20, 'include_hidden' => true]);
$topics = array_values(array_filter($topics, static fn($t) => (int)($t['id'] ?? 0) > 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Forum — Animation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/employee.css">
    <link rel="stylesheet" href="styles/ui-components.css">
</head>
<body class="employee-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<main class="employee-shell">
    <section class="emp-card">
        <h1>Forum — animation & modération</h1>
        <p class="muted">
            <a href="forum.php">Accéder au hub forum communautaire</a> ·
            <a href="salarie.php">← Tableau de bord</a>
        </p>
    </section>

    <?php if (!forum_schema_ready()): ?>
        <?php render_empty_state('Forum indisponible', 'Réimportez le schéma SQL forum.'); ?>
    <?php else: ?>

    <section class="emp-card">
        <h2>Signalements en attente (<?= count($pending) ?>)</h2>
        <?php if (empty($pending)): ?>
            <?php render_empty_state('Aucun signalement', 'La file de modération est vide.'); ?>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Sujet</th><th>Raison</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $r): ?>
                <tr>
                    <td><a href="forum_topic.php?id=<?= (int)($r['topic_id'] ?? 0) ?>"><?= e($r['topic_title'] ?? 'Sujet') ?></a></td>
                    <td><?= e($r['reason'] ?? '') ?></td>
                    <td><?= e(formatDateFr($r['created_at'] ?? '')) ?></td>
                    <td class="actions-compact">
                        <form method="POST"><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="report_status" value="reviewed"><button class="btn-outline" type="submit">Traité</button></form>
                        <form method="POST"><input type="hidden" name="mod_target" value="post"><input type="hidden" name="mod_id" value="<?= (int)$r['post_id'] ?>"><input type="hidden" name="mod_action" value="hide"><button class="btn-danger" type="submit">Masquer msg</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="emp-card">
        <h2>Sujets récents</h2>
        <?php if (empty($topics)): render_empty_state('Aucun sujet', 'Créez le premier sujet depuis le hub.', 'Hub forum', 'forum.php'); else: ?>
        <?php foreach ($topics as $t):
            $badges = forum_topic_badges($t);
        ?>
            <div class="forum-topic-row">
                <div>
                    <a href="forum_topic.php?id=<?= (int)$t['id'] ?>" style="font-weight:600;"><?= e($t['title'] ?? '') ?></a>
                    <?php foreach ($badges as $b): ?><span class="badge-status <?= e($b['class']) ?>"><?= e($b['label']) ?></span><?php endforeach; ?>
                </div>
                <form method="POST" class="actions-compact">
                    <input type="hidden" name="mod_target" value="topic">
                    <input type="hidden" name="mod_id" value="<?= (int)$t['id'] ?>">
                    <input type="hidden" name="mod_action" value="<?= !empty($t['is_locked']) ? 'unlock' : 'lock' ?>">
                    <button class="btn-outline" type="submit"><?= !empty($t['is_locked']) ? 'Déverrouiller' : 'Verrouiller' ?></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
