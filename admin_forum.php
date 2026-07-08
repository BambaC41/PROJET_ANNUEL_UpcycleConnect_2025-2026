<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum_local.php';
require_once 'includes/ui_helpers.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['mod_action'] ?? ''));
    $target = trim((string)($_POST['mod_target'] ?? ''));
    $id = (int)($_POST['mod_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? 'Modération admin'));
    $ok = false;
    
    if ($target === 'topic' && $id > 0) {
        $ok = forum_moderate_topic($id, $userId, $action, $reason);
    } elseif ($target === 'post' && $id > 0) {
        $ok = forum_moderate_post($id, $userId, $action, $reason);
    }
    
    $_SESSION['flash_toast'] = $ok
        ? ['type' => 'success', 'message' => 'Modération appliquée.']
        : ['type' => 'error', 'message' => 'Action impossible.'];
    header('Location: admin_forum.php');
    exit;
}

function traduireAction($action) {
    $map = [
        'HIDE_POST' => 'Masquage de message',
        'RESTORE_POST' => 'Restauration de message',
        'HIDE_TOPIC' => 'Masquage de sujet',
        'RESTORE_TOPIC' => 'Restauration de sujet',
        'LOCK_TOPIC' => 'Verrouillage de sujet',
        'UNLOCK_TOPIC' => 'Déverrouillage de sujet',
        'handle_report' => 'Traitement de signalement',
        'update_category' => 'Modification de catégorie',
        'delete_category' => 'Suppression de catégorie',
        'create_category' => 'Création de catégorie',
    ];
    return $map[$action] ?? $action;
}

$allTopics = forum_get_topics(['per_page' => 100, 'status' => 'all', 'include_hidden' => true]);
$pendingReports = forum_get_pending_reports(10);
$logs = forum_get_moderation_logs(20);
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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>💬 Supervision du forum</h1>
        <p class="muted">
            <a href="forum.php">🌐 Voir le forum public</a> ·
            <a href="admin_forum_reports.php">⚠️ Signalements (<?= count($pendingReports) ?>)</a> ·
            <a href="admin_forum_categories.php">📂 Gérer les catégories</a> ·
            <a href="admin_forum_moderation.php">🛡️ Modération centralisée</a>
        </p>
        
        <div class="stats-cards">
            <div class="stat-card"><div class="stat-number"><?= count($allTopics) ?></div><div class="stat-label">Sujets</div></div>
            <div class="stat-card"><div class="stat-number"><?= count($pendingReports) ?></div><div class="stat-label">Signalements</div></div>
            <div class="stat-card"><div class="stat-number"><?= count($logs) ?></div><div class="stat-label">Actions récentes</div></div>
        </div>
        
        <?php if (!empty($pendingReports)): ?>
            <div class="warning-box">
                🚨 <strong><?= count($pendingReports) ?> signalement(s) en attente</strong> — 
                <a href="admin_forum_reports.php" style="color:#92400e; font-weight:bold;">Traiter maintenant</a>
            </div>
        <?php endif; ?>
        
        <h2>📋 Sujets du forum</h2>
        <div class="table-responsive">
            <table class="topic-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Auteur</th>
                        <th>Vues</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allTopics)): ?>
                        <tr><td colspan="7" style="text-align:center;">Aucun sujet trouvé.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($allTopics as $t): ?>
                        <tr>
                            <td><?= (int)($t['id'] ?? 0) ?></td>
                            <td>
                                <a href="forum_topic.php?id=<?= (int)($t['id'] ?? 0) ?>" target="_blank" style="font-weight:600;">
                                    <?= e(mb_substr($t['title'] ?? '', 0, 60)) ?>
                                </a>
                            </td>
                            <td><?= e($t['category_name'] ?? '—') ?></td>
                            <td><?= e($t['author_pseudo'] ?? '—') ?></td>
                            <td><?= (int)($t['views_count'] ?? 0) ?></td>
                            <td>
                                <?php foreach (forum_topic_badges($t) as $b): ?>
                                    <span class="status-badge <?= e($b['class']) ?>"><?= e($b['label']) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="row-actions">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="mod_target" value="topic">
                                    <input type="hidden" name="mod_id" value="<?= (int)($t['id'] ?? 0) ?>">
                                    <input type="hidden" name="mod_action" value="<?= !empty($t['is_locked']) ? 'unlock' : 'lock' ?>">
                                    <button class="btn-outline btn-sm" type="submit"><?= !empty($t['is_locked']) ? '🔓 Déverrouiller' : '🔒 Verrouiller' ?></button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="mod_target" value="topic">
                                    <input type="hidden" name="mod_id" value="<?= (int)($t['id'] ?? 0) ?>">
                                    <input type="hidden" name="mod_action" value="<?= !empty($t['is_hidden']) ? 'restore' : 'hide' ?>">
                                    <button class="btn-outline btn-sm" type="submit"><?= !empty($t['is_hidden']) ? '👁️ Réafficher' : '🙈 Masquer' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer-center">
            <button onclick="toggleLogs()" class="btn-toggle-logs" id="toggleLogsBtn">
                📜 Afficher le journal de modération
            </button>
        </div>
        
        <div id="logsSection" class="logs-section">
            <h2>📜 Journal de modération</h2>
            <?php if (empty($logs)): ?>
                <div class="info-box" style="text-align:center;">Aucune action de modération enregistrée.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table log-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Modérateur</th>
                            <th>Action</th>
                            <th>Cible</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $index => $l): 
                        $actionTraduite = traduireAction($l['action'] ?? '');
                    ?>
                        <tr>
                            <td><?= formatDateFr($l['created_at'] ?? '') ?></td>
                            <td><?= e($l['moderator_pseudo'] ?? '—') ?></td>
                            <td><span class="action-traduite"><?= $actionTraduite ?></span></td>
                            <td><?= e(($l['target_type'] ?? '') . ' #' . ($l['target_id'] ?? '')) ?></td>
                            <td><button class="btn-view-log" onclick="showLogDetails(<?= $index ?>)">👁️ Voir</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<div id="logModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📋 Détails de l'action</h3>
            <button class="modal-close" onclick="closeLogModal()">&times;</button>
        </div>
        <div class="modal-body" id="logModalBody">
        </div>
    </div>
