<?php
require_once __DIR__ . '/api_core.php';

function api_get_events($token) {
    $res = callAPI('GET', '/events', $token);
    return ($res['status'] === 200 && is_array($res['data'])) ? $res['data'] : [];
}

function api_get_event($token, $id) {
    return callAPI('GET', '/events/' . $id, $token);
}

function api_create_event($token, $data) {
    return callAPI('POST', '/events', $token, $data);
}

function api_update_event($token, $id, $data) {
    return callAPI('PUT', '/events/' . $id, $token, $data);
}

function api_delete_event($token, $id) {
    return callAPI('DELETE', '/events/' . $id, $token);
}
?>