<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/prestations.php';
require_once __DIR__ . '/includes/functions/events.php';
require_once __DIR__ . '/includes/functions/local_db.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];
$events = salarie_events_for_user($token, $userId);
$prestations = api_get_prestations($token);

$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? 'N/A';
}

$viewMode = $_GET['view'] ?? 'week'; 
$offset = (int)($_GET['offset'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$lieuFilter = $_GET['lieu'] ?? 'all';

$salles = [
    '10e - Siège social' => '174 rue La Fayette, 75010 Paris',
    '11e - Annexe' => '11e arrondissement, Paris',
    '13e - Annexe' => '13e arrondissement, Paris',
    '16e - Annexe' => '16e arrondissement, Paris',
    'Bourg-la-Reine' => 'Bourg-la-Reine, 92340',
    'Ivry' => 'Ivry-sur-Seine, 94200',
    'Montreuil' => 'Montreuil, 93100',
];

if ($viewMode === 'month') {
    $currentMonth = strtotime(($offset >= 0 ? '+' : '') . $offset . ' months', strtotime('first day of this month'));
    $startDate = strtotime(date('Y-m-01', $currentMonth));
    $endDate = strtotime('+1 month', $startDate);
    $dates = [];
    $d = $startDate;
    while ($d < $endDate) {
        $dates[] = $d;
        $d = strtotime('+1 day', $d);
    }
} else {
    $baseMonday = strtotime('monday this week');
    $monday = strtotime(($offset >= 0 ? '+' : '') . $offset . ' week', $baseMonday);
    $startDate = strtotime(date('Y-m-d 00:00:00', $monday));
    $endDate = strtotime('+7 day', $startDate);
    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $dates[] = strtotime("+$i day", $startDate);
    }
}

$dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

$eventsByDate = [];
foreach ($events as $ev) {
    if ($statusFilter !== 'all' && ($ev['statut'] ?? '') !== $statusFilter) continue;
    
    $startTs = strtotime((string)($ev['date_debut'] ?? ''));
    if ($startTs === false) continue;
    
    if ($lieuFilter !== 'all' && ($ev['lieu'] ?? '') !== $lieuFilter) continue;
    
    $dateKey = date('Y-m-d', $startTs);
    if ($startTs >= $startDate && $startTs < $endDate) {
        if (!isset($eventsByDate[$dateKey])) $eventsByDate[$dateKey] = [];
        $eventsByDate[$dateKey][] = [
            'id_session' => $ev['id_session'] ?? 0,
            'title' => $prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? ($ev['prestation_titre'] ?? 'Formation'),
            'time_start' => date('H:i', $startTs),
            'time_end' => date('H:i', strtotime((string)($ev['date_fin'] ?? ''))),
            'lieu' => $ev['lieu'] ?? 'Lieu non défini',
            'statut' => $ev['statut'] ?? 'en_attente',
            'capacite' => (int)($ev['capacite_max'] ?? 0),
            'inscrits' => (int)($ev['inscrits_count'] ?? 0),
        ];
    }
}

$totalEvents = count($events);
$validatedEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'valide'));
$pendingEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'en_attente'));
$weekEvents = count($eventsByDate);

$pendingReports = 0;
db_safe_exec(function(PDO $pdo) use (&$pendingReports) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM forum_reports WHERE status = "pending"');
    $pendingReports = (int)$stmt->fetchColumn();
    return true;
}, false);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon planning - Espace Salarié</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>

