<?php
require_once __DIR__ . '/api_core.php';

function api_get_prestations($token) {
    $res = callAPI('GET', '/prestations', $token);
    return ($res['status'] === 200 && is_array($res['data'])) ? $res['data'] : [];
}

function api_create_prestation($token, $data) {
    return callAPI('POST', '/prestations', $token, $data);
}

function api_update_prestation($token, $id, $data) {
    return callAPI('PUT', '/prestations/' . $id, $token, $data);
}

function api_delete_prestation($token, $id) {
    return callAPI('DELETE', '/prestations/' . $id, $token);
}
?>