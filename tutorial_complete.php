<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['token'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session non authentifiée (token manquant).']);
    exit;
}

$roleRaw = $_SESSION['role_id'] ?? null;
$roleId = is_numeric($roleRaw) ? (int)$roleRaw : 0;
if ($roleId !== 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Rôle non autorisé pour ce tutoriel (attendu: particulier).']);
    exit;
}

require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';

$uid = session_ensure_user_id();
if ($uid <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Impossible de résoudre id utilisateur (/me ou session).']);
    exit;
}

$dbError = null;
$alreadyDone = false;
$justCompleted = false;
try {
    $pdo = db_pdo();
    $q = $pdo->prepare('SELECT tutorial_completed FROM utilisateur WHERE id_user = ?');
    $q->execute([$uid]);
    $cur = $q->fetchColumn();
    if ($cur === false) {
        $dbError = 'Utilisateur introuvable en base.';
    } elseif ((int)$cur === 1) {
        $alreadyDone = true;
    } else {
        $stmt = $pdo->prepare('UPDATE utilisateur SET tutorial_completed = 1 WHERE id_user = ? AND COALESCE(tutorial_completed, 0) <> 1');
        $stmt->execute([$uid]);
        $justCompleted = $stmt->rowCount() > 0;
        if (!$justCompleted) {
            $dbError = 'Impossible de valider le tutoriel (conflit ou lecture concurrente).';
        }
    }
} catch (Throwable $e) {
    error_log('Tutorial completion error for user ' . $uid . ': ' . $e->getMessage(), 0);
    $dbError = 'Erreur de connexion à la base de données. Vérifiez la configuration MySQL.';
}

if ($dbError !== null) {
    echo json_encode(['ok' => false, 'error' => $dbError]);
    exit;
}

if ($justCompleted) {
    notif_create($uid, 'system', 'Tutoriel terminé', 'Vous avez terminé la prise en main de votre espace particulier.');
    notif_notify_roles([1], 'tutoriel', 'Tutoriel particulier complété', 'Un particulier a terminé le tutoriel (compte #' . $uid . ').');
}

echo json_encode(['ok' => true, 'already_done' => $alreadyDone]);
