<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/chat_local.php';

header('Content-Type: application/json');

$messageId = (int)($_POST['message_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($messageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ID manquant']);
    exit;
}

$result = chat_delete_message($messageId, $userId);
if (isset($result['error'])) {
    http_response_code(403);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true]);