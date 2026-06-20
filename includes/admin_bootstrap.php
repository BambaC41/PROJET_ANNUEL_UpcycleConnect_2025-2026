<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/functions/api_core.php';
require_once __DIR__ . '/functions/prestations.php';
require_once __DIR__ . '/functions/events.php';
require_once __DIR__ . '/functions/conteneur.php';
require_once __DIR__ . '/functions/paiements.php';
require_once __DIR__ . '/functions/annonce.php';
require_once __DIR__ . '/functions/conseils.php';
require_once __DIR__ . '/functions/forum_api.php';
require_once __DIR__ . '/functions/view_context.php';
require_once __DIR__ . '/functions/local_db.php';
require_once __DIR__ . '/functions/bootstrap_notify.php';
require_once __DIR__ . '/ui_helpers.php';
require_once __DIR__ . '/functions/session.php';

set_lang_from_request();

if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}
if (($_SESSION['role_id'] ?? 0) != 1) {
    header("Location: login.php");
    exit();
}

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string {
        if (empty($date)) {
            return 'Non renseignée';
        }
        $t = strtotime($date);
        if ($t === false) {
            return (string)$date;
        }
        return date('d/m/Y H:i', $t);
    }
}

if (!function_exists('formatPriceEur')) {
    function formatPriceEur(float $price): string {
        return number_format($price, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('vc_media_url')) {
    function vc_media_url(?string $url): string {
        if (empty($url)) return '';
       
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
       
        return $url;
    }
}

if (function_exists('session_ensure_user_id') && session_ensure_user_id() > 0) {
    if (function_exists('bootstrap_flash_unread_notifications')) {
        bootstrap_flash_unread_notifications();
    }
}
?>