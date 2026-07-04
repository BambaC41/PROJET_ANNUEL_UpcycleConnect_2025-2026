<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/prestations.php';
require_once __DIR__ . '/includes/functions/events.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];
$prestations = api_get_prestations($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $eventId = (int)$_POST['event_id'];
    $dateDebut = date('Y-m-d H:i:s', strtotime((string)($_POST['date_debut'] ?? '')));
    $dateFin = date('Y-m-d H:i:s', strtotime((string)($_POST['date_fin'] ?? '')));
    $lieu = trim((string)($_POST['lieu'] ?? ''));
    $capacite = (int)($_POST['capacite_max'] ?? 0);
    $newStatut = trim((string)($_POST['statut'] ?? ''));
    
    $currentImage = '';
    db_safe_exec(function (PDO $pdo) use ($eventId, &$currentImage) {
        $stmt = $pdo->prepare('SELECT image_url FROM session WHERE id_session = ?');
        $stmt->execute([$eventId]);
        $currentImage = (string)$stmt->fetchColumn();
        return true;
    }, false);
    
    $imageUrl = $currentImage;
    
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/events/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['event_image']['tmp_name']);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (in_array($mimeType, $allowedTypes)) {
            $ext = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            $filename = 'event_' . $eventId . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $destination)) {
                if (!empty($currentImage) && $currentImage !== 'uploads/events/default.jpg' && file_exists(__DIR__ . '/' . $currentImage)) {
                    unlink(__DIR__ . '/' . $currentImage);
                }
                $imageUrl = 'uploads/events/' . $filename;
            }
        }
    }
    
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        if (!empty($currentImage) && $currentImage !== 'uploads/events/default.jpg' && file_exists(__DIR__ . '/' . $currentImage)) {
            unlink(__DIR__ . '/' . $currentImage);
        }
        $imageUrl = '';
    }
    
    $belongsToUser = (bool)db_safe_exec(function (PDO $pdo) use ($eventId, $userId) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM session WHERE id_session = ? AND id_createur = ?');
        $st->execute([$eventId, $userId]);
        return $st->fetchColumn() > 0;
    }, false);
    
    if ($belongsToUser) {
        $payload = [
            'date_debut' => $dateDebut, 
            'date_fin' => $dateFin, 
            'lieu' => $lieu, 
            'capacite_max' => $capacite,
            'statut' => $newStatut,
            'image_url' => $imageUrl
        ];
        
        api_update_event($token, $eventId, $payload);
        
        db_safe_exec(function (PDO $pdo) use ($eventId, $dateDebut, $dateFin, $lieu, $capacite, $newStatut, $imageUrl) {
            $st = $pdo->prepare('UPDATE session SET date_debut = ?, date_fin = ?, lieu = ?, capacite_max = ?, statut = ?, image_url = ? WHERE id_session = ?');
            $st->execute([$dateDebut, $dateFin, $lieu, $capacite, $newStatut, $imageUrl, $eventId]);
            return true;
        }, false);
        
        toast_redirect('salarie_events.php', 'success', '✅ Événement modifié avec succès.');
    } else {
        toast_redirect('salarie_events.php', 'error', '❌ Vous ne pouvez pas modifier cet événement.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eventId = (int)$_POST['event_id'];
    
    $imageToDelete = '';
    db_safe_exec(function (PDO $pdo) use ($eventId, &$imageToDelete) {
        $stmt = $pdo->prepare('SELECT image_url FROM session WHERE id_session = ?');
        $stmt->execute([$eventId]);
        $imageToDelete = (string)$stmt->fetchColumn();
        return true;
    }, false);
    
    $belongsToUser = (bool)db_safe_exec(function (PDO $pdo) use ($eventId, $userId) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM session WHERE id_session = ? AND id_createur = ?');
        $st->execute([$eventId, $userId]);
        return $st->fetchColumn() > 0;
    }, false);
    
    if ($belongsToUser) {
        $inscrits = (int)db_safe_exec(function (PDO $pdo) use ($eventId) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM inscription WHERE id_session = ?');
            $st->execute([$eventId]);
            return (int)$st->fetchColumn();
        }, 0);
        
        if ($inscrits > 0) {
            api_update_event($token, $eventId, ['statut' => 'annule']);
            db_safe_exec(function (PDO $pdo) use ($eventId) {
                $st = $pdo->prepare('UPDATE session SET statut = "annule" WHERE id_session = ?');
                $st->execute([$eventId]);
                return true;
            }, false);
            
            $participants = (array)db_safe_exec(function (PDO $pdo) use ($eventId) {
                $st = $pdo->prepare('SELECT id_user FROM inscription WHERE id_session = ?');
                $st->execute([$eventId]);
                return $st->fetchAll(PDO::FETCH_COLUMN);
            }, []);
            foreach ($participants as $uid) {
                notif_create((int)$uid, 'evenement', 'Événement annulé', 'L\'événement auquel vous étiez inscrit a été annulé.');
            }
            
            toast_redirect('salarie_events.php', 'warning', '⚠️ Événement annulé. Les participants ont été notifiés.');
        } else {
            api_delete_event($token, $eventId);
            db_safe_exec(function (PDO $pdo) use ($eventId) {
                $st = $pdo->prepare('DELETE FROM session WHERE id_session = ?');
                $st->execute([$eventId]);
                return true;
            }, false);
            
            if (!empty($imageToDelete) && $imageToDelete !== 'uploads/events/default.jpg' && file_exists(__DIR__ . '/' . $imageToDelete)) {
                unlink(__DIR__ . '/' . $imageToDelete);
            }
            
            toast_redirect('salarie_events.php', 'success', '✅ Événement supprimé définitivement.');
        }
    } else {
        toast_redirect('salarie_events.php', 'error', '❌ Vous ne pouvez pas modifier cet événement.');
    }
}

