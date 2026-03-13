<?php
require_once __DIR__ . '/api_core.php';

function api_update_user($token, $id, $data) {
    return callAPI('PUT', "/users/" . $id, $token, $data);
}

function api_delete_user($token, $id) {
    return callAPI('DELETE', "/users/" . $id, $token);
}

function api_admin_create_user($token, $data) {
    return callAPI('POST', "/admin/users", $token, $data);
}
?>