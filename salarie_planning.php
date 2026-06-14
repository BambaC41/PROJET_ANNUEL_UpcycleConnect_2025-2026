<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/prestations.php';
require_once __DIR__ . '/includes/functions/events.php';
require_once __DIR__ . '/includes/functions/local_db.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];

// Récupération des données
$events = salarie_events_for_user($token, $userId);
$prestations = api_get_prestations($token);

// Map des prestations
$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? 'N/A';
}

// Paramètres
$viewMode = $_GET['view'] ?? 'week'; // week ou month
$offset = (int)($_GET['offset'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$lieuFilter = $_GET['lieu'] ?? 'all';

// Liste des salles (conformément au sujet)
$salles = [
    '10e - Siège social' => '174 rue La Fayette, 75010 Paris',
    '11e - Annexe' => '11e arrondissement, Paris',
    '13e - Annexe' => '13e arrondissement, Paris',
    '16e - Annexe' => '16e arrondissement, Paris',
    'Bourg-la-Reine' => 'Bourg-la-Reine, 92340',
    'Ivry' => 'Ivry-sur-Seine, 94200',
    'Montreuil' => 'Montreuil, 93100',
];

// Calcul de la plage de dates
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

// Filtrage et organisation des événements
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

// Statistiques
$totalEvents = count($events);
$validatedEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'valide'));
$pendingEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'en_attente'));
$weekEvents = count($eventsByDate);

// Récupération des signalements forum en attente
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
    <style>
        * { box-sizing: border-box; }
        
        body { background: #f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        .planning-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* En-tête avec infos */
        .header-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: linear-gradient(135deg, #2e7d32, #4caf50);
            border-radius: 20px;
            color: white;
        }
        
        .header-stats h1 { margin: 0 0 8px 0; font-size: 24px; }
        .header-stats p { margin: 0; opacity: 0.9; }
        
        .stats-badges {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background: rgba(255,255,255,0.2);
            border-radius: 30px;
            padding: 8px 20px;
            text-align: center;
            min-width: 100px;
        }
        
        .stat-badge .number { font-size: 24px; font-weight: 700; }
        .stat-badge .label { font-size: 12px; opacity: 0.9; }
        
        /* Barre d'outils */
        .toolbar {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .view-switch {
            display: flex;
            gap: 8px;
            background: #f0f0f0;
            border-radius: 40px;
            padding: 4px;
        }
        
        .view-btn {
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.2s;
        }
        
        .view-btn.active {
            background: #4caf50;
            color: white;
        }
        
        .nav-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .nav-btn {
            background: #f0f0f0;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .nav-btn:hover { background: #e0e0e0; }
        
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filters select, .filters button {
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid #ddd;
            background: white;
            font-size: 13px;
        }
        
        .btn-primary {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-primary:hover { background: #2e7d32; }
        
        /* Vue planning */
        .planning-table {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .planning-grid {
            display: grid;
            grid-template-columns: 100px repeat(<?= $viewMode === 'week' ? 7 : min(7, count($dates)) ?>, 1fr);
            min-width: 800px;
        }
        
        .grid-header {
            background: #f8f9fa;
            padding: 16px 8px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
        }
        
        .grid-header:first-child {
            background: #f8f9fa;
        }
        
        .date-cell {
            font-size: 14px;
        }
        
        .date-cell .day {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        
        .grid-hour {
            background: #fafbfc;
            padding: 12px 8px;
            text-align: center;
            font-size: 12px;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            font-weight: 500;
        }
        
        .grid-cell {
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            padding: 6px;
            vertical-align: top;
            min-height: 80px;
        }
        
        .event-card {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 8px 10px;
            margin-bottom: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .event-card:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .event-card.en_attente {
            background: #fff3e0;
            border-left-color: #ff9800;
        }
        
        .event-card.annule {
            background: #f5f5f5;
            border-left-color: #9e9e9e;
            opacity: 0.7;
        }
        
        .event-time {
            font-size: 10px;
            font-weight: 600;
            color: #666;
            display: block;
            margin-bottom: 4px;
        }
        
        .event-title {
            font-size: 13px;
            font-weight: 600;
            display: block;
        }
        
        .event-lieu {
            font-size: 10px;
            color: #666;
            display: block;
            margin-top: 4px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            margin-top: 4px;
        }
        
        .badge-valide { background: #4caf50; color: white; }
        .badge-en_attente { background: #ff9800; color: white; }
        .badge-annule { background: #9e9e9e; color: white; }
        
        .empty-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 80px;
            color: #ccc;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 24px;
            justify-content: center;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            padding: 24px;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 16px;
            right: 20px;
            cursor: pointer;
            font-size: 24px;
        }
        
        @media (max-width: 768px) {
            .planning-container { padding: 12px; }
            .planning-grid { font-size: 11px; }
            .event-title { font-size: 11px; }
        }
    </style>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>

<main class="planning-container">
    <!-- En-tête -->
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
    
    <!-- Barre d'outils -->
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
    
    <!-- Actions rapides -->
    <div class="action-buttons">
        <a href="salarie_events.php" class="btn-primary">+ Créer un événement</a>
        <a href="salarie_conseils.php" class="btn-primary" style="background:#2196f3;">📝 Rédiger un conseil</a>
        <?php if ($pendingReports > 0): ?>
            <a href="salarie_forum.php" class="btn-primary" style="background:#f44336;">⚠️ Modérer le forum (<?= $pendingReports ?>)</a>
        <?php endif; ?>
    </div>
    
    <!-- Planning -->
    <div class="planning-table">
        <div class="planning-grid">
            <!-- En-têtes des jours -->
            <div class="grid-header">Horaire</div>
            <?php foreach ($dates as $idx => $ts): ?>
                <div class="grid-header">
                    <div class="date-cell">
                        <strong><?= $dayNames[$idx % 7] ?></strong>
                        <div class="day"><?= date('d/m/Y', $ts) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Créneaux horaires (8h-20h pour semaine, pas d'heure pour mois) -->
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
                <!-- Vue mois : un événement par jour -->
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
    
    <!-- Légende -->
    <div style="display: flex; gap: 24px; margin-top: 24px; padding: 16px; background: white; border-radius: 16px; flex-wrap: wrap; justify-content: center;">
        <div><span style="background:#e8f5e9; border-left:4px solid #4caf50; padding:4px 12px;">✅ Validé</span> = Événement confirmé</div>
        <div><span style="background:#fff3e0; border-left:4px solid #ff9800; padding:4px 12px;">⏳ En attente</span> = Validation responsable requise</div>
        <div><span style="background:#f5f5f5; border-left:4px solid #9e9e9e; padding:4px 12px;">❌ Annulé</span> = Événement annulé</div>
    </div>
</main>

<!-- Modal -->
<div id="eventModal" class="modal" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
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