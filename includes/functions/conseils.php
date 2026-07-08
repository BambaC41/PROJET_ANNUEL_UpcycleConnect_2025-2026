<?php

require_once __DIR__ . '/api_core.php';

function api_get_conseils() {
    return api_get('/conseils');
}

function api_get_conseil($id) {
    return api_get('/conseils/' . (int)$id);
}

function api_get_conseils_admin(array $query = []) {
    $endpoint = '/admin/conseils';
    if ($query !== []) {
        $endpoint .= '?' . http_build_query($query);
    }
    return api_get($endpoint, true);
}

function api_get_my_conseils() {
    return api_get('/me/conseils', true);
}

function api_create_conseil($payload) {
    return api_post('/conseils', $payload, true);
}

function api_update_conseil($id, $payload) {
    return api_put('/conseils/' . (int)$id, $payload, true);
}

function api_delete_conseil($id) {
    return api_delete('/conseils/' . (int)$id, true);
}


function conseils_admin_items_from_response(array $response): array
{
    $data = $response['data'] ?? null;
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['items']) && is_array($data['items'])) {
        return $data['items'];
    }
    return $data;
}


function conseils_admin_total_from_response(array $response): int
{
    $data = $response['data'] ?? null;
    if (is_array($data) && isset($data['total'])) {
        return (int)$data['total'];
    }
    return count(conseils_admin_items_from_response($response));
}


function salarie_conseils_merge_local(array $apiItems, int $userId): array
{
    require_once __DIR__ . '/local_db.php';
    $byId = [];
    foreach ($apiItems as $c) {
        $id = (int)($c['id_conseil'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $c;
        }
    }
    $local = (array)db_safe_exec(function (PDO $pdo) use ($userId) {
        $st = $pdo->prepare('SELECT id_conseil, titre, contenu, categorie, image_url, is_active, created_at, id_auteur FROM conseil WHERE id_auteur = ? ORDER BY id_conseil DESC');
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
    foreach ($local as $r) {
        $id = (int)($r['id_conseil'] ?? 0);
        if ($id > 0 && !isset($byId[$id])) {
            $byId[$id] = $r;
        }
    }
    $out = array_values($byId);
    usort($out, static fn($a, $b) => (int)($b['id_conseil'] ?? 0) <=> (int)($a['id_conseil'] ?? 0));
    return $out;
}
