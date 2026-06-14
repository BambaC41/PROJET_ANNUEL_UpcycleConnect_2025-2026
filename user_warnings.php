<?php
require_once 'includes/employee_bootstrap.php';
require_once 'includes/functions/forum_local.php';

$userId = (int)$_SESSION['user_id'];

// Marquer un avertissement comme lu
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $warningId = (int)$_GET['id'];
    forum_mark_warning_read($warningId, $userId);
    header('Location: user_warnings.php');
    exit;
}

// Marquer tous les avertissements comme lus
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
    <style>
        .warning-card {
            background: #fef3c7;
            border-left: 4px solid #ff9800;
            padding: 16px;
            margin-bottom: 16px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .warning-card.read {
            background: #f5f5f5;
            border-left-color: #9e9e9e;
            opacity: 0.8;
        }
        .warning-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .warning-message {
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            margin-bottom: 12px;
        }
        .warning-meta {
            font-size: 12px;
            color: #666;
        }
        .badge-unread {
            background: #ff9800;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-read {
            background: #9e9e9e;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .btn-mark-read {
            background: transparent;
            border: 1px solid #ff9800;
            color: #ff9800;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        .btn-mark-read:hover {
            background: #ff9800;
            color: white;
        }
        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        .stats-badge {
            background: #ff9800;
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-all-read {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-all-read:hover {
            background: #2e7d32;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .back-link:hover {
            color: #2e7d32;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #666;
        }
    </style>
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
                <div class="stats-badge">
                    ⚠️ <?= $unreadCount ?> non lu(s)
                </div>
            <?php else: ?>
                <div class="stats-badge" style="background: #4caf50;">
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