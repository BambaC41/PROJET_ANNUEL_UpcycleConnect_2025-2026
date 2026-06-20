<?php
session_start();

if (empty($_SESSION['token']) || empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

require_once 'includes/functions/chat_local.php';
require_once 'includes/functions/view_context.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];
$query = trim((string)($_GET['q'] ?? ''));

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$results = chat_search_users($query, $userId);

foreach ($results as &$user) {
    if (!empty($user['photo_profil'])) {
        $user['photo_url'] = vc_media_url($user['photo_profil']);
    } else {
        $user['photo_url'] = '';
    }
    $user['role_label'] = match((int)$user['id_role']) {
        1 => 'Admin',
        3 => 'Pro',
        4 => 'Staff',
        default => ''
    };
    $user['role_color'] = match((int)$user['id_role']) {
        1 => '#f44336',
        3 => '#ff9800',
        4 => '#2196f3',
        default => ''
    };
}

echo json_encode($results);