<main class="planning-container">
  
    <div class="header-stats">
        <div>
            <h1>🗓️ Mon planning</h1>
            <p>Gérez vos formations, ateliers et animations</p>
        </div>
        <div class="stats-badges">
            <div class="stat-badge"><div class="number"><?= $totalEvents ?></div><div class="label">Total</div></div>
            <div class="stat-badge"><div class="number"><?= $validatedEvents ?></div><div class="label">Validés</div></div>
            <div class="stat-badge"><div class="number"><?= $pendingEvents ?></div><div class="label">En attente</div></div>
            <?php if ($pendingReports > 0): ?>
            <div class="stat-badge" style="background:#f44336;"><div class="number"><?= $pendingReports ?></div><div class="label">Signalements</div></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="toolbar">
        <div class="view-switch">
            <a href="?view=week&offset=<?= $offset ?>&status=<?= $statusFilter ?>&lieu=<?= $lieuFilter ?>" class="view-btn <?= $viewMode === 'week' ? 'active' : '' ?>">📅 Semaine</a>
            <a href="?view=month&offset=<?= $offset ?>&status=<?= $statusFilter ?>&lieu=<?= $lieuFilter ?>" class="view-btn <?= $viewMode === 'month' ? 'active' : '' ?>">📆 Mois</a>
        </div>
        
        <div class="nav-buttons">
            <form method="GET" style="margin:0;">
                <input type="hidden" name="view" value="<?= $viewMode ?>">
                <input type="hidden" name="offset" value="<?= $offset - 1 ?>">
                <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <input type="hidden" name="lieu" value="<?= $lieuFilter ?>">
                <button class="nav-btn" type="submit">← Précédent</button>
            </form>
            <form method="GET" style="margin:0;">
                <input type="hidden" name="view" value="<?= $viewMode ?>">
                <input type="hidden" name="offset" value="0">
                <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <input type="hidden" name="lieu" value="<?= $lieuFilter ?>">
                <button class="nav-btn" type="submit">📌 Aujourd'hui</button>
            </form>
            <form method="GET" style="margin:0;">
                <input type="hidden" name="view" value="<?= $viewMode ?>">
                <input type="hidden" name="offset" value="<?= $offset + 1 ?>">
                <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <input type="hidden" name="lieu" value="<?= $lieuFilter ?>">
                <button class="nav-btn" type="submit">Suivant →</button>
            </form>
        </div>
        
        <div class="filters">
            <form method="GET" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="hidden" name="view" value="<?= $viewMode ?>">
                <input type="hidden" name="offset" value="<?= $offset ?>">
                <select name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                    <option value="valide" <?= $statusFilter === 'valide' ? 'selected' : '' ?>>✅ Validés</option>
                    <option value="en_attente" <?= $statusFilter === 'en_attente' ? 'selected' : '' ?>>⏳ En attente</option>
                    <option value="annule" <?= $statusFilter === 'annule' ? 'selected' : '' ?>>❌ Annulés</option>
                </select>
                <select name="lieu">
                    <option value="all" <?= $lieuFilter === 'all' ? 'selected' : '' ?>>Tous les lieux</option>
                    <?php foreach (array_keys($salles) as $salle): ?>
                        <option value="<?= e($salle) ?>" <?= $lieuFilter === $salle ? 'selected' : '' ?>><?= e($salle) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary" type="submit" style="padding: 8px 16px;">Appliquer</button>
            </form>
        </div>
    </div>
    
    <div class="action-buttons" style="display: flex; gap: 16px; margin-top: 24px; justify-content: center;">
        <a href="salarie_events.php" class="btn-primary">+ Créer un événement</a>
        <a href="salarie_conseils.php" class="btn-primary" style="background:#2196f3;">📝 Rédiger un conseil</a>
        <?php if ($pendingReports > 0): ?>
            <a href="forum.php" class="btn-primary" style="background:#f44336;">⚠️ Modérer le forum (<?= $pendingReports ?>)</a>
        <?php endif; ?>
    </div>
    
    <div class="planning-table">
        <div class="planning-grid">
           
            <div class="grid-header">Horaire</div>
            <?php foreach ($dates as $idx => $ts): ?>
                <div class="grid-header">
                    <div class="date-cell">
                        <strong><?= $dayNames[$idx % 7] ?></strong>
                        <div class="day"><?= date('d/m/Y', $ts) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($viewMode === 'week'): ?>
                <?php for ($hour = 8; $hour <= 20; $hour++): ?>
                    <?php for ($min = 0; $min < 60; $min += 60): ?>
                        <?php $timeLabel = sprintf('%02d:%02d', $hour, $min); ?>
                        <div class="grid-hour"><?= $timeLabel ?></div>
                        <?php foreach ($dates as $ts): ?>
                            <?php $dateKey = date('Y-m-d', $ts); ?>
                            <div class="grid-cell">
                                <?php if (isset($eventsByDate[$dateKey])): ?>
                                    <?php foreach ($eventsByDate[$dateKey] as $event): ?>
                                        <?php if (substr($event['time_start'], 0, 5) === $timeLabel): ?>
                                            <div class="event-card <?= $event['statut'] ?>" onclick='showEventModal(<?= json_encode($event, JSON_HEX_TAG) ?>)'>
                                                <span class="event-time">⏰ <?= $event['time_start'] ?> - <?= $event['time_end'] ?></span>
                                                <span class="event-title"><?= e(mb_substr($event['title'], 0, 30)) ?></span>
                                                <span class="event-lieu">📍 <?= e(mb_substr($event['lieu'], 0, 20)) ?></span>
                                                <span class="badge badge-<?= $event['statut'] === 'valide' ? 'valide' : ($event['statut'] === 'en_attente' ? 'en_attente' : 'annule') ?>">
                                                    <?= $event['statut'] === 'valide' ? 'Validé' : ($event['statut'] === 'en_attente' ? 'En attente' : 'Annulé') ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                <?php endfor; ?>
            <?php else: ?>
    
                <?php foreach ($dates as $ts): ?>
                    <div class="grid-hour" style="background:#f8f9fa; font-weight:600;"><?= date('d/m', $ts) ?></div>
                    <?php $dateKey = date('Y-m-d', $ts); ?>
                    <div class="grid-cell" style="grid-column: span <?= count($dates) ?>;">
                        <?php if (isset($eventsByDate[$dateKey])): ?>
                            <?php foreach ($eventsByDate[$dateKey] as $event): ?>
                                <div class="event-card <?= $event['statut'] ?>" onclick='showEventModal(<?= json_encode($event, JSON_HEX_TAG) ?>)'>
                                    <span class="event-time">⏰ <?= $event['time_start'] ?> - <?= $event['time_end'] ?></span>
                                    <span class="event-title"><?= e($event['title']) ?></span>
                                    <span class="event-lieu">📍 <?= e($event['lieu']) ?></span>
                                    <span class="badge badge-<?= $event['statut'] === 'valide' ? 'valide' : ($event['statut'] === 'en_attente' ? 'en_attente' : 'annule') ?>">
                                        <?= $event['statut'] === 'valide' ? 'Validé' : ($event['statut'] === 'en_attente' ? 'En attente' : 'Annulé') ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-cell">Aucun événement</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="display: flex; gap: 24px; margin-top: 24px; padding: 16px; background: white; border-radius: 16px; flex-wrap: wrap; justify-content: center;">
        <div><span style="background:#e8f5e9; border-left:4px solid #4caf50; padding:4px 12px;">✅ Validé</span> = Événement confirmé</div>
        <div><span style="background:#fff3e0; border-left:4px solid #ff9800; padding:4px 12px;">⏳ En attente</span> = Validation responsable requise</div>
        <div><span style="background:#f5f5f5; border-left:4px solid #9e9e9e; padding:4px 12px;">❌ Annulé</span> = Événement annulé</div>
    </div>
