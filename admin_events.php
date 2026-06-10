<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/events.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'], $_POST['event_status'])) {
    $eventId = (int)$_POST['event_id'];
    $eventStatus = trim((string)$_POST['event_status']);
    $allowed = ['en_attente', 'valide', 'rejete', 'annule'];
    if (!in_array($eventStatus, $allowed, true)) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Statut invalide.'];
    } else {
        $rows = (int)db_safe_exec(static function (PDO $pdo) use ($eventId, $eventStatus): int {
            $stmt = $pdo->prepare('UPDATE session SET statut = ? WHERE id_session = ?');
            $stmt->execute([$eventStatus, $eventId]);
            return $stmt->rowCount();
        }, 0);

        $current = api_get_event($_SESSION['token'], $eventId);
        if (($current['status'] ?? 0) === 200 && is_array($current['data'] ?? null)) {
            $payload = $current['data'];
            $payload['statut'] = $eventStatus;
            api_update_event($_SESSION['token'], $eventId, $payload);
        }

        if ($rows > 0) {
            db_safe_exec(static function (PDO $pdo) use ($eventId, $eventStatus): void {
                $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "event", ?, ?, NOW())');
                $audit->execute([(int)($_SESSION['user_id'] ?? 0), strtoupper($eventStatus) . '_EVENT', $eventId, 'Moderation evenement']);
            }, null);
            $creatorId = (int)db_safe_exec(static function (PDO $pdo) use ($eventId): int {
                $stmt = $pdo->prepare('SELECT id_createur FROM session WHERE id_session = ?');
                $stmt->execute([$eventId]);
                return (int)$stmt->fetchColumn();
            }, 0);
            if ($creatorId > 0) {
                notif_create($creatorId, 'evenement', 'Événement modéré', 'Statut de votre événement #' . $eventId . ' : ' . $eventStatus);
            }
            if (in_array($eventStatus, ['rejete', 'annule'], true)) {
                $uids = (array)db_safe_exec(static function (PDO $pdo) use ($eventId) {
                    $stmt = $pdo->prepare('SELECT DISTINCT id_user FROM inscription WHERE id_session = ?');
                    $stmt->execute([$eventId]);
                    return $stmt->fetchAll(PDO::FETCH_COLUMN);
                }, []);
                foreach ($uids as $uid) {
                    $uid = (int)$uid;
                    if ($uid > 0) {
                        notif_create($uid, 'evenement', 'Session mise à jour', 'La session #' . $eventId . ' : ' . $eventStatus . '.');
                    }
                }
            }
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Statut événement mis à jour (' . $eventStatus . ').'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Aucune session modifiée (id #' . $eventId . ' introuvable en base).'];
        }
    }
    header('Location: admin_events.php?' . http_build_query(['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? 'all', 'sort' => $_GET['sort'] ?? 'date']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $eventId = (int)$_POST['delete_event_id'];
    $insCount = (int)db_safe_exec(function (PDO $pdo) use ($eventId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscription WHERE id_session = ?');
        $stmt->execute([$eventId]);
        return (int)$stmt->fetchColumn();
    }, 0);
    if ($insCount > 0) {
        $current = api_get_event($_SESSION['token'], $eventId);
        if (($current['status'] ?? 0) === 200 && is_array($current['data'] ?? null)) {
            $payload = $current['data'];
            $payload['statut'] = 'annule';
            api_update_event($_SESSION['token'], $eventId, $payload);
        }
        $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Événement annulé (inscriptions présentes — suppression impossible).'];
    } else {
        $res = api_delete_event($_SESSION['token'], $eventId);
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
            ? ['type' => 'success', 'message' => 'Événement supprimé.']
            : ['type' => 'error', 'message' => 'Suppression impossible (API).'];
    }
    header('Location: admin_events.php');
    exit;
}

$events = api_get_events($_SESSION['token']);
$prestations = api_get_prestations($_SESSION['token']);
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$status = trim((string)($_GET['status'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'date'));
$creatorFilter = trim((string)($_GET['creator'] ?? ''));

$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? 'N/A';
}

$userPseudo = [];
$dbUsers = (array)db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT id_user, pseudo FROM utilisateur')->fetchAll(PDO::FETCH_ASSOC), []);
foreach ($dbUsers as $ru) {
    $userPseudo[(int)$ru['id_user']] = (string)($ru['pseudo'] ?? '');
}

$filtered = array_values(array_filter($events, function ($ev) use ($prestationsMap, $q, $status, $creatorFilter, $userPseudo) {
    $title = mb_strtolower((string)($prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? ''));
    $lieu = mb_strtolower((string)($ev['lieu'] ?? ''));
    $okQ = ($q === '') || str_contains($title, $q) || str_contains($lieu, $q);
    $okS = ($status === 'all') || (($ev['statut'] ?? '') === $status);
    $cid = (int)($ev['id_createur'] ?? 0);
    $pseudo = mb_strtolower((string)($userPseudo[$cid] ?? ''));
    $okC = ($creatorFilter === '') || str_contains($pseudo, mb_strtolower($creatorFilter)) || (string)$cid === $creatorFilter;
    return $okQ && $okS && $okC;
}));

usort($filtered, static function ($a, $b) use ($sort, $prestationsMap) {
    if ($sort === 'statut') {
        return strcmp((string)($a['statut'] ?? ''), (string)($b['statut'] ?? ''));
    }
    if ($sort === 'prestation') {
        $ta = (string)($prestationsMap[(int)($a['id_prestation'] ?? 0)] ?? '');
        $tb = (string)($prestationsMap[(int)($b['id_prestation'] ?? 0)] ?? '');
        return strcmp($ta, $tb);
    }
    return strcmp((string)($b['date_debut'] ?? ''), (string)($a['date_debut'] ?? ''));
});

function eventImage(string $title): string {
    $t = mb_strtolower($title);
    if (str_contains($t, 'velo')) {
        return 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=1200&q=80';
    }
    if (str_contains($t, 'couture') || str_contains($t, 'textile')) {
        return 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1200&q=80';
    }
    return 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=1200&q=80';
}

function event_status_badge_class(string $st): string
{
    return match ($st) {
        'en_attente' => 'status-warn',
        'valide' => 'status-ok',
        'annule' => 'status-muted',
        'rejete' => 'status-danger',
        default => 'status-info',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>Gestion des événements</h1>
        <form method="GET" class="row-actions" style="flex-wrap:wrap;">
            <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Prestation / lieu…">
            <input class="input" type="search" name="creator" value="<?= e($creatorFilter) ?>" placeholder="ID ou pseudo créateur">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="valide" <?= $status === 'valide' ? 'selected' : '' ?>>Validé</option>
                <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="annule" <?= $status === 'annule' ? 'selected' : '' ?>>Annulé</option>
                <option value="rejete" <?= $status === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
            </select>
            <select class="input" name="sort">
                <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Tri : date</option>
                <option value="statut" <?= $sort === 'statut' ? 'selected' : '' ?>>Tri : statut</option>
                <option value="prestation" <?= $sort === 'prestation' ? 'selected' : '' ?>>Tri : prestation</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Image</th><th>ID</th><th>Prestation</th><th>Lieu</th><th>Début</th><th>Fin</th><th>Statut</th><th>Créateur</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($filtered as $ev): ?>
                <?php $title = $prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? 'N/A'; ?>
                <?php $st = (string)($ev['statut'] ?? ''); ?>
                <tr>
                    <td><img src="<?= e(eventImage($title)) ?>" alt="Event" style="width:62px;height:42px;border-radius:8px;object-fit:cover;"></td>
                    <td><?= e($ev['id_session'] ?? '') ?></td>
                    <td><?= e($title) ?></td>
                    <td><?= e($ev['lieu'] ?? '') ?></td>
                    <td><?= e(formatDateFr($ev['date_debut'] ?? '')) ?></td>
                    <td><?= e(formatDateFr($ev['date_fin'] ?? '')) ?></td>
                    <td><span class="status-badge <?= e(event_status_badge_class($st)) ?>"><?= e($st) ?></span></td>
                    <td><?php $cid = (int)($ev['id_createur'] ?? 0); ?><?= $cid ? e('#' . $cid . ' ' . ($userPseudo[$cid] ?? '')) : '—' ?></td>
                    <td>
                        <a class="btn-outline" href="admin_event_edit.php?id=<?= (int)($ev['id_session'] ?? 0) ?>">Modifier</a>
                        <form method="POST" class="row-actions" style="display:inline-flex;flex-wrap:wrap;">
                            <input type="hidden" name="event_id" value="<?= (int)($ev['id_session'] ?? 0) ?>">
                            <select class="input" name="event_status">
                                <option value="valide">valide</option>
                                <option value="en_attente">en_attente</option>
                                <option value="rejete">rejete</option>
                                <option value="annule">annule</option>
                            </select>
                            <button class="btn-outline" type="submit">Appliquer</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ou annuler cet événement ?');">
                            <input type="hidden" name="delete_event_id" value="<?= (int)($ev['id_session'] ?? 0) ?>">
                            <button class="btn-danger" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>