$events = salarie_events_for_user($token, $userId);
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));

$prestById = [];
foreach ($prestations as $p) {
    $prestById[(int)($p['id_prestation'] ?? 0)] = $p;
}

$imageMap = [];
db_safe_exec(function (PDO $pdo) use (&$imageMap) {
    $stmt = $pdo->query('SELECT id_session, image_url FROM session WHERE image_url IS NOT NULL AND image_url != ""');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $imageMap[(int)$row['id_session']] = $row['image_url'];
    }
    return true;
}, false);

$inscritsMap = [];
db_safe_exec(function (PDO $pdo) use (&$inscritsMap) {
    $stmt = $pdo->query('SELECT id_session, COUNT(*) as nb FROM inscription GROUP BY id_session');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $inscritsMap[(int)$row['id_session']] = (int)$row['nb'];
    }
    return true;
}, false);

$filtered = [];
foreach ($events as $ev) {
    if ($status !== 'all' && ($ev['statut'] ?? '') !== $status) continue;
    $title = $prestById[(int)($ev['id_prestation'] ?? 0)]['titre'] ?? ($ev['prestation_titre'] ?? 'Session');
    $hay = strtolower($title . ' ' . ($ev['lieu'] ?? ''));
    if ($q !== '' && !str_contains($hay, strtolower($q))) continue;
    $ev['_title'] = $title;
    $ev['_image_url'] = $imageMap[(int)($ev['id_session'] ?? 0)] ?? '';
    $ev['_inscrits'] = $inscritsMap[(int)($ev['id_session'] ?? 0)] ?? 0;
    $filtered[] = $ev;
}

