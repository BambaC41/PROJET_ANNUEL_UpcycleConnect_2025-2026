<?php
require_once __DIR__ . '/api_core.php';

function api_get_events($token) {
    $res = callAPI('GET', '/events', $token);
    return ($res['status'] === 200 && is_array($res['data'])) ? $res['data'] : [];
}

function api_get_my_events($token) {
    $res = callAPI('GET', '/me/events', $token);
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

/**
 * Ajoute les sessions créées en local (id_createur) absentes de la réponse API.
 *
 * @param list<array<string,mixed>> $apiEvents
 * @return list<array<string,mixed>>
 */
/**
 * Événements du salarié connecté (API /me/events + sessions locales orphelines).
 *
 * @return list<array<string,mixed>>
 */
function salarie_events_for_user(string $token, int $userId): array
{
    return salarie_events_merge_local(api_get_my_events($token), $userId);
}

function salarie_events_merge_local(array $apiEvents, int $userId): array
{
    require_once __DIR__ . '/local_db.php';
    $byId = [];
    foreach ($apiEvents as $e) {
        $id = (int)($e['id_session'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $e;
        }
    }
    $local = (array)db_safe_exec(function (PDO $pdo) use ($userId) {
        $st = $pdo->prepare('SELECT s.id_session, s.date_debut, s.date_fin, s.lieu, s.capacite_max, s.statut, s.id_prestation, COALESCE(p.titre, "") AS prestation_titre FROM session s LEFT JOIN prestation p ON p.id_prestation = s.id_prestation WHERE s.id_createur = ?');
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
    foreach ($local as $r) {
        $id = (int)($r['id_session'] ?? 0);
        if ($id > 0 && !isset($byId[$id])) {
            $byId[$id] = [
                'id_session' => $id,
                'date_debut' => $r['date_debut'],
                'date_fin' => $r['date_fin'],
                'lieu' => $r['lieu'],
                'capacite_max' => (int)($r['capacite_max'] ?? 0),
                'statut' => $r['statut'],
                'id_prestation' => (int)($r['id_prestation'] ?? 0),
                'prestation_titre' => $r['prestation_titre'],
                'inscrits_count' => 0,
            ];
        }
    }
    $out = array_values($byId);
    usort($out, static function ($a, $b) {
        return strcmp((string)($b['date_debut'] ?? ''), (string)($a['date_debut'] ?? ''));
    });
    return $out;
}
?>