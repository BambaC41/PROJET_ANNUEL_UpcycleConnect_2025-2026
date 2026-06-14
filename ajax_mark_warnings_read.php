<?php
session_start();
require_once 'includes/functions/local_db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    db_safe_exec(function (PDO $pdo) use ($userId) {
        $st = $pdo->prepare('UPDATE user_warnings SET is_read = 1 WHERE user_id = ?');
        $st->execute([$userId]);
        return true;
    }, false);
}
echo json_encode(['success' => true]);