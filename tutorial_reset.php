<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['token']) || (int)($_SESSION['role_id'] ?? 0) !== 2) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/functions/local_db.php';
$uid = session_ensure_user_id();
if ($uid > 0) {
    db_safe_exec(function (PDO $pdo) use ($uid) {
        $stmt = $pdo->prepare('UPDATE utilisateur SET tutorial_completed = 0 WHERE id_user = ?');
        $stmt->execute([$uid]);
    });
}
header('Location: particulier.php');
exit;