</main>

<div id="eventModal" class="modal" onclick="closeModal()">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle" style="margin-bottom: 16px;"></h3>
        <div style="margin-bottom: 12px;"><strong>⏰ Horaire :</strong> <span id="modalTime"></span></div>
        <div style="margin-bottom: 12px;"><strong>📍 Lieu :</strong> <span id="modalLieu"></span></div>
        <div style="margin-bottom: 12px;"><strong>📊 Capacité :</strong> <span id="modalCapacite"></span></div>
        <div style="margin-bottom: 12px;"><strong>📌 Statut :</strong> <span id="modalStatut"></span></div>
        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee;">
            <a href="salarie_events.php" class="btn-primary" style="display: inline-block; text-decoration: none;">Gérer mes événements</a>
        </div>
    </div>
</div>

<script>
function showEventModal(event) {
    document.getElementById('modalTitle').textContent = event.title;
    document.getElementById('modalTime').textContent = event.time_start + ' - ' + event.time_end;
    document.getElementById('modalLieu').textContent = event.lieu;
    document.getElementById('modalCapacite').textContent = (event.inscrits || 0) + ' / ' + (event.capacite || 0);
    
    let statutHtml = '';
    if (event.statut === 'valide') statutHtml = '<span style="background:#4caf50; color:white; padding:4px 12px; border-radius:20px;">✅ Validé</span>';
    else if (event.statut === 'en_attente') statutHtml = '<span style="background:#ff9800; color:white; padding:4px 12px; border-radius:20px;">⏳ En attente de validation</span>';
    else statutHtml = '<span style="background:#9e9e9e; color:white; padding:4px 12px; border-radius:20px;">❌ Annulé</span>';
    document.getElementById('modalStatut').innerHTML = statutHtml;
    
    document.getElementById('eventModal').classList.add('active');
}

function closeModal() {
    document.getElementById('eventModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>