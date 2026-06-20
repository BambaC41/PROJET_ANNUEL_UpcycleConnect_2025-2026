<?php
require_once 'includes/particulier_bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier manquant ou erreur lors de l\'upload']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Type de fichier non autorisé']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/chat/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$userId = (int)$_SESSION['user_id'];
$filename = $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de sauvegarder le fichier']);
    exit;
}

echo json_encode([
    'file_path' => '/upcycle/uploads/chat/' . $filename,
    'file_name' => $file['name']
]);