</div>

<script>
const logsData = <?php 
    $logsArray = [];
    foreach ($logs as $l) {
        $logsArray[] = [
            'date' => formatDateFr($l['created_at'] ?? ''),
            'moderator' => e($l['moderator_pseudo'] ?? '—'),
            'action' => traduireAction($l['action'] ?? ''),
            'action_raw' => e($l['action'] ?? '—'),
            'target_type' => e($l['target_type'] ?? '—'),
            'target_id' => (int)($l['target_id'] ?? 0),
            'reason' => !empty($l['reason']) ? e($l['reason']) : 'Aucune raison spécifiée'
        ];
    }
    echo json_encode($logsArray);
?>;

let logsVisible = false;

function toggleLogs() {
    const logsSection = document.getElementById('logsSection');
    const toggleBtn = document.getElementById('toggleLogsBtn');
    
    if (logsVisible) {
        logsSection.classList.remove('show');
        toggleBtn.innerHTML = '📜 Afficher le journal de modération';
        toggleBtn.style.background = '#ff9800';
        logsVisible = false;
    } else {
        logsSection.classList.add('show');
        toggleBtn.innerHTML = '🙈 Masquer le journal de modération';
        toggleBtn.style.background = '#f44336';
        logsVisible = true;
    }
}

function showLogDetails(index) {
    const log = logsData[index];
    if (!log) return;
    
    const modalBody = document.getElementById('logModalBody');
    modalBody.innerHTML = `
        <div class="detail-row">
            <span class="detail-label">📌 Action :</span>
            <span class="detail-value"><strong>${log.action}</strong> <span style="color:#999; font-size:11px;">(${log.action_raw})</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">🕐 Date :</span>
            <span class="detail-value">${log.date}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">👤 Modérateur :</span>
            <span class="detail-value">${log.moderator}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">🎯 Cible :</span>
            <span class="detail-value">${log.target_type} #${log.target_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">📝 Raison :</span>
            <span class="detail-value">${log.reason}</span>
        </div>
        <hr>
        <div class="detail-row">
            <span class="detail-label">🔍 Résumé :</span>
            <span class="detail-value">Le modérateur <strong>${log.moderator}</strong> a effectué l'action <strong>${log.action}</strong> sur ${log.target_type} #${log.target_id} le ${log.date}.</span>
        </div>
    `;
    
    document.getElementById('logModal').classList.add('show');
}

function closeLogModal() {
    document.getElementById('logModal').classList.remove('show');
}

window.onclick = function(event) {
    const modal = document.getElementById('logModal');
    if (event.target === modal) {
        closeLogModal();
    }
}
</script>
<?php  ?>
</body>
</html>
