<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/prestations.php';
require_once __DIR__ . '/includes/functions/events.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];
$prestations = api_get_prestations($token);

function normalize_datetime_local(string $raw): string
{
    $s = str_replace('T', ' ', trim($raw));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
        return $s . ':00';
    }
    return $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $dateDebut = normalize_datetime_local((string)($_POST['date_debut'] ?? ''));
    $dateFin = normalize_datetime_local((string)($_POST['date_fin'] ?? ''));
    $payload = [
        'id_prestation' => (int)($_POST['id_prestation'] ?? 0),
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin,
        'lieu' => trim((string)($_POST['lieu'] ?? '')),
        'capacite_max' => (int)($_POST['capacite_max'] ?? 0),
        'statut' => 'en_attente',
    ];
    $res = api_create_event($token, $payload);
    if (($res['status'] ?? 0) === 201) {
        notif_create($userId, 'event', 'Événement soumis', 'Votre événement a été soumis et est en attente de validation.');
        notif_notify_roles([1], 'event', 'Événement à valider', 'Un salarié a soumis un nouvel événement / session.');
        toast_redirect('salarie_events.php', 'success', 'Événement soumis en attente de validation.');
    }

    $localId = (int)db_safe_exec(function (PDO $pdo) use ($payload, $userId) {
        if ($payload['id_prestation'] <= 0 || $payload['capacite_max'] <= 0) {
            return 0;
        }
        $st = $pdo->prepare('INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, id_prestation, id_validateur, id_createur) VALUES (?, ?, ?, ?, "en_attente", ?, NULL, ?)');
        $st->execute([
            $payload['date_debut'],
            $payload['date_fin'],
            $payload['lieu'],
            $payload['capacite_max'],
            $payload['id_prestation'],
            $userId,
        ]);
        return (int)$pdo->lastInsertId();
    }, 0);

    if ($localId > 0) {
        notif_create($userId, 'event', 'Événement enregistré (local)', 'Session #' . $localId . ' créée en attente (hors API).');
        notif_notify_roles([1], 'event', 'Événement à valider', 'Événement créé localement par un salarié (#session ' . $localId . ').');
        toast_redirect('salarie_events.php', 'success', 'Événement soumis en attente de validation (enregistrement local).');
    }

    toast_redirect('salarie_events.php', 'error', 'Création impossible : ' . (string)($res['error'] ?? 'vérifiez les champs et la connexion API.'));
}

$events = salarie_events_for_user($token, $userId);
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));

$prestById = [];
foreach ($prestations as $p) {
    $prestById[(int)($p['id_prestation'] ?? 0)] = $p;
}

$filtered = [];
foreach ($events as $ev) {
    if ($status !== 'all' && ($ev['statut'] ?? '') !== $status) {
        continue;
    }
    $title = $prestById[(int)($ev['id_prestation'] ?? 0)]['titre'] ?? ($ev['prestation_titre'] ?? 'Session');
    $hay = strtolower($title . ' ' . ($ev['lieu'] ?? ''));
    if ($q !== '' && !str_contains($hay, strtolower($q))) {
        continue;
    }
    $ev['_title'] = $title;
    $filtered[] = $ev;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Salarie - Evenements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
    <link rel="stylesheet" href="styles/employee.css">
</head>
<body>
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="container" style="max-width:1100px;margin:20px auto;padding:0 16px;">
    <section class="hero-block soft" style="margin-top:18px;">
        <h1>🎓 Evenements (formations / ateliers)</h1>
        <p class="muted">Les événements sont créés en <strong>en_attente</strong> puis validés par un responsable.</p>
    </section>

    <section class="emp-card" style="margin-top:14px;">
        <h3>➕ Soumettre un nouvel evenement</h3>
        <form method="POST" class="row-actions" style="flex-wrap:wrap;gap:10px;">
            <input type="hidden" name="create_event" value="1">
            <select class="input" name="id_prestation" required style="min-width:260px;">
                <option value="">Choisir une prestation</option>
                <?php foreach ($prestations as $p): ?>
                    <option value="<?= e((int)($p['id_prestation'] ?? 0)) ?>"><?= e($p['titre'] ?? 'Prestation') ?> (<?= e($p['type'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="datetime-local" name="date_debut" required>
            <input class="input" type="datetime-local" name="date_fin" required>
            <input class="input" name="lieu" placeholder="Lieu" required>
            <input class="input" type="number" min="1" name="capacite_max" placeholder="Capacite" required style="width:130px;">
            <button class="btn-primary" type="submit">Soumettre</button>
        </form>
    </section>

    <section class="emp-card" style="margin-top:14px;">
        <h3>📋 Liste des evenements</h3>
        <form method="GET" class="row-actions" style="flex-wrap:wrap;">
            <input class="input" name="q" value="<?= e($q) ?>" placeholder="Recherche titre / lieu">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="valide" <?= $status === 'valide' ? 'selected' : '' ?>>Valide</option>
                <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="annule" <?= $status === 'annule' ? 'selected' : '' ?>>Annule</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>

        <table class="table" style="margin-top:10px;">
            <thead>
            <tr>
                <th>Prestation</th>
                <th>Debut</th>
                <th>Fin</th>
                <th>Lieu</th>
                <th>Capacité</th>
                <th>Statut</th>
                <th>Inscrits</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($filtered as $ev): ?>
                <tr>
                    <td><strong><?= e($ev['_title'] ?? 'Session') ?></strong></td>
                    <td><?= e(formatDateFr($ev['date_debut'] ?? '')) ?></td>
                    <td><?= e(formatDateFr($ev['date_fin'] ?? '')) ?></td>
                    <td><?= e($ev['lieu'] ?? '') ?></td>
                    <td><?= e((int)($ev['capacite_max'] ?? 0)) ?></td>
                    <td><?= e($ev['statut'] ?? '') ?></td>
                    <td><?= e((int)($ev['inscrits_count'] ?? 0)) ?>/<?= e((int)($ev['capacite_max'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($filtered) === 0): ?>
                <tr><td colspan="7" class="muted">Aucun evenement.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
