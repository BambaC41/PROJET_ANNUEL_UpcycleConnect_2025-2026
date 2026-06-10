<?php
declare(strict_types=1);

function bootstrap_flash_unread_notifications(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['prompt_unread_notifs'])) {
        return;
    }
    unset($_SESSION['prompt_unread_notifs']);
    if (!empty($_SESSION['flash_toast'])) {
        return;
    }
    require_once __DIR__ . '/../notifications.php';
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }
    $n = notif_unread_count($uid);
    if ($n > 0) {
        $_SESSION['flash_toast'] = [
            'type' => 'info',
            'message' => "Vous avez {$n} notification(s) non lue(s). Consultez la page Notifications.",
        ];
    }
}
