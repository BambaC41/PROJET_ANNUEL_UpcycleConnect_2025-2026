<?php
require_once 'includes/pro_bootstrap.php';
$inscriptions = api_get_my_inscriptions()['data'] ?? [];
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

$blocks = [];
foreach ($inscriptions as $ins) {
    if ($statusFilter !== 'all' && ($ins['statut'] ?? '') !== $statusFilter) continue;
    $startTs = strtotime((string)($ins['date_debut'] ?? ''));
    $endTs = strtotime((string)($ins['date_fin'] ?? ''));
    if ($startTs === false || $endTs === false || $endTs <= $startTs) continue;
    if ($startTs < $weekStart || $startTs >= $weekEnd) continue;
    $dayIdx = (int)date('N', $startTs) - 1;
    if ($dayIdx < 0 || $dayIdx > 6) continue;
    $startMin = ((int)date('G', $startTs) * 60 + (int)date('i', $startTs));
    $endMin = ((int)date('G', $endTs) * 60 + (int)date('i', $endTs));
    $gridStart = max(0, (int)floor(($startMin - 420) / 30));
    $gridSpan = max(1, (int)ceil(($endMin - $startMin) / 30));
    $blocks[] = [
        'day' => $dayIdx,
        'start' => $gridStart,
        'span' => $gridSpan,
        'time' => date('H:i', $startTs) . ' - ' . date('H:i', $endTs),
        'title' => $ins['prestation_titre'] ?? 'Session',
        'lieu' => $ins['lieu'] ?? 'Lieu'
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/calendar.css">
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>Planning hebdomadaire (style emploi du temps)</h1>
        <form method="GET" class="row-actions">
            <input type="hidden" name="week" value="<?= e($offset - 1) ?>">
            <button class="btn-outline" type="submit">⬅ Semaine précédente</button>
        </form>
        <form method="GET" class="row-actions" style="margin-top:-42px;justify-content:flex-end;">
            <input type="hidden" name="week" value="<?= e($offset + 1) ?>">
            <button class="btn-outline" type="submit">Semaine suivante ➡</button>
        </form>
        <form method="GET" class="row-actions">
            <input type="hidden" name="week" value="<?= e($offset) ?>">
            <select class="input" name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="confirmee" <?= $statusFilter === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                <option value="annulee" <?= $statusFilter === 'annulee' ? 'selected' : '' ?>>Annulée</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        <div class="schedule">
            <div class="schedule-grid">
                <div class="schedule-time-head"></div>
                <?php $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']; ?>
                <?php foreach ($weekDays as $idx => $day): ?>
                    <div class="schedule-day-head"><?= e($dayNames[$idx] . '. ' . date('d/m/y', $day)) ?></div>
                <?php endforeach; ?>
                <?php for ($slot = 0; $slot < 30; $slot++): ?>
                    <?php $timeLabel = date('H:i', strtotime('07:00 +' . ($slot * 30) . ' minutes')); ?>
                    <div class="schedule-time"><?= e($timeLabel) ?></div>
                    <?php for ($d = 0; $d < 7; $d++): ?>
                        <div class="schedule-cell"></div>
                    <?php endfor; ?>
                <?php endfor; ?>
                <?php foreach ($blocks as $b): ?>
                    <div class="schedule-event" style="left: calc(70px + (100% - 70px) / 7 * <?= e($b['day']) ?> + 4px); width: calc((100% - 70px) / 7 - 8px); top: calc(40px + 24px * <?= e($b['start']) ?> + 2px); height: calc(24px * <?= e($b['span']) ?> - 4px);">
                        <strong><?= e($b['time']) ?></strong>
                        <?= e($b['title']) ?><br><?= e($b['lieu']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
</body>
</html>
