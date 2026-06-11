<?php

require_once __DIR__ . '/api_core.php';

// ============================================
// FONCTIONS API ANNONCES
// ============================================

function api_get_annonces() {
    return api_get('/annonces');
}

function api_get_my_annonces() {
    return api_get('/me/annonces', true);
}

function api_get_annonce($id) {
    return api_get('/annonces/' . (int)$id, true);
}

function api_create_annonce($data) {
    return api_post('/annonces', $data, true);
}

function api_update_annonce($id, $data) {
    return api_put('/annonces/' . (int)$id, $data, true);
}

function api_delete_annonce($id) {
    return api_delete('/annonces/' . (int)$id, true);
}

function api_get_pending_annonces() {
    return api_get('/admin/annonces/pending', true);
}

function api_moderate_annonce($id, $statut) {
    return api_put('/annonces/' . (int)$id . '/validate', ['statut' => $statut], true);
}

// formatPriceEur() et formatDateFr() sont déjà dans particulier_bootstrap.php
// Ne pas les redéclarer ici

?>