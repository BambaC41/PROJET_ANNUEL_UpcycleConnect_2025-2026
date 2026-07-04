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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell">
    <div class="pro-card">
        <h1>🗓️ Mon planning hebdomadaire</h1>
        
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

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
<?php  ?>
</body>
</html>