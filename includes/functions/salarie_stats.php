<?php
declare(strict_types=1);

require_once __DIR__ . '/local_db.php';
require_once __DIR__ . '/events.php';

function salarie_dashboard_stats(string $token, int $userId): array
{
    $events = salarie_events_for_user($token, $userId);
    if (empty($events)) {
        $events = (array)db_safe_exec(static function (PDO $pdo) use ($userId): array {
            $st = $pdo->prepare('SELECT s.id_session, s.date_debut, s.date_fin, s.lieu, s.capacite_max, s.statut, s.id_prestation,
                COALESCE(p.titre, "") AS prestation_titre
                FROM session s
                LEFT JOIN prestation p ON p.id_prestation = s.id_prestation
                WHERE s.id_createur = ?
                ORDER BY s.date_debut DESC');
            $st->execute([$userId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id_session' => (int)$r['id_session'],
                    'date_debut' => $r['date_debut'],
                    'date_fin' => $r['date_fin'],
                    'lieu' => $r['lieu'],
                    'statut' => $r['statut'],
                    'prestation_titre' => $r['prestation_titre'],
                ];
            }
            return $out;
        }, []);
    }

    $countBy = static function (array $list, string $statut): int {
        return count(array_filter($list, static fn($e) => ($e['statut'] ?? '') === $statut));
    };

    $conseilDraft = 0;
    $conseilPublished = 0;
    db_safe_exec(static function (PDO $pdo) use ($userId, &$conseilDraft, &$conseilPublished): void {
        $st = $pdo->prepare('SELECT COUNT(*) FROM conseil WHERE id_auteur = ? AND is_active = 0');
        $st->execute([$userId]);
        $conseilDraft = (int)$st->fetchColumn();
        $st2 = $pdo->prepare('SELECT COUNT(*) FROM conseil WHERE id_auteur = ? AND is_active = 1');
        $st2->execute([$userId]);
        $conseilPublished = (int)$st2->fetchColumn();
    }, null);

    return [
        'events' => $events,
        'total_events' => count($events),
        'pending' => $countBy($events, 'en_attente'),
        'validated' => $countBy($events, 'valide'),
        'rejected' => $countBy($events, 'rejete'),
        'cancelled' => $countBy($events, 'annule'),
        'conseil_draft' => $conseilDraft,
        'conseil_published' => $conseilPublished,
    ];
}