$totalEvents = count($events);
$validatedEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'valide'));
$pendingEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'en_attente'));
$cancelledEvents = count(array_filter($events, fn($e) => ($e['statut'] ?? '') === 'annule'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes événements - Espace Salarié</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="events-page">
    
    <div class="page-header-compact">
        <div>
            <h1>🎓 Mes événements</h1>
            <p>Cliquez sur un événement pour le modifier</p>
        </div>
        <a href="salarie_events_create.php" class="btn-create">+ Nouvel événement</a>
    </div>
    
    <div class="stats-row-mini">
        <div class="stat-mini"><div class="number"><?= $totalEvents ?></div><div class="label">Total</div></div>
        <div class="stat-mini"><div class="number" style="color:#2e7d32;"><?= $validatedEvents ?></div><div class="label">Validés</div></div>
        <div class="stat-mini"><div class="number" style="color:#ef6c00;"><?= $pendingEvents ?></div><div class="label">En attente</div></div>
        <div class="stat-mini"><div class="number" style="color:#dc2626;"><?= $cancelledEvents ?></div><div class="label">Annulés</div></div>
    </div>
    
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input type="text" name="q" placeholder="🔍 Rechercher..." value="<?= e($q) ?>" style="flex: 2; min-width: 150px;">
            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="valide" <?= $status === 'valide' ? 'selected' : '' ?>>Validés</option>
                <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="annule" <?= $status === 'annule' ? 'selected' : '' ?>>Annulés</option>
            </select>
            <button type="submit" style="background:#f0f0f0; border:none; padding:8px 16px; border-radius:30px; cursor:pointer;">Filtrer</button>
            <a href="salarie_events.php" style="background:#f0f0f0; color:#333; padding:8px 16px; border-radius:30px; text-decoration:none;">Reset</a>
        </form>
    </div>
    
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
            <h3>Aucun événement trouvé</h3>
            <p>Créez votre premier événement en cliquant sur le bouton ci-dessus.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($filtered as $ev): 
                $statut = $ev['statut'] ?? 'en_attente';
                $badgeClass = match($statut) {
                    'valide' => 'badge-valide',
                    'en_attente' => 'badge-en_attente',
                    'annule' => 'badge-annule',
                    default => 'badge-en_attente'
                };
                $badgeText = match($statut) {
                    'valide' => '✅ Validé',
                    'en_attente' => '⏳ En attente',
                    'annule' => '❌ Annulé',
                    default => $statut
                };
                $inscrits = $ev['_inscrits'] ?? 0;
                $capacite = (int)($ev['capacite_max'] ?? 0);
                $title = $ev['_title'] ?? 'Session';
                
                $imageUrl = '/upcycle/' . $ev['_image_url'];
                if (empty($ev['_image_url']) || !file_exists(__DIR__ . '/' . $ev['_image_url'])) {
                    $bgImage = 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=600&q=80';
                    if (str_contains(mb_strtolower($title), 'bois')) {
                        $bgImage = 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=600&q=80';
                    } elseif (str_contains(mb_strtolower($title), 'velo')) {
                        $bgImage = 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=600&q=80';
                    }
                    $imageUrl = $bgImage;
                }
            ?>
                <div class="event-card" onclick='openEventZoom(<?= htmlspecialchars(json_encode([
                    'id' => $ev['id_session'],
                    'title' => $title,
                    'date_debut' => date('d/m/Y H:i', strtotime($ev['date_debut'] ?? 'now')),
                    'date_fin' => date('d/m/Y H:i', strtotime($ev['date_fin'] ?? 'now')),
                    'lieu' => $ev['lieu'] ?? 'Lieu non défini',
                    'statut' => $statut,
                    'statut_text' => $badgeText,
                    'capacite' => $capacite,
                    'inscrits' => $inscrits,
                    'image_url' => $imageUrl,
                    'date_debut_raw' => date('Y-m-d\TH:i', strtotime($ev['date_debut'] ?? 'now')),
                    'date_fin_raw' => date('Y-m-d\TH:i', strtotime($ev['date_fin'] ?? 'now')),
                ]), JSON_HEX_TAG) ?>)'>
                    <img class="event-card-image" src="<?= $imageUrl ?>" alt="<?= e($title) ?>">
                    <span class="event-card-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    <div class="event-card-content">
                        <h3 class="event-card-title"><?= e(mb_substr($title, 0, 40)) ?></h3>
                        <div class="event-card-date">📅 <?= date('d/m/Y H:i', strtotime($ev['date_debut'] ?? 'now')) ?></div>
                        <div class="event-card-lieu">📍 <?= e(mb_substr($ev['lieu'] ?? 'Lieu non défini', 0, 35)) ?></div>
                        <div class="event-card-capacite">👥 <?= $inscrits ?> / <?= $capacite ?> inscrits</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<div id="eventZoomModal" class="modal-event-zoom" onclick="closeEventZoom()">
    <div class="modal-event-content" onclick="event.stopPropagation()">
        <span class="modal-event-close" onclick="closeEventZoom()">&times;</span>
        <div class="modal-event-image-container">
            <img id="modalEventImg" class="modal-event-img" src="" alt="">
        </div>
        <div class="modal-event-body">
            <h2 id="zoomTitle"></h2>
            <div class="modal-info-row"><div class="modal-info-label">📅 Date début :</div><div class="modal-info-value" id="zoomDateDebut"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📅 Date fin :</div><div class="modal-info-value" id="zoomDateFin"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📍 Lieu :</div><div class="modal-info-value" id="zoomLieu"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">👥 Capacité :</div><div class="modal-info-value" id="zoomCapacite"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📌 Statut :</div><div class="modal-info-value" id="zoomStatut"></div></div>
            <div class="modal-actions" id="modalActions">
                <!-- Généré dynamiquement -->
            </div>
        </div>
        <div id="editMode" style="display:none; padding:24px; border-top:1px solid #eee;">
            <form method="POST" enctype="multipart/form-data" id="editEventForm">
                <input type="hidden" name="update_event" value="1">
                <input type="hidden" name="event_id" id="edit_event_id">
                
                <div class="modal-form-group">
                    <label>📅 Date de début</label>
                    <input type="datetime-local" name="date_debut" id="edit_date_debut" required>
                </div>
                <div class="modal-form-group">
                    <label>📅 Date de fin</label>
                    <input type="datetime-local" name="date_fin" id="edit_date_fin" required>
                </div>
                <div class="modal-form-group">
                    <label>📍 Lieu</label>
                    <input type="text" name="lieu" id="edit_lieu" required>
                </div>
                <div class="modal-form-group">
                    <label>👥 Capacité maximale</label>
                    <input type="number" name="capacite_max" id="edit_capacite" min="1" required>
                </div>
                <div class="modal-form-group">
                    <label>📌 Statut</label>
                    <select name="statut" id="edit_statut">
                        <option value="en_attente">⏳ En attente</option>
                        <option value="valide">✅ Validé</option>
                        <option value="annule">❌ Annulé</option>
                    </select>
                    <small style="color:#666; display:block; margin-top:4px;">Vous pouvez modifier le statut même si l'événement est déjà validé.</small>
                </div>
                <div class="modal-form-group">
                    <label>🖼️ Changer l'image</label>
                    <div id="currentImagePreview" class="current-image"></div>
                    <input type="file" name="event_image" accept="image/jpeg,image/png,image/webp">
                    <label style="margin-top:8px;">
                        <input type="checkbox" name="delete_image" value="1"> Supprimer l'image actuelle
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-modal-primary">💾 Enregistrer</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="toggleEditMode()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentEventData = null;

