<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum_local.php';
require_once 'includes/ui_helpers.php';

$status = trim((string)($_GET['status'] ?? 'pending'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['report_id']) && isset($_POST['report_status'])) {
        $id = (int)$_POST['report_id'];
        $st = trim((string)$_POST['report_status']);
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $st) {
            $sql = 'UPDATE forum_reports SET status = ?, handled_at = NOW() WHERE id_report = ?';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$st, $id]);
        }, false);
        $_SESSION['flash_toast'] = $ok
            ? ['type' => 'success', 'message' => 'Signalement traité.']
            : ['type' => 'error', 'message' => 'Erreur lors du traitement.'];
        header('Location: admin_forum_reports.php?status=' . urlencode($status) . '&page=' . $page);
        exit;
    }
    
    if (isset($_POST['hide_post_id'])) {
        $postId = (int)$_POST['hide_post_id'];
        $reason = trim((string)($_POST['reason'] ?? 'Signalement traité'));
        $ok = forum_moderate_post($postId, (int)$_SESSION['user_id'], 'hide', $reason);
        $_SESSION['flash_toast'] = $ok
            ? ['type' => 'success', 'message' => 'Message masqué.']
            : ['type' => 'error', 'message' => 'Erreur lors du masquage.'];
        header('Location: admin_forum_reports.php?status=' . urlencode($status) . '&page=' . $page);
        exit;
    }
}

$reports = [];
$totalReports = 0;

$tableExists = db_safe_exec(function (PDO $pdo) {
    $result = $pdo->query("SHOW TABLES LIKE 'forum_reports'");
    return $result && $result->rowCount() > 0;
}, false);

