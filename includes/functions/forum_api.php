<?php

require_once __DIR__ . '/api_core.php';

function api_forum_get($endpoint, array $query = []) {
    $path = '/forum/' . ltrim($endpoint, '/');
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    return api_get($path, true);
}

function api_forum_post($endpoint, $data = null) {
    return api_post('/forum/' . ltrim($endpoint, '/'), $data, true);
}

function api_forum_put($endpoint, $data = null) {
    return api_put('/forum/' . ltrim($endpoint, '/'), $data, true);
}

function api_forum_delete($endpoint) {
    return api_delete('/forum/' . ltrim($endpoint, '/'), true);
}

function api_admin_forum_get($endpoint, array $query = []) {
    $path = '/admin/forum/' . ltrim($endpoint, '/');
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    return api_get($path, true);
}

function api_admin_forum_put($endpoint, $data = null) {
    return api_put('/admin/forum/' . ltrim($endpoint, '/'), $data, true);
}

function forum_items_from_response(array $res): array {
    $data = $res['data'] ?? null;
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['items']) && is_array($data['items'])) {
        return $data['items'];
    }
    return $data;
}

function forum_api_error_message(array $res, string $fallback = 'Erreur API'): string {
    if (!empty($res['error'])) {
        return (string)$res['error'];
    }
    $code = (int)($res['status'] ?? 0);
    if ($code === 401) {
        return 'Session expirée — reconnectez-vous.';
    }
    if ($code === 403) {
        return 'Action non autorisée.';
    }
    if ($code === 404) {
        return 'Contenu introuvable.';
    }
    return $fallback . ($code > 0 ? ' (HTTP ' . $code . ')' : '');
}

function forum_topic_badges(?array $topic): array {
    if ($topic === null || $topic === []) {
        return [];
    }
    $badges = [];
    if (!empty($topic['is_pinned'])) {
        $badges[] = ['class' => 'badge-pinned', 'label' => '📌 Épinglé'];
    }
    if (!empty($topic['is_locked'])) {
        $badges[] = ['class' => 'badge-closed', 'label' => '🔒 Verrouillé'];
    }
    $status = (string)($topic['status'] ?? 'open');
    if ($status === 'closed') {
        $badges[] = ['class' => 'badge-closed', 'label' => '🔒 Fermé'];
    } elseif ($status === 'hidden' || !empty($topic['is_hidden'])) {
        $badges[] = ['class' => 'badge-reported', 'label' => '🙈 Masqué'];
    } else {
        $badges[] = ['class' => 'badge-open', 'label' => '💬 Ouvert'];
    }
    return $badges;
}