<?php
require_once __DIR__ . '/api_core.php';

function api_get_users($token) {
    $response = callAPI('GET', '/users', $token);
    
    if ($response['status'] === 200) {
        return $response['data'];
    }
    return [];
}

function api_get_user_by_id($id, $token) {
    return callAPI('GET', '/users/' . $id, $token);
}
?>