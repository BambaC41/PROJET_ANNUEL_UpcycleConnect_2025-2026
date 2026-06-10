<?php

require_once __DIR__ . '/api_core.php';

function api_get_conteneurs() {
    return api_get('/conteneurs');
}

function api_create_conteneur($data) {
    return api_post('/conteneurs', $data, true);
}

function api_update_conteneur($id, $data) {
    return api_put('/conteneurs/' . (int)$id, $data, true);
}

function api_delete_conteneur_admin($id) {
    return api_delete('/conteneurs/' . (int)$id, true);
}

function api_get_my_demandes_depot() {
    return api_get('/me/demandes-depot', true);
}

function api_create_demande_depot($data) {
    return api_post('/demandes-depot', $data, true);
}

function api_get_demande_depot($id) {
    return api_get('/demandes-depot/' . $id, true);
}

function api_get_demande_codes($id) {
    return api_get('/demandes-depot/' . $id . '/codes', true);
}

function api_delete_demande_depot($id) {
    return api_delete('/demandes-depot/' . $id, true);
}
function api_get_all_demandes_depot() {
    return api_get('/demandes-depot', true);
}

function api_validate_demande_depot($id) {
    return api_put('/demandes-depot/' . $id . '/valider', [], true);
}