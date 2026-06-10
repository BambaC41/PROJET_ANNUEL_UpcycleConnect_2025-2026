<?php

require_once __DIR__ . '/api_core.php';

function api_pay_inscription($inscriptionId, $cardData = []) {
    return api_post('/inscriptions/' . (int)$inscriptionId . '/pay', $cardData, true);
}

function api_get_my_paiements() {
    return api_get('/me/paiements', true);
}

function api_get_all_paiements() {
    return api_get('/paiements', true);
}