if ($tableExists) {
    $reports = (array)db_safe_exec(function (PDO $pdo) use ($status, $page, $perPage) {
        $sql = "SELECT 
                    r.id_report,
                    r.post_id,
                    r.reporter_id,
                    r.reason,
                    r.details,
                    r.status,
                    r.created_at,
                    r.handled_at,
                    p.content as post_content,
                    t.id_topic,
                    t.title as topic_title,
                    COALESCE(u.pseudo, 'Inconnu') as reporter_pseudo,
                    COALESCE(a.pseudo, 'Inconnu') as author_pseudo
                FROM forum_reports r
                LEFT JOIN forum_posts p ON p.id_post = r.post_id
                LEFT JOIN forum_topics t ON t.id_topic = p.topic_id
                LEFT JOIN utilisateur u ON u.id_user = r.reporter_id
                LEFT JOIN utilisateur a ON a.id_user = p.author_id
                WHERE 1=1";
        
        $params = [];
        if ($status !== 'all') {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, []);
    
    $totalReports = (int)db_safe_exec(function (PDO $pdo) use ($status) {
        $sql = "SELECT COUNT(*) FROM forum_reports r";
        if ($status !== 'all') {
            $sql .= " WHERE r.status = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query($sql);
        }
        return (int)$stmt->fetchColumn();
    }, 0);
}

$totalPages = max(1, (int)ceil($totalReports / $perPage));
$pendingCount = (int)db_safe_exec(function(PDO $pdo) {
    $st = $pdo->query("SELECT COUNT(*) FROM forum_reports WHERE status = 'pending'");
    return $st ? (int)$st->fetchColumn() : 0;
}, 0);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Signalements forum</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="admin-section">
        <?php include 'includes/flash_toast.php'; ?>
        
        <h1>⚠️ Forum — Signalements</h1>
        <p class="muted">
            <a href="admin_forum.php">← Supervision forum</a> ·
            <a href="admin_forum_moderation.php">🛡️ Modération centralisée</a>
        </p>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number total"><?= $totalReports ?></div>
                <div class="stat-label">Total signalements</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pending"><?= $pendingCount ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </div>

        <div class="filter-bar">
            <a href="?status=all" class="filter-btn <?= $status === 'all' ? 'active' : '' ?>">📋 Tous</a>
            <a href="?status=pending" class="filter-btn <?= $status === 'pending' ? 'active' : '' ?>">⏳ En attente</a>
            <a href="?status=reviewed" class="filter-btn <?= $status === 'reviewed' ? 'active' : '' ?>">✅ Traités</a>
            <a href="?status=dismissed" class="filter-btn <?= $status === 'dismissed' ? 'active' : '' ?>">❌ Rejetés</a>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <h3>Aucun signalement</h3>
                <p>Les signalements apparaîtront ici lorsque des utilisateurs signaleront des messages.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r): ?>
                <div class="report-card <?= e($r['status'] ?? 'pending') ?>">
                    <div class="report-header">
                        <div>
                            <strong>📝 Signalement #<?= (int)($r['id_report'] ?? 0) ?></strong>
                            <span class="report-badge <?= e($r['status'] ?? 'pending') ?>">
                                <?php 
                                    switch($r['status'] ?? 'pending') {
                                        case 'pending': echo '⏳ En attente'; break;
                                        case 'reviewed': echo '✅ Traité'; break;
                                        case 'dismissed': echo '❌ Rejeté'; break;
                                        default: echo e($r['status'] ?? '?');
                                    }
                                ?>
                            </span>
                            <?php if (!empty($r['reason'])): ?>
                                <span class="reason-tag reason-<?= e($r['reason']) ?>">
                                    <?php
                                        switch($r['reason'] ?? 'spam') {
                                            case 'spam': echo '📧 Spam'; break;
                                            case 'off_topic': echo '💬 Hors sujet'; break;
                                            case 'inappropriate': echo '🚫 Inapproprié'; break;
                                            default: echo e($r['reason']);
                                        }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            <?= !empty($r['created_at']) ? formatDateFr($r['created_at']) : 'Date inconnue' ?>
                        </div>
                    </div>
                    
                    <div class="report-content">
                        <div><strong>📂 Sujet :</strong> 
                            <a href="forum_topic.php?id=<?= (int)($r['id_topic'] ?? 0) ?>" target="_blank">
                                <?= e($r['topic_title'] ?? 'Sujet inconnu') ?>
                            </a>
                        </div>
                        <div><strong>👤 Auteur :</strong> <?= e($r['author_pseudo'] ?? 'Inconnu') ?></div>
                        <div><strong>👤 Signalé par :</strong> <?= e($r['reporter_pseudo'] ?? 'Inconnu') ?></div>
                        <?php if (!empty($r['details'])): ?>
                            <div style="margin-top: 8px; padding: 6px 10px; background: #fff3e0; border-radius: 6px;">
                                <strong>📝 Détails :</strong> <?= e($r['details']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="report-message">
                            <strong>💬 Message :</strong><br>
                            "<?= e(mb_substr($r['post_content'] ?? '', 0, 200)) ?>"
                        </div>
                    </div>
                    
                    <div class="report-actions">
                        <?php if (($r['status'] ?? '') === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="report_id" value="<?= (int)($r['id_report'] ?? 0) ?>">
                                <input type="hidden" name="report_status" value="reviewed">
                                <button class="btn-success btn-sm" type="submit">✅ Traiter</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="report_id" value="<?= (int)($r['id_report'] ?? 0) ?>">
                                <input type="hidden" name="report_status" value="dismissed">
                                <button class="btn-outline btn-sm" type="submit">❌ Rejeter</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Masquer ce message ?');">
                                <input type="hidden" name="hide_post_id" value="<?= (int)($r['post_id'] ?? 0) ?>">
                                <input type="hidden" name="reason" value="Signalement #<?= (int)($r['id_report'] ?? 0) ?>">
                                <button class="btn-warning btn-sm" type="submit" style="background:#ff9800;color:white;border:none;">🙈 Masquer</button>
                            </form>
                        <?php endif; ?>
                        <a href="forum_topic.php?id=<?= (int)($r['id_topic'] ?? 0) ?>" target="_blank" class="btn-outline btn-sm">🔗 Voir le sujet</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <nav style="display: flex; gap: 12px; justify-content: center; margin-top: 30px;">
                    <?php if ($page > 1): ?>
                        <a class="btn-outline" href="?status=<?= e($status) ?>&page=<?= $page - 1 ?>">← Précédent</a>
                    <?php endif; ?>
                    <span>Page <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn-outline" href="?status=<?= e($status) ?>&page=<?= $page + 1 ?>">Suivant →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?php  ?>
</body>
</html>