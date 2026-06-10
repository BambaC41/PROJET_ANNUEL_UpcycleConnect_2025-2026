<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum.php';

$status = trim((string)($_GET['status'] ?? 'pending'));
$page = max(1, (int)($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['report_status'])) {
    $id = (int)$_POST['report_id'];
    $st = trim((string)$_POST['report_status']);
    $res = api_admin_forum_put('reports/' . $id, ['status' => $st]);
    $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
        ? ['type' => 'success', 'message' => 'Signalement traité.']
        : ['type' => 'error', 'message' => forum_api_error_message($res)];
    header('Location: admin_forum_reports.php?status=' . urlencode($status) . '&page=' . $page);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hide_post_id'])) {
    $pid = (int)$_POST['hide_post_id'];
    $res = api_admin_forum_put('posts/' . $pid . '/hide', ['reason' => trim((string)($_POST['reason'] ?? 'Signalement traité'))]);
    $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
        ? ['type' => 'success', 'message' => 'Message masqué.']
        : ['type' => 'error', 'message' => forum_api_error_message($res)];
    header('Location: admin_forum_reports.php?status=' . urlencode($status));
    exit;
}

$res = api_admin_forum_get('reports', ['status' => $status !== 'all' ? $status : '', 'page' => $page, 'per_page' => 25]);
$reports = forum_items_from_response($res);
$total = (int)($res['data']['total'] ?? count($reports));
$totalPages = max(1, (int)ceil($total / 25));
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
        <h1>Forum — signalements</h1>
        <p class="muted"><a href="admin_forum.php">← Modération forum</a></p>

        <form method="GET" class="row-actions" style="margin:12px 0;">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Traités</option>
                <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Rejetés</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>

        <table class="admin-table">
            <thead>
            <tr><th>ID</th><th>Sujet</th><th>Extrait</th><th>Motif</th><th>Statut</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="6" style="text-align:center;">Aucun signalement.</td></tr>
            <?php else: foreach ($reports as $r): ?>
                <tr>
                    <td><?= (int)($r['id'] ?? 0) ?></td>
                    <td><?= e($r['topic_title'] ?? '') ?></td>
                    <td><?= e($r['post_preview'] ?? '') ?></td>
                    <td><?= e($r['reason'] ?? '') ?><?= !empty($r['details']) ? '<br><span class="muted">' . e($r['details']) . '</span>' : '' ?></td>
                    <td>
                        <?php
                        $st = (string)($r['status'] ?? 'pending');
                        $cls = $st === 'pending' ? 'badge-draft' : ($st === 'reviewed' ? 'badge-published' : 'badge-closed');
                        ?>
                        <span class="badge-status <?= e($cls) ?>"><?= e($st) ?></span>
                    </td>
                    <td style="display:flex;flex-wrap:wrap;gap:6px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?= (int)($r['id'] ?? 0) ?>">
                            <input type="hidden" name="report_status" value="reviewed">
                            <button class="btn-primary" type="submit">Traiter</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="report_id" value="<?= (int)($r['id'] ?? 0) ?>">
                            <input type="hidden" name="report_status" value="dismissed">
                            <button class="btn-outline" type="submit">Rejeter</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Masquer ce message ?');">
                            <input type="hidden" name="hide_post_id" value="<?= (int)($r['post_id'] ?? 0) ?>">
                            <input type="hidden" name="reason" value="Suite signalement #<?= (int)($r['id'] ?? 0) ?>">
                            <button class="btn-danger" type="submit">Masquer post</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="row-actions" style="margin-top:12px;">
                <?php if ($page > 1): ?>
                    <a class="btn-outline" href="?status=<?= e($status) ?>&page=<?= $page - 1 ?>">← Précédent</a>
                <?php endif; ?>
                <span class="muted">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn-outline" href="?status=<?= e($status) ?>&page=<?= $page + 1 ?>">Suivant →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
</main>
<style>.badge-status{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:600}.badge-draft{background:#fef3c7;color:#92400e}.badge-published{background:#dcfce7;color:#166534}.badge-closed{background:#f1f5f9;color:#475569}</style>
</body>
</html>
