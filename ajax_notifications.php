<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
header('Content-Type: application/json');

if ($userId <= 0) {
    echo json_encode(['notifications' => []]);
    exit;
}

$notifications = notif_list($userId, 50, 0);
$result = [];

foreach ($notifications as $n) {
    $result[] = [
        'id' => (int)$n['id_notification'],
        'titre' => $n['titre'] ?? '',
        'contenu' => mb_substr($n['contenu'] ?? '', 0, 150),
        'date' => formatDateFr($n['created_at'] ?? ''),
        'is_read' => !empty($n['is_read'])
    ];
}

echo json_encode(['notifications' => $result]);
?>