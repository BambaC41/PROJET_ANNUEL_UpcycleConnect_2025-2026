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
<?php if ($role === 1): ?>
<?php include __DIR__ . '/includes/head.php'; ?>
<body class="admin-page">
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="admin-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>Mes notifications</h1>
<?php else: ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/employee.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php
if ($role === 3) {
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
<?php endif; ?>
        <?php if (!empty($notifications)): ?>
        <form method="POST" class="row-actions" style="margin-bottom:14px;">
            <input type="hidden" name="mark_all" value="1">
            <button class="btn-outline" type="submit">Tout marquer comme lu</button>
        </form>
        <?php endif; ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Type</th><th>Titre</th><th>Message</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($notifications)): ?>
                <tr><td colspan="6" class="muted">Aucune notification pour le moment. Effectuez une action (inscription, paiement, annonce…) pour en recevoir.</td></tr>
            <?php else: foreach ($notifications as $n): ?>
                <tr>
                    <td><?= e($n['type'] ?? '') ?></td>
                    <td><?= e($n['titre'] ?? '') ?></td>
                    <td><?= e($n['contenu'] ?? '') ?></td>
                    <td><?= e(formatDateFr($n['created_at'] ?? '')) ?></td>
                    <td><?= !empty($n['is_read']) ? 'Lue' : 'Non lue' ?></td>
                    <td>
                        <?php if (empty($n['is_read'])): ?>
                            <form method="POST">
                                <input type="hidden" name="mark_id" value="<?= (int)($n['id_notification'] ?? 0) ?>">
                                <button class="btn-primary" type="submit">Marquer lue</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
<?php if ($role === 1): ?>
    </section>
</section>
</main>
<?php else: ?>
    </section>
</main>
<?php endif; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
