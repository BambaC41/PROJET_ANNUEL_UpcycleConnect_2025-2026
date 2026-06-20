<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/i18n.php';
set_lang_from_request();

require_once __DIR__ . '/functions/annonce.php';
require_once __DIR__ . '/functions/conteneur.php';
require_once __DIR__ . '/functions/inscriptions.php';
require_once __DIR__ . '/functions/paiements.php';
require_once __DIR__ . '/functions/api_core.php';
require_once __DIR__ . '/functions/view_context.php';

if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role_id'] ?? null) != 3) {
    $r = (int)($_SESSION['role_id'] ?? 0);
    if ($r === 2) {
        header('Location: particulier.php');
    } elseif ($r === 4) {
        header('Location: salarie.php');
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

function formatPriceEur($amount): string
{
    return number_format((float)$amount, 2, ',', ' ') . ' EUR';
}

function isUserPremiumDirect($userId) {
    global $pdo;
    if (!isset($pdo)) {
        require_once __DIR__ . '/functions/local_db.php';
        $pdo = getDbConnection();
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM abonnement_pro WHERE id_pro = ? AND statut = "actif" AND (date_fin IS NULL OR date_fin >= CURDATE())');
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}