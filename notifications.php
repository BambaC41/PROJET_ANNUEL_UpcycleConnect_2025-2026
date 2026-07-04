<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/i18n.php';
set_lang_from_request();
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/functions/view_context.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$userId = session_ensure_user_id();
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['mark_id'])) {
        notif_mark_read($userId, (int)$_POST['mark_id']);
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Notification marquée comme lue.'];
    } elseif (!empty($_POST['mark_all'])) {
        notif_mark_all_read($userId);
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Toutes les notifications sont marquées lues.'];
    }
    header('Location: notifications.php');
    exit;
}

$notifications = notif_list($userId, 100, 0);
$role = (int)($_SESSION['role_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/employee.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php
// Choix du header/navigation en fonction du rôle
if ($role === 1) {
    // Admin : on utilise le header admin (qui contient le sidebar)
    include __DIR__ . '/includes/header.php';
} elseif ($role === 3) {
    include __DIR__ . '/includes/pro_nav.php';
} elseif ($role === 4) {
    include __DIR__ . '/includes/employee_nav.php';
} else {
    include __DIR__ . '/includes/particulier_nav.php';
}
?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1>Mes notifications</h1>

        <?php if (!empty($notifications)): ?>
        <form method="POST" class="row-actions" style="margin-bottom:14px;">
            <input type="hidden" name="mark_all" value="1">
            <button class="btn-outline" type="submit">✅ Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
        
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Titre</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                        <tr>
                            <td colspan="6" class="muted">Aucune notification pour le moment. Effectuez une action (inscription, paiement, annonce…) pour en recevoir.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notifications as $index => $n): ?>
                            <tr class="notif-row <?= empty($n['is_read']) ? 'notif-unread' : '' ?>" 
                                onclick="openNotificationModal(<?= $index ?>)">
                                <td><?= e($n['type'] ?? '') ?></td>
                                <td><?= e($n['titre'] ?? '') ?></td>
                                <td><?= e(mb_substr($n['contenu'] ?? '', 0, 80)) ?><?= mb_strlen($n['contenu'] ?? '') > 80 ? '...' : '' ?></td>
                                <td><?= e(formatDateFr($n['created_at'] ?? '')) ?></td>
                                <td><?= !empty($n['is_read']) ? '<span class="status-badge status-ok">✅ Lue</span>' : '<span class="status-badge status-warn">🟡 Non lue</span>' ?></td>
                                <td>
                                    <?php if (empty($n['is_read'])): ?>
                                        <form method="POST" onclick="event.stopPropagation()">
                                            <input type="hidden" name="mark_id" value="<?= (int)($n['id_notification'] ?? 0) ?>">
                                            <button class="btn-primary" type="submit">Marquer lue</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<div id="notifModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📬 Détail de la notification</h3>
            <button class="modal-close" onclick="closeNotificationModal()">&times;</button>
        </div>
        <div class="modal-body" id="notifModalBody">
            <!-- Contenu dynamique -->
        </div>
    </div>
</div>

<script>
const notificationsData = <?php 
    $notifArray = [];
    foreach ($notifications as $n) {
        $typeClass = 'notif-type-system';
        $type = strtolower($n['type'] ?? '');
        if (strpos($type, 'forum') !== false || strpos($type, 'moderation') !== false) {
            $typeClass = 'notif-type-forum';
        } elseif (strpos($type, 'warning') !== false || strpos($type, 'avert') !== false) {
            $typeClass = 'notif-type-warning';
        } elseif (strpos($type, 'paiement') !== false || strpos($type, 'stripe') !== false || strpos($type, 'facture') !== false) {
            $typeClass = 'notif-type-paiement';
        }
        
        $typeLabel = $n['type'] ?? 'Système';
        if (strpos($type, 'forum_warning') !== false) $typeLabel = '⚠️ Avertissement forum';
        elseif (strpos($type, 'forum_moderation') !== false) $typeLabel = '🛡️ Modération forum';
        elseif (strpos($type, 'paiement') !== false) $typeLabel = '💳 Paiement';
        elseif (strpos($type, 'evenement') !== false) $typeLabel = '📅 Événement';
        elseif (strpos($type, 'depot') !== false) $typeLabel = '📦 Dépôt';
        elseif (strpos($type, 'retrait') !== false) $typeLabel = '🚚 Retrait';
        elseif (strpos($type, 'annonce') !== false) $typeLabel = '📢 Annonce';
        elseif (strpos($type, 'system') !== false) $typeLabel = '⚙️ Système';
        
        $notifArray[] = [
            'id' => (int)($n['id_notification'] ?? 0),
            'type' => $typeLabel,
            'type_class' => $typeClass,
            'titre' => e($n['titre'] ?? ''),
            'contenu' => nl2br(e($n['contenu'] ?? '')),
            'date' => formatDateFr($n['created_at'] ?? ''),
            'is_read' => !empty($n['is_read'])
        ];
    }
    echo json_encode($notifArray);
?>;

function openNotificationModal(index) {
    const notif = notificationsData[index];
    if (!notif) return;
    
    const modalBody = document.getElementById('notifModalBody');
    modalBody.innerHTML = `
        <div class="notif-type ${notif.type_class}">${notif.type}</div>
        <div class="notif-title">📌 ${notif.titre}</div>
        <div class="notif-content">${notif.contenu}</div>
        <div class="notif-date">📅 ${notif.date}</div>
        <button class="btn-close-modal" onclick="closeNotificationModal()">Fermer</button>
    `;
    
    document.getElementById('notifModal').classList.add('show');
}

function closeNotificationModal() {
    document.getElementById('notifModal').classList.remove('show');
}

window.onclick = function(event) {
    const modal = document.getElementById('notifModal');
    if (event.target === modal) {
        closeNotificationModal();
    }
}
</script>
<?php  ?>
</body>
</html>