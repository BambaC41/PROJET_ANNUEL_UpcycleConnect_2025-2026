<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/prestations.php';
require_once __DIR__ . '/includes/functions/events.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];
$events = salarie_events_for_user($token, $userId);
$prestations = api_get_prestations($token);
$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? 'N/A';
}

$offset = (int)($_GET['week'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$baseMonday = strtotime('monday this week');
$monday = strtotime(($offset >= 0 ? '+' : '') . $offset . ' week', $baseMonday);
$weekStart = strtotime(date('Y-m-d 00:00:00', $monday));
$weekEnd = strtotime('+7 day', $weekStart);
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = strtotime("+$i day", $weekStart);
}
$dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$weekLabel = 'Semaine du ' . date('d/m/Y', $weekStart) . ' au ' . date('d/m/Y', strtotime('+6 day', $weekStart));

$blocks = [];
foreach ($events as $ev) {
    if ($statusFilter !== 'all' && ($ev['statut'] ?? '') !== $statusFilter) {
        continue;
    }
    $startTs = strtotime((string)($ev['date_debut'] ?? ''));
    $endTs = strtotime((string)($ev['date_fin'] ?? ''));
    if ($startTs === false || $endTs === false || $endTs <= $startTs) {
        continue;
    }
    if ($startTs < $weekStart || $startTs >= $weekEnd) {
        continue;
    }
    $dayIdx = (int)date('N', $startTs) - 1;
    if ($dayIdx < 0 || $dayIdx > 6) {
        continue;
    }

    $startMin = ((int)date('G', $startTs) * 60 + (int)date('i', $startTs));
    $endMin = ((int)date('G', $endTs) * 60 + (int)date('i', $endTs));
    $gridStart = max(0, (int)floor(($startMin - 420) / 30));
    $gridSpan = max(1, (int)ceil(($endMin - $startMin) / 30));

    $blocks[] = [
        'day' => $dayIdx,
        'start' => $gridStart,
        'span' => $gridSpan,
        'time' => date('H:i', $startTs) . ' - ' . date('H:i', $endTs),
        'title' => $prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? ($ev['prestation_titre'] ?? 'Session'),
        'lieu' => $ev['lieu'] ?? 'Lieu',
        'statut' => $ev['statut'] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning salaries</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
    <link rel="stylesheet" href="styles/calendar.css">
    <link rel="stylesheet" href="styles/employee.css">
    <style>
        .schedule-day-head, .schedule-time-head, .schedule-time { background: #16a34a; }
        .schedule-event { background:#dcfce7; border-color:#86efac; }
        .schedule-event .pill { margin-top:4px; display:inline-flex; }
        .week-nav-salarie { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .week-nav-salarie .week-title { font-weight:700; color:#166534; text-align:center; flex:1 1 200px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="container" style="max-width:1200px;margin:20px auto;padding:0 16px;">
    <section class="hero-block soft" style="margin-top:18px;">
        <h1>🗓️ Planning hebdomadaire</h1>
        <p class="muted">Navigation semaine par semaine, avec filtre par statut.</p>
    </section>

    <section class="emp-card" style="margin-top:14px;">
        <div class="week-nav-salarie">
            <form method="GET" style="margin:0;">
                <input type="hidden" name="week" value="<?= e($offset - 1) ?>">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <button class="btn-outline" type="submit">⬅ Semaine précédente</button>
            </form>
            <div class="week-title"><?= e($weekLabel) ?></div>
            <form method="GET" style="margin:0;">
                <input type="hidden" name="week" value="<?= e($offset + 1) ?>">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <button class="btn-outline" type="submit">Semaine suivante ➡</button>
            </form>
        </div>
        <form method="GET" class="row-actions" style="flex-wrap:wrap;margin-bottom:12px;">
            <input type="hidden" name="week" value="<?= e($offset) ?>">
            <select class="input" name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="valide" <?= $statusFilter === 'valide' ? 'selected' : '' ?>>Valide</option>
                <option value="en_attente" <?= $statusFilter === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="annule" <?= $statusFilter === 'annule' ? 'selected' : '' ?>>Annulé</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>

        <div class="schedule" style="margin-top:10px;">
            <div class="schedule-grid">
                <div class="schedule-time-head"></div>
                <?php foreach ($weekDays as $idx => $day): ?>
                    <div class="schedule-day-head"><?= e($dayNames[$idx] . '. ' . date('d/m/y', $day)) ?></div>
                <?php endforeach; ?>
                <?php for ($slot = 0; $slot < 30; $slot++): ?>
                    <?php $timeLabel = date('H:i', strtotime('07:00 +' . ($slot * 30) . ' minutes')); ?>
                    <div class="schedule-time"><?= e($timeLabel) ?></div>
                    <?php for ($d = 0; $d < 7; $d++): ?><div class="schedule-cell"></div><?php endfor; ?>
                <?php endfor; ?>

                <?php foreach ($blocks as $b): ?>
                    <div class="schedule-event" style="left: calc(70px + (100% - 70px) / 7 * <?= e($b['day']) ?> + 4px); width: calc((100% - 70px) / 7 - 8px); top: calc(40px + 24px * <?= e($b['start']) ?> + 2px); height: calc(24px * <?= e($b['span']) ?> - 4px);">
                        <strong><?= e($b['time']) ?></strong>
                        <?= e($b['title']) ?><br><?= e($b['lieu']) ?><br>
                        <span class="pill pill-green"><?= e($b['statut']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
</body>
</html>
