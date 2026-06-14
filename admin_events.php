<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/events.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

// =============================================
// 1. VALIDATION / MODIFICATION STATUT
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $eventId = (int)$_POST['event_id'];
    $dateDebut = date('Y-m-d H:i:s', strtotime((string)($_POST['date_debut'] ?? '')));
    $dateFin = date('Y-m-d H:i:s', strtotime((string)($_POST['date_fin'] ?? '')));
    $lieu = trim((string)($_POST['lieu'] ?? ''));
    $capacite = (int)($_POST['capacite_max'] ?? 0);
    $eventStatus = trim((string)($_POST['statut'] ?? ''));
    $allowed = ['en_attente', 'valide', 'rejete', 'annule'];
    
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
    
    if (!in_array($eventStatus, $allowed, true)) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Statut invalide.'];
    } else {
        $rows = (int)db_safe_exec(static function (PDO $pdo) use ($eventId, $dateDebut, $dateFin, $lieu, $capacite, $eventStatus, $imageUrl): int {
            $stmt = $pdo->prepare('UPDATE session SET date_debut = ?, date_fin = ?, lieu = ?, capacite_max = ?, statut = ?, image_url = ? WHERE id_session = ?');
            $stmt->execute([$dateDebut, $dateFin, $lieu, $capacite, $eventStatus, $imageUrl, $eventId]);
            return $stmt->rowCount();
        }, 0);

        $current = api_get_event($_SESSION['token'], $eventId);
        if (($current['status'] ?? 0) === 200 && is_array($current['data'] ?? null)) {
            $payload = $current['data'];
            $payload['date_debut'] = $dateDebut;
            $payload['date_fin'] = $dateFin;
            $payload['lieu'] = $lieu;
            $payload['capacite_max'] = $capacite;
            $payload['statut'] = $eventStatus;
            if ($imageUrl) $payload['image_url'] = $imageUrl;
            api_update_event($_SESSION['token'], $eventId, $payload);
        }

        if ($rows > 0) {
            db_safe_exec(static function (PDO $pdo) use ($eventId, $eventStatus): void {
                $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "event", ?, ?, NOW())');
                $audit->execute([(int)($_SESSION['user_id'] ?? 0), strtoupper($eventStatus) . '_EVENT', $eventId, 'Modification événement admin']);
            }, null);
            
            $creatorId = (int)db_safe_exec(static function (PDO $pdo) use ($eventId): int {
                $stmt = $pdo->prepare('SELECT id_createur FROM session WHERE id_session = ?');
                $stmt->execute([$eventId]);
                return (int)$stmt->fetchColumn();
            }, 0);
            
            if ($creatorId > 0) {
                $message = match($eventStatus) {
                    'valide' => '✅ Votre événement a été validé et est maintenant visible par les particuliers !',
                    'rejete' => '❌ Votre événement a été rejeté. Veuillez contacter l\'administration.',
                    'annule' => '⚠️ Votre événement a été annulé.',
                    default => 'Le statut de votre événement a été modifié : ' . $eventStatus
                };
                notif_create($creatorId, 'evenement', 'Statut événement mis à jour', $message);
            }
            
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Événement mis à jour.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Événement #' . $eventId . ' introuvable.'];
        }
    }
    header('Location: admin_events.php');
    exit;
}

// =============================================
// 2. CRÉATION D'UN ÉVÉNEMENT (ADMIN)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $prestationId = (int)$_POST['prestation_id'];
    $dateDebut = $_POST['date_debut'];
    $dateFin = $_POST['date_fin'];
    $lieu = trim($_POST['lieu']);
    $capacite = (int)$_POST['capacite'];
    $statut = $_POST['statut'];
    $imageUrl = '';
    
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
            $filename = 'event_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $destination)) {
                $imageUrl = 'uploads/events/' . $filename;
            }
        }
    }
    
    $data = [
        'id_prestation' => $prestationId,
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin,
        'lieu' => $lieu,
        'capacite_max' => $capacite,
        'statut' => $statut,
        'id_validateur' => $_SESSION['user_id'],
        'image_url' => $imageUrl
    ];
    
    $result = callAPI('POST', '/events', $_SESSION['token'], $data);
    
    if (($result['status'] ?? 0) === 201) {
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '✅ Événement créé.'];
    } else {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Erreur: ' . ($result['error'] ?? 'Inconnue')];
    }
    header('Location: admin_events.php');
    exit;
}

