<?php
require_once 'includes/employee_bootstrap.php';
require_once 'includes/functions/forum_local.php';

$userId = (int)$_SESSION['user_id'];

if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $warningId = (int)$_GET['id'];
    forum_mark_warning_read($warningId, $userId);
    header('Location: user_warnings.php');
    exit;
}
if (isset($_GET['mark_all_read'])) {
    db_safe_exec(function (PDO $pdo) use ($userId) {
        $st = $pdo->prepare('UPDATE user_warnings SET is_read = 1 WHERE user_id = ?');
        $st->execute([$userId]);
        return true;
    }, false);
    header('Location: user_warnings.php');
    exit;
}

$warnings = forum_get_user_warnings($userId, false);
$unreadCount = count(array_filter($warnings, fn($w) => empty($w['is_read'])));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes avertissements - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<main class="pro-shell page-shell">
    <a href="forum.php" class="back-link">← Retour au forum</a>
    
    <section class="pro-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
            <h1 style="margin: 0;">⚠️ Mes avertissements</h1>
            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="btn-all-read" onclick="return confirm('Marquer tous les avertissements comme lus ?');">✅ Tout marquer comme lu</a>
            <?php endif; ?>
        </div>
        
        <div class="stats-bar">
            <div>
                <strong>Total des avertissements :</strong> <?= count($warnings) ?>
            </div>
            <?php if ($unreadCount > 0): ?>
                <div class="stats-badge" style="background: #ff9800; color: white; padding: 6px 14px; border-radius: 30px; font-size: 14px; font-weight: 600;">
                    ⚠️ <?= $unreadCount ?> non lu(s)
                </div>
            <?php else: ?>
                <div class="stats-badge" style="background: #4caf50; color: white; padding: 6px 14px; border-radius: 30px; font-size: 14px; font-weight: 600;">
                    ✅ Tout est lu
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($warnings)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                <h3>Aucun avertissement reçu</h3>
                <p>Vous n'avez reçu aucun avertissement de la part des modérateurs.</p>
                <a href="forum.php" class="btn-primary" style="margin-top: 16px; display: inline-block;">Accéder au forum</a>
            </div>
        <?php else: ?>
            <?php foreach ($warnings as $w): 
                $isRead = !empty($w['is_read']);
            ?>
                <div class="warning-card <?= $isRead ? 'read' : '' ?>">
                    <div class="warning-header">
                        <div>
                            <strong>📅 <?= formatDateFr($w['created_at'] ?? '') ?></strong>
                            <?php if (!$isRead): ?>
                                <span class="badge-unread">Non lu</span>
                            <?php else: ?>
                                <span class="badge-read">Lu</span>
                            <?php endif; ?>
                        </div>
                        <div class="warning-meta">
                            👤 Par : <strong><?= e($w['admin_pseudo'] ?? 'Modérateur') ?></strong>
                        </div>
                    </div>
                    <div class="warning-message">
                        <?= nl2br(e($w['message'] ?? '')) ?>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <?php if (!$isRead): ?>
                            <a href="?mark_read=1&id=<?= (int)$w['id_warning'] ?>" class="btn-mark-read">✓ Marquer comme lu</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center;">
            <p class="muted" style="font-size: 12px;">
                Les avertissements sont donnés par les modérateurs en cas de non-respect des règles du forum.
                Veuillez prendre en compte ces avertissements pour éviter d'éventuels bannissements.
            </p>
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>
