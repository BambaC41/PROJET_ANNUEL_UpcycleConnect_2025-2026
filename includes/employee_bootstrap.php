<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/i18n.php';
set_lang_from_request();

require_once __DIR__ . '/functions/api_core.php';
require_once __DIR__ . '/functions/events.php';
require_once __DIR__ . '/functions/conseils.php';
require_once __DIR__ . '/functions/forum_api.php';
require_once __DIR__ . '/functions/view_context.php';

if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role_id'] ?? null) != 4) {
    $r = (int)($_SESSION['role_id'] ?? 0);
    if ($r === 2) {
        header('Location: particulier.php');
    } elseif ($r === 3) {
        header('Location: pro.php');
    } elseif ($r === 1) {
        header('Location: admin.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

require_once __DIR__ . '/functions/local_db.php';
require_once __DIR__ . '/functions/bootstrap_notify.php';
if (session_ensure_user_id() <= 0) {
    header('Location: login.php');
    exit;
}
bootstrap_flash_unread_notifications();

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string
    {
        if (empty($date)) {
            return 'Non renseigne';
        }
        $t = strtotime($date);
        if ($t === false) return (string)$date;
        return date('d/m/Y H:i', $t);
    }
}

function toast_redirect(string $url, string $type, string $message): void
{
    $_SESSION['flash_toast'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $url);
    exit;
}

if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO {
        $host = 'localhost';
        $dbname = 'upcycleconnect';
        $user = 'root';
        $pass = 'root';
        return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    }
}
?>