// =============================================
// 3. SUPPRESSION/ANNULATION D'UN ÉVÉNEMENT
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $eventId = (int)$_POST['delete_event_id'];
    
    $imageToDelete = '';
    db_safe_exec(function (PDO $pdo) use ($eventId, &$imageToDelete) {
        $stmt = $pdo->prepare('SELECT image_url FROM session WHERE id_session = ?');
        $stmt->execute([$eventId]);
        $imageToDelete = (string)$stmt->fetchColumn();
        return true;
    }, false);
    
    $inscrits = (int)db_safe_exec(function (PDO $pdo) use ($eventId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscription WHERE id_session = ?');
        $stmt->execute([$eventId]);
        return (int)$stmt->fetchColumn();
    }, 0);
    
    if ($inscrits > 0) {
        db_safe_exec(function (PDO $pdo) use ($eventId) {
            $stmt = $pdo->prepare('UPDATE session SET statut = "annule" WHERE id_session = ?');
            $stmt->execute([$eventId]);
            return true;
        }, false);
        
        $current = api_get_event($_SESSION['token'], $eventId);
        if (($current['status'] ?? 0) === 200 && is_array($current['data'] ?? null)) {
            $payload = $current['data'];
            $payload['statut'] = 'annule';
            api_update_event($_SESSION['token'], $eventId, $payload);
        }
        
        $participants = (array)db_safe_exec(function (PDO $pdo) use ($eventId) {
            $stmt = $pdo->prepare('SELECT id_user FROM inscription WHERE id_session = ?');
            $stmt->execute([$eventId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }, []);
        foreach ($participants as $uid) {
            notif_create((int)$uid, 'evenement', 'Événement annulé', 'Un événement auquel vous étiez inscrit a été annulé par l\'administrateur.');
        }
        
        $creatorId = (int)db_safe_exec(function (PDO $pdo) use ($eventId) {
            $stmt = $pdo->prepare('SELECT id_createur FROM session WHERE id_session = ?');
            $stmt->execute([$eventId]);
            return (int)$stmt->fetchColumn();
        }, 0);
        if ($creatorId > 0) {
            notif_create($creatorId, 'evenement', 'Événement annulé', 'Votre événement a été annulé par l\'administrateur.');
        }
        
        $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => '⚠️ Événement annulé. Les participants ont été notifiés.'];
    } else {
        $res = api_delete_event($_SESSION['token'], $eventId);
        db_safe_exec(function (PDO $pdo) use ($eventId) {
            $stmt = $pdo->prepare('DELETE FROM session WHERE id_session = ?');
            $stmt->execute([$eventId]);
            return true;
        }, false);
        
        if (!empty($imageToDelete) && $imageToDelete !== 'uploads/events/default.jpg' && file_exists(__DIR__ . '/' . $imageToDelete)) {
            unlink(__DIR__ . '/' . $imageToDelete);
        }
        
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
            ? ['type' => 'success', 'message' => '🗑️ Événement supprimé définitivement.']
            : ['type' => 'error', 'message' => 'Suppression impossible.'];
    }
    header('Location: admin_events.php');
    exit;
}

// =============================================
// 4. RÉCUPÉRATION DES DONNÉES
// =============================================
$events = api_get_events($_SESSION['token']);
$prestations = api_get_prestations($_SESSION['token']);
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$status = trim((string)($_GET['status'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'date'));

$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? 'N/A';
}

