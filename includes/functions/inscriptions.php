<?php

require_once __DIR__ . '/api_core.php';

function api_register_event($sessionId) {
    return api_post('/events/' . (int)$sessionId . '/register', [], true);
}

function api_get_my_inscriptions() {
    return api_get('/me/inscriptions', true);
}

function api_cancel_inscription($inscriptionId) {
    return api_delete('/inscriptions/' . (int)$inscriptionId, true);
}
