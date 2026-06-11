<?php
require_once 'includes/particulier_bootstrap.php';

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$inscriptions = api_get_my_inscriptions()['data'] ?? [];
$payments = api_get_my_paiements()['data'] ?? [];

$paidInscriptionIds = [];
foreach ($payments as $p) {
    if (($p['statut'] ?? '') === 'paid' && !empty($p['id_inscription'])) {
        $paidInscriptionIds[(int)$p['id_inscription']] = true;
    }
}

$offset = (int)($_GET['week'] ?? 0);
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$baseMonday = strtotime('monday this week');
$monday = strtotime(($offset >= 0 ? '+' : '') . $offset . ' week', $baseMonday);
$weekStart = strtotime(date('Y-m-d 00:00:00', $monday));
$weekEnd = strtotime('+7 day', $weekStart);
$dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$weekDays = [];
for ($i = 0; $i < 7; $i++) $weekDays[] = strtotime("+$i day", $weekStart);

$blocks = [];
foreach ($inscriptions as $ins) {
    if ($statusFilter !== 'all' && ($ins['statut'] ?? '') !== $statusFilter) continue;
    $startTs = strtotime((string)($ins['date_debut'] ?? ''));
    $endTs = strtotime((string)($ins['date_fin'] ?? ''));
    if ($startTs === false || $endTs === false || $endTs <= $startTs) continue;
    if ($startTs < $weekStart || $startTs >= $weekEnd) continue;
    $dayIdx = (int)date('N', $startTs) - 1;
    $startMin = ((int)date('G', $startTs) * 60 + (int)date('i', $startTs));
    $endMin = ((int)date('G', $endTs) * 60 + (int)date('i', $endTs));
    $gridStart = max(0, (int)floor(($startMin - 420) / 30));
    $gridSpan = max(1, (int)ceil(($endMin - $startMin) / 30));
    
    $iid = (int)($ins['id_inscription'] ?? 0);
    $price = (float)($ins['prestation_prix'] ?? 0);
    $isPaid = !empty($paidInscriptionIds[$iid]);
    
    $blocks[] = [
        'id_inscription' => $iid,
        'day' => $dayIdx,
        'start' => $gridStart,
        'span' => $gridSpan,
        'time' => date('H:i', $startTs) . ' - ' . date('H:i', $endTs),
        'title' => $ins['prestation_titre'] ?? 'Session',
        'lieu' => $ins['lieu'] ?? 'Lieu',
        'statut' => $ins['statut'] ?? '',
        'date' => date('d/m/Y', $startTs),
        'price' => $price,
        'isPaid' => $isPaid,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon planning - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        /* ===== STYLES COMPLETS SANS DEPENDANCE EXTERNE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #f5f7fb;
        }
        
        .pro-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .pro-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .pro-card h1 {
            margin-bottom: 24px;
            font-size: 24px;
            color: #1a1a2e;
        }
        
        /* Navigation semaine */
        .week-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .btn-outline {
            background: white;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-outline:hover {
            background: #f0f0f0;
            border-color: #bbb;
        }
        
        .week-date {
            font-weight: 700;
            color: #2e7d32;
            font-size: 14px;
            text-align: center;
        }
        
        /* Filtres */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: center;
        }
        
        .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        /* ===== PLANNING EN TABLE ===== */
        /* Version stable avant/après zoom */
        .schedule-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            background: white;
        }
        
        .schedule-table {
            min-width: 900px;
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
        }
        
        .schedule-table th,
        .schedule-table td {
            border: 1px solid #e5e7eb;
            padding: 8px 6px;
            vertical-align: top;
        }
        
        /* En-tête des jours */
        .schedule-table thead tr:first-child th {
            background: #f8f9fc;
            font-weight: 600;
            text-align: center;
            padding: 12px 6px;
            font-size: 13px;
        }
        
        .schedule-table thead tr:first-child th small {
            font-size: 10px;
            color: #666;
            font-weight: normal;
        }
        
        /* Colonne des heures */
        .schedule-table th:first-child,
        .schedule-table td:first-child {
            background: #fafbfc;
            font-weight: 500;
            text-align: center;
            width: 60px;
            font-size: 11px;
            color: #555;
        }
        
        /* Cellules de contenu */
        .schedule-table td {
            height: 50px;
            position: relative;
        }
        
        /* Événements dans les cellules */
        .event-card {
            background: #4caf50;
            color: white;
            border-radius: 8px;
            padding: 6px 8px;
            margin: -4px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .event-card:hover {
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .event-card.confirmee {
            background: #2196f3;
        }
        
        .event-card.annulee {
            background: #9e9e9e;
            opacity: 0.7;
            text-decoration: line-through;
        }
        
        .event-time {
            font-weight: 700;
            font-size: 9px;
            display: block;
            margin-bottom: 3px;
        }
        
        .event-title {
            font-weight: 500;
            font-size: 10px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .event-badge {
            display: inline-block;
            font-size: 7px;
            padding: 2px 5px;
            border-radius: 10px;
            margin-top: 3px;
            background: rgba(255,255,255,0.2);
        }
        
        .btn-pay-mini {
            background: #ff9800;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 7px;
            text-decoration: none;
            display: inline-block;
            margin-top: 3px;
        }
        
        /* Messages */
        .info-banner {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Modal */
        .modal-event {
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
        
        .modal-event.active {
            display: flex;
        }
        
        .modal-event-content {
            background: white;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            padding: 24px;
            position: relative;
        }
        
        .modal-event-close {
            position: absolute;
            top: 16px;
            right: 16px;
            cursor: pointer;
            font-size: 24px;
        }
        
        .btn-pay {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 16px;
            font-weight: 600;
        }
        
        .statut-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .statut-confirmee {
            background: #4caf50;
            color: white;
        }
        
        .statut-annulee {
            background: #f44336;
            color: white;
        }
        
        .statut-pending {
            background: #ff9800;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .pro-card {
                padding: 16px;
            }
            .pro-card h1 {
                font-size: 20px;
            }
            .schedule-table {
                font-size: 10px;
            }
            .schedule-table td {
                height: 45px;
            }
            .event-card {
                padding: 4px 6px;
            }
            .event-title {
                font-size: 9px;
            }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell">
    <div class="pro-card">
        <h1>🗓️ Mon planning hebdomadaire</h1>
        
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <!-- Navigation semaine -->
        <div class="week-nav">
            <form method="GET">
                <input type="hidden" name="week" value="<?= $offset - 1 ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <button class="btn-outline" type="submit">⬅ Semaine précédente</button>
            </form>
            <div class="week-date">
                Semaine du <?= date('d/m/Y', $weekStart) ?> au <?= date('d/m/Y', strtotime('+6 day', $weekStart)) ?>
            </div>
            <form method="GET">
                <input type="hidden" name="week" value="<?= $offset + 1 ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <button class="btn-outline" type="submit">Semaine suivante ➡</button>
            </form>
        </div>

        <!-- Filtres -->
        <form method="GET" class="filters">
            <input type="hidden" name="week" value="<?= $offset ?>">
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="confirmee" <?= $statusFilter === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                <option value="annulee" <?= $statusFilter === 'annulee' ? 'selected' : '' ?>>Annulée</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>

        <?php if (empty($blocks)): ?>
            <div class="info-banner">
                📅 Aucun événement programmé cette semaine pour le filtre sélectionné.
            </div>
        <?php endif; ?>

        <!-- PLANNING EN TABLE - Stable avant/après zoom -->
        <div class="schedule-container">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Heure</th>
                        <?php foreach ($weekDays as $i => $day): ?>
                            <th>
                                <?= htmlspecialchars($dayNames[$i]) ?><br>
                                <small><?= date('d/m', $day) ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Générer les créneaux horaires de 07:00 à 20:30 (27 créneaux de 30min)
                    for ($hour = 7; $hour <= 20; $hour++):
                        for ($minute = 0; $minute < 60; $minute += 30):
                            if ($hour == 20 && $minute > 0) continue;
                            $timeLabel = sprintf('%02d:%02d', $hour, $minute);
                            $timeMin = $hour * 60 + $minute;
                    ?>
                        <tr>
                            <td><?= $timeLabel ?></td>
                            <?php for ($dayIdx = 0; $dayIdx < 7; $dayIdx++): ?>
                                <td>
                                    <?php 
                                    // Chercher les événements qui commencent à ce créneau
                                    foreach ($blocks as $b):
                                        if ($b['day'] != $dayIdx) continue;
                                        $eventStartMin = 420 + $b['start'] * 30;
                                        if ($eventStartMin == $timeMin):
                                    ?>
                                        <div class="event-card <?= htmlspecialchars($b['statut']) ?>" 
                                             onclick='showEventDetails(<?= json_encode($b, JSON_HEX_TAG) ?>)'>
                                            <span class="event-time">⏰ <?= htmlspecialchars($b['time']) ?></span>
                                            <span class="event-title">📌 <?= htmlspecialchars(mb_substr($b['title'], 0, 25)) ?></span>
                                            <?php if ($b['isPaid']): ?>
                                                <span class="event-badge">✅ Payé</span>
                                            <?php elseif ($b['price'] > 0): ?>
                                                <span class="event-badge">⏳ Paiement requis</span>
                                                <a href="paiement_stripe.php?amount=<?= $b['price'] * 100 ?>&item=Inscription+<?= urlencode($b['title']) ?>&inscription_id=<?= $b['id_inscription'] ?>" 
                                                   class="btn-pay-mini" onclick="event.stopPropagation();">💳 Payer</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php 
                        endfor;
                    endfor; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal -->
<div id="eventModal" class="modal-event">
    <div class="modal-event-content">
        <span class="modal-event-close" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle"></h3>
        <p><strong>📍 Lieu :</strong> <span id="modalLieu"></span></p>
        <p><strong>📅 Date :</strong> <span id="modalDate"></span></p>
        <p><strong>⏰ Horaire :</strong> <span id="modalTime"></span></p>
        <p><strong>📌 Statut :</strong> <span id="modalStatut"></span></p>
        <p><strong>💰 Prix :</strong> <span id="modalPrice"></span></p>
        <div id="modalPayButton"></div>
    </div>
</div>

<script>
function showEventDetails(event) {
    document.getElementById('modalTitle').textContent = event.title;
    document.getElementById('modalLieu').textContent = event.lieu;
    document.getElementById('modalTime').textContent = event.time;
    document.getElementById('modalDate').textContent = event.date;
    
    let statutHtml = event.statut;
    if (event.statut === 'confirmee') statutHtml = '<span class="statut-badge statut-confirmee">✓ Confirmée</span>';
    else if (event.statut === 'annulee') statutHtml = '<span class="statut-badge statut-annulee">✗ Annulée</span>';
    else statutHtml = '<span class="statut-badge statut-pending">⏳ En attente</span>';
    document.getElementById('modalStatut').innerHTML = statutHtml;
    
    document.getElementById('modalPrice').innerHTML = (event.price || 0).toFixed(2) + ' €';
    
    const payDiv = document.getElementById('modalPayButton');
    if (!event.isPaid && event.price > 0) {
        payDiv.innerHTML = '<a href="paiement_stripe.php?amount=' + (event.price * 100) + '&item=Inscription+' + encodeURIComponent(event.title) + '&inscription_id=' + event.id_inscription + '" class="btn-pay">💳 Payer maintenant</a>';
    } else {
        payDiv.innerHTML = '';
    }
    
    document.getElementById('eventModal').classList.add('active');
}

function closeModal() {
    document.getElementById('eventModal').classList.remove('active');
}

window.onclick = function(e) {
    if (e.target === document.getElementById('eventModal')) closeModal();
}
</script>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>