function openEventZoom(event) {
    currentEventData = event;
    
    document.getElementById('zoomTitle').textContent = event.title;
    document.getElementById('zoomDateDebut').textContent = event.date_debut;
    document.getElementById('zoomDateFin').textContent = event.date_fin;
    document.getElementById('zoomLieu').textContent = event.lieu;
    document.getElementById('zoomCapacite').textContent = event.inscrits + ' / ' + event.capacite + ' inscrits';
    
    let statutHtml = '';
    if (event.statut === 'valide') statutHtml = '<span class="badge-valide" style="background:#e8f5e9; color:#2e7d32; padding:4px 12px; border-radius:30px;">✅ Validé</span>';
    else if (event.statut === 'en_attente') statutHtml = '<span class="badge-en_attente" style="background:#fff3e0; color:#ef6c00; padding:4px 12px; border-radius:30px;">⏳ En attente</span>';
    else statutHtml = '<span class="badge-annule" style="background:#f5f5f5; color:#757575; padding:4px 12px; border-radius:30px;">❌ Annulé</span>';
    document.getElementById('zoomStatut').innerHTML = statutHtml;
    
    const modalImg = document.getElementById('modalEventImg');
    modalImg.src = event.image_url;
    modalImg.alt = event.title;
    
    const actionsDiv = document.getElementById('modalActions');
    actionsDiv.innerHTML = '';
    
    const editBtn = document.createElement('button');
    editBtn.className = 'btn-modal btn-modal-primary';
    editBtn.innerHTML = '✏️ Modifier';
    editBtn.onclick = () => toggleEditMode();
    actionsDiv.appendChild(editBtn);
    
    if (event.statut !== 'annule') {
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-modal btn-modal-danger';
        if (event.inscrits > 0) {
            cancelBtn.innerHTML = '❌ Annuler l\'événement';
        } else {
            cancelBtn.innerHTML = '🗑️ Supprimer l\'événement';
        }
        cancelBtn.onclick = () => confirmDeleteOrCancel();
        actionsDiv.appendChild(cancelBtn);
    }
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'btn-modal btn-modal-secondary';
    closeBtn.innerHTML = 'Fermer';
    closeBtn.onclick = () => closeEventZoom();
    actionsDiv.appendChild(closeBtn);
    
    document.getElementById('edit_event_id').value = event.id;
    document.getElementById('edit_date_debut').value = event.date_debut_raw;
    document.getElementById('edit_date_fin').value = event.date_fin_raw;
    document.getElementById('edit_lieu').value = event.lieu;
    document.getElementById('edit_capacite').value = event.capacite;
    document.getElementById('edit_statut').value = event.statut;
    
    const previewDiv = document.getElementById('currentImagePreview');
    if (event.image_url && !event.image_url.includes('unsplash')) {
        previewDiv.innerHTML = '<img src="' + event.image_url + '" class="image-preview"><br><small>Image actuelle</small>';
    } else {
        previewDiv.innerHTML = '<small>Aucune image personnalisée</small>';
    }
    
    const editDiv = document.getElementById('editMode');
    if (editDiv) editDiv.style.display = 'none';
    
    document.getElementById('eventZoomModal').classList.add('active');
}

function closeEventZoom() {
    document.getElementById('eventZoomModal').classList.remove('active');
    currentEventData = null;
}

function toggleEditMode() {
    const editDiv = document.getElementById('editMode');
    if (editDiv.style.display === 'none') {
        editDiv.style.display = 'block';
    } else {
        editDiv.style.display = 'none';
    }
}

function confirmDeleteOrCancel() {
    var hasInscrits = currentEventData && currentEventData.inscrits > 0;
    var message = hasInscrits 
        ? '⚠️ Attention : ' + currentEventData.inscrits + ' personne(s) sont inscrites à cet événement.\n\n' +
          'L\'annulation enverra une notification aux participants et l\'événement ne sera plus accessible.\n\n' +
          'Confirmez-vous l\'ANNULATION de cet événement ?'
        : '⚠️ Êtes-vous sûr de vouloir SUPPRIMER définitivement cet événement ?\n\n' +
          'Cette action est irréversible.';
    
    if (confirm(message)) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        var input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'delete_event';
        input1.value = '1';
        var input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'event_id';
        input2.value = currentEventData.id;
        form.appendChild(input1);
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEventZoom();
});
</script>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>