$userPseudo = [];
$dbUsers = (array)db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT id_user, pseudo FROM utilisateur')->fetchAll(PDO::FETCH_ASSOC), []);
foreach ($dbUsers as $ru) {
    $userPseudo[(int)$ru['id_user']] = (string)($ru['pseudo'] ?? '');
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

$filtered = array_values(array_filter($events, function ($ev) use ($prestationsMap, $q, $status) {
    $title = mb_strtolower((string)($prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? ''));
    $lieu = mb_strtolower((string)($ev['lieu'] ?? ''));
    $okQ = ($q === '') || str_contains($title, $q) || str_contains($lieu, $q);
    $okS = ($status === 'all') || (($ev['statut'] ?? '') === $status);
    return $okQ && $okS;
}));

if ($sort === 'date') {
    usort($filtered, fn($a, $b) => strtotime($b['date_debut'] ?? '') <=> strtotime($a['date_debut'] ?? ''));
} elseif ($sort === 'statut') {
    usort($filtered, fn($a, $b) => strcmp($a['statut'] ?? '', $b['statut'] ?? ''));
}

function event_status_badge_class(string $st): string {
    return match ($st) {
        'en_attente' => 'badge-en_attente',
        'valide' => 'badge-valide',
        'annule' => 'badge-annule',
        'rejete' => 'badge-rejete',
        default => 'badge-en_attente'
    };
}

function event_status_text(string $st): string {
    return match ($st) {
        'en_attente' => '⏳ En attente',
        'valide' => '✅ Validé',
        'annule' => '❌ Annulé',
        'rejete' => '⚠️ Rejeté',
        default => $st
    };
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestion des événements</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-valide { background: #e8f5e9; color: #2e7d32; }
        .badge-en_attente { background: #fff3e0; color: #ef6c00; }
        .badge-annule { background: #f5f5f5; color: #757575; }
        .badge-rejete { background: #fee2e2; color: #dc2626; }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .event-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 12px 28px rgba(0,0,0,0.15); }
        
        .event-card-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        
        .event-card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            z-index: 2;
        }
        .event-card-badge.badge-valide { background: #4caf50; }
        .event-card-badge.badge-en_attente { background: #ff9800; }
        .event-card-badge.badge-annule { background: #9e9e9e; }
        .event-card-badge.badge-rejete { background: #f44336; }
        
        .event-card-content { padding: 16px; }
        .event-card-title { font-size: 18px; font-weight: 700; margin: 0 0 8px 0; color: #1a1a2e; }
        .event-card-date { font-size: 13px; color: #666; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .event-card-lieu { font-size: 13px; color: #666; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .event-card-creator { font-size: 11px; color: #999; margin-top: 8px; padding-top: 8px; border-top: 1px solid #eee; }
        
        .btn-create {
            background: #4caf50;
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        .btn-create:hover { background: #2e7d32; }
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        
        .modal-event-zoom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal-event-zoom.active { display: flex; }
        .modal-event-content {
            background: white;
            border-radius: 24px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 0;
            position: relative;
            cursor: default;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .modal-event-close {
            position: absolute;
            top: 12px;
            right: 16px;
            cursor: pointer;
            font-size: 28px;
            color: white;
            z-index: 20;
            background: rgba(0,0,0,0.5);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-event-image-container {
            width: 100%;
            background: #1a1a2e;
            border-radius: 24px 24px 0 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            max-height: 250px;
        }
        .modal-event-img {
            width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: contain;
            display: block;
        }
        .modal-event-body { padding: 24px; }
        .modal-event-body h2 { margin: 0 0 20px 0; font-size: 24px; border-bottom: 2px solid #e0e0e0; padding-bottom: 12px; }
        .modal-info-row { display: flex; margin-bottom: 14px; }
        .modal-info-label { width: 110px; font-weight: 600; color: #555; }
        .modal-info-value { flex: 1; color: #333; }
        .modal-actions { margin-top: 24px; border-top: 1px solid #eee; padding-top: 20px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-modal { padding: 10px 20px; border-radius: 30px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-modal-primary { background: #2196f3; color: white; }
        .btn-modal-primary:hover { background: #1976d2; }
        .btn-modal-success { background: #4caf50; color: white; }
        .btn-modal-success:hover { background: #2e7d32; }
        .btn-modal-warning { background: #ff9800; color: white; }
        .btn-modal-warning:hover { background: #f57c00; }
        .btn-modal-danger { background: #dc2626; color: white; }
        .btn-modal-danger:hover { background: #b91c1c; }
        .btn-modal-secondary { background: #9e9e9e; color: white; }
        .btn-modal-secondary:hover { background: #757575; }
        
        .modal-form-group { margin-bottom: 16px; }
        .modal-form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #333; }
        .modal-form-group input, .modal-form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }
        .image-preview { max-width: 100%; max-height: 100px; border-radius: 8px; margin-top: 8px; }
        .current-image { margin-bottom: 10px; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; color: #999; }
        
        @media (max-width: 768px) {
            .events-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    
    <section class="pro-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <h1 style="margin:0;">📅 Gestion des événements</h1>
            <button class="btn-create" onclick="openCreateModal()">+ Créer un événement</button>
        </div>
    </section>
    
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input type="text" name="q" placeholder="🔍 Rechercher..." value="<?= e($q) ?>" style="flex: 2; min-width: 150px; padding: 8px 12px; border:1px solid #ddd; border-radius:30px;">
            <select name="status" style="padding: 8px 12px; border-radius:30px; border:1px solid #ddd;">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="valide" <?= $status === 'valide' ? 'selected' : '' ?>>Validés</option>
                <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="annule" <?= $status === 'annule' ? 'selected' : '' ?>>Annulés</option>
                <option value="rejete" <?= $status === 'rejete' ? 'selected' : '' ?>>Rejetés</option>
            </select>
            <select name="sort" style="padding: 8px 12px; border-radius:30px; border:1px solid #ddd;">
                <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Tri : date récente</option>
                <option value="statut" <?= $sort === 'statut' ? 'selected' : '' ?>>Tri : statut</option>
            </select>
            <button type="submit" style="background:#f0f0f0; border:none; padding:8px 16px; border-radius:30px; cursor:pointer;">Filtrer</button>
            <a href="admin_events.php" style="background:#f0f0f0; color:#333; padding:8px 16px; border-radius:30px; text-decoration:none;">Reset</a>
        </form>
    </div>
    
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
            <h3>Aucun événement trouvé</h3>
            <p>Créez un événement avec le bouton ci-dessus.</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($filtered as $ev): 
                $title = $prestationsMap[(int)($ev['id_prestation'] ?? 0)] ?? 'Prestation inconnue';
                $st = (string)($ev['statut'] ?? '');
                $cid = (int)($ev['id_createur'] ?? 0);
                $creatorName = $cid ? ($userPseudo[$cid] ?? '#' . $cid) : 'Admin';
                $inscritsCount = $inscritsMap[(int)($ev['id_session'] ?? 0)] ?? 0;
                
                $imageUrl = '/upcycle/' . ($imageMap[(int)($ev['id_session'] ?? 0)] ?? '');
                if (empty($imageMap[(int)($ev['id_session'] ?? 0)])) {
                    if (str_contains(mb_strtolower($title), 'bois')) {
                        $imageUrl = 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=600&q=80';
                    } elseif (str_contains(mb_strtolower($title), 'velo')) {
                        $imageUrl = 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=600&q=80';
                    } else {
                        $imageUrl = 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=600&q=80';
                    }
                }
            ?>
                <div class="event-card" onclick='openEventZoom(<?= htmlspecialchars(json_encode([
                    'id' => $ev['id_session'] ?? 0,
                    'title' => $title,
                    'date_debut' => formatDateFr($ev['date_debut'] ?? ''),
                    'date_fin' => formatDateFr($ev['date_fin'] ?? ''),
                    'lieu' => $ev['lieu'] ?? 'Lieu non défini',
                    'statut' => $st,
                    'statut_text' => event_status_text($st),
                    'capacite' => (int)($ev['capacite_max'] ?? 0),
                    'inscrits' => $inscritsCount,
                    'creator' => $creatorName,
                    'image_url' => $imageUrl,
                    'date_debut_raw' => date('Y-m-d\TH:i', strtotime($ev['date_debut'] ?? 'now')),
                    'date_fin_raw' => date('Y-m-d\TH:i', strtotime($ev['date_fin'] ?? 'now')),
                ]), JSON_HEX_TAG) ?>)'>
                    <img class="event-card-image" src="<?= $imageUrl ?>" alt="<?= e($title) ?>">
                    <span class="event-card-badge <?= event_status_badge_class($st) ?>"><?= event_status_text($st) ?></span>
                    <div class="event-card-content">
                        <h3 class="event-card-title"><?= e(mb_substr($title, 0, 40)) ?></h3>
                        <div class="event-card-date">📅 <?= formatDateFr($ev['date_debut'] ?? '') ?></div>
                        <div class="event-card-lieu">📍 <?= e(mb_substr($ev['lieu'] ?? 'Lieu non défini', 0, 35)) ?></div>
                        <div class="event-card-creator">👤 Créé par : <?= e($creatorName) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- MODAL CRÉATION -->
<div id="createModal" class="modal-event-zoom" onclick="closeCreateModal()">
    <div class="modal-event-content" onclick="event.stopPropagation()">
        <span class="modal-event-close" onclick="closeCreateModal()">&times;</span>
        <div class="modal-event-image-container" style="background: linear-gradient(135deg, #2e7d32, #4caf50); min-height:150px;">
            <div style="text-align:center; padding:40px 20px;">
                <div class="big-icon" style="font-size:64px;">➕</div>
                <h2 style="margin:16px 0 0 0; color:white;">Créer un événement</h2>
            </div>
        </div>
        <div class="modal-event-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_event" value="1">
                <div class="modal-form-group">
                    <label>📋 Prestation</label>
                    <select name="prestation_id" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($prestations as $p): ?>
                            <option value="<?= $p['id_prestation'] ?>"><?= e($p['titre'] ?? '') ?> (<?= e($p['type'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>📅 Date de début</label>
                    <input type="datetime-local" name="date_debut" required>
                </div>
                <div class="modal-form-group">
                    <label>📅 Date de fin</label>
                    <input type="datetime-local" name="date_fin" required>
                </div>
                <div class="modal-form-group">
                    <label>📍 Lieu</label>
                    <input type="text" name="lieu" required placeholder="Salle, adresse...">
                </div>
                <div class="modal-form-group">
                    <label>👥 Capacité maximale</label>
                    <input type="number" name="capacite" min="1" value="20" required>
                </div>
                <div class="modal-form-group">
                    <label>✅ Statut</label>
                    <select name="statut">
                        <option value="valide">✅ Validé (visible immédiatement)</option>
                        <option value="en_attente">⏳ En attente</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label>🖼️ Image (optionnel)</label>
                    <input type="file" name="event_image" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-modal-success">🎯 Créer</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeCreateModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ZOOM MODIFICATION -->
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
            <div class="modal-info-row"><div class="modal-info-label">👤 Créateur :</div><div class="modal-info-value" id="zoomCreator"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📌 Statut :</div><div class="modal-info-value" id="zoomStatut"></div></div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-primary" onclick="toggleEditMode()">✏️ Modifier</button>
                
                <?php // Bouton Valider : uniquement si l'événement n'est PAS déjà validé ?>
                <?php if ($st !== 'valide'): ?>
                    <button class="btn-modal btn-modal-warning" onclick="quickValidate()">✅ Valider</button>
                <?php endif; ?>
                
                <?php // Bouton Annuler/Supprimer : uniquement si l'événement n'est PAS déjà annulé ?>
                <?php if ($st !== 'annule'): ?>
                    <button class="btn-modal btn-modal-danger" id="deleteOrCancelBtn" onclick="confirmDeleteOrCancel()">
                        <?php if ($inscritsCount > 0): ?>❌ Annuler l'événement<?php else: ?>🗑️ Supprimer l'événement<?php endif; ?>
                    </button>
                <?php endif; ?>
                
                <button class="btn-modal btn-modal-secondary" onclick="closeEventZoom()">Fermer</button>
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
                        <option value="rejete">⚠️ Rejeté</option>
                        <option value="annule">❌ Annulé</option>
                    </select>
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
                    <button type="submit" class="btn-modal btn-modal-success">💾 Enregistrer</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="toggleEditMode()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentEventData = null;

function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

function openEventZoom(event) {
    currentEventData = event;
    
    document.getElementById('zoomTitle').textContent = event.title;
    document.getElementById('zoomDateDebut').textContent = event.date_debut;
    document.getElementById('zoomDateFin').textContent = event.date_fin;
    document.getElementById('zoomLieu').textContent = event.lieu;
    document.getElementById('zoomCapacite').textContent = event.inscrits + ' / ' + event.capacite + ' inscrits';
    document.getElementById('zoomCreator').textContent = event.creator;
    
    let statutHtml = '';
    if (event.statut === 'valide') statutHtml = '<span class="badge-valide" style="background:#e8f5e9; color:#2e7d32; padding:4px 12px; border-radius:30px;">✅ Validé</span>';
    else if (event.statut === 'en_attente') statutHtml = '<span class="badge-en_attente" style="background:#fff3e0; color:#ef6c00; padding:4px 12px; border-radius:30px;">⏳ En attente</span>';
    else if (event.statut === 'rejete') statutHtml = '<span class="badge-rejete" style="background:#fee2e2; color:#dc2626; padding:4px 12px; border-radius:30px;">⚠️ Rejeté</span>';
    else statutHtml = '<span class="badge-annule" style="background:#f5f5f5; color:#757575; padding:4px 12px; border-radius:30px;">❌ Annulé</span>';
    document.getElementById('zoomStatut').innerHTML = statutHtml;
    
    const modalImg = document.getElementById('modalEventImg');
    modalImg.src = event.image_url;
    modalImg.alt = event.title;
    
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
        previewDiv.innerHTML = '<small>Aucune image</small>';
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

function quickValidate() {
    if (currentEventData && currentEventData.statut !== 'valide') {
        if (confirm('Valider cet événement ? Il deviendra visible par les particuliers.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.enctype = 'multipart/form-data';
            var inputs = ['update_event', 'event_id', 'date_debut', 'date_fin', 'lieu', 'capacite_max', 'statut'];
            inputs.forEach(function(name) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                if (name === 'update_event') input.value = '1';
                else if (name === 'event_id') input.value = currentEventData.id;
                else if (name === 'date_debut') input.value = currentEventData.date_debut_raw;
                else if (name === 'date_fin') input.value = currentEventData.date_fin_raw;
                else if (name === 'lieu') input.value = currentEventData.lieu;
                else if (name === 'capacite_max') input.value = currentEventData.capacite;
                else if (name === 'statut') input.value = 'valide';
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
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
        input1.name = 'delete_event_id';
        input1.value = currentEventData.id;
        form.appendChild(input1);
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEventZoom();
        closeCreateModal();
    }
});
</script>

<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>