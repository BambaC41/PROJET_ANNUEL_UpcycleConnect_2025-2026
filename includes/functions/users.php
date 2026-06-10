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

function api_update_user($token, $id, $payload) {
    return callAPI("PUT", "/users/$id", $token, $payload);
}

function api_update_user_role($token, $id, $role) {
    $userResponse = api_get_user_by_id($id, $token);
    if (($userResponse['status'] ?? 0) !== 200 || !is_array($userResponse['data'] ?? null)) {
        return $userResponse;
    }

    $current = $userResponse['data'];
    $payload = [
        'email' => $current['email'] ?? '',
        'pseudo' => $current['pseudo'] ?? '',
        'prenom' => $current['prenom'] ?? '',
        'nom' => $current['nom'] ?? '',
        'telephone' => $current['telephone'] ?? '',
        'adresse_rue' => $current['adresse_rue'] ?? '',
        'adresse_ville' => $current['adresse_ville'] ?? '',
        'adresse_code_postal' => $current['adresse_code_postal'] ?? '',
        'adresse_pays' => $current['adresse_pays'] ?? '',
        'photo_profil' => $current['photo_profil'] ?? '',
        'bio' => $current['bio'] ?? '',
        'statut' => $current['statut'] ?? 'actif',
        'id_role' => (int)$role,
        'is_approved' => $current['is_approved'] ?? true
    ];

    return api_update_user($token, $id, $payload);
}

function api_ban_user($token, $id, $reason, $until) {
    return callAPI("PUT", "/users/$id/ban", $token, [
        "ban_reason" => $reason,
        "ban_until" => $until
    ]);
}

function api_unban_user($token, $id) {
    return callAPI("PUT", "/users/$id/unban", $token);
}

function api_toggle_ban_user($token, $id, $isCurrentlyBanned = false) {
    if ($isCurrentlyBanned) {
        return api_unban_user($token, $id);
    }

    $banUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
    return api_ban_user($token, $id, 'Bannissement administratif', $banUntil);
}

function api_delete_user($token, $id) {
    return callAPI("DELETE", "/users/$id", $token);
}

function api_get_pending_pros($token) {
    return callAPI("GET", "/pros/pending", $token);
}

function api_approve_pro($token, $id) {
    return callAPI("PUT", "/users/$id/approve", $token);
}
?>