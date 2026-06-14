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

function normalize_datetime(string $raw): string {
    $s = str_replace('T', ' ', trim($raw));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
        return $s . ':00';
    }
    return $s;
}

$formErrors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $dateDebut = normalize_datetime((string)($_POST['date_debut'] ?? ''));
    $dateFin = normalize_datetime((string)($_POST['date_fin'] ?? ''));
    $idPrestation = (int)($_POST['id_prestation'] ?? 0);
    $lieu = trim((string)($_POST['lieu'] ?? ''));
    $capacite = (int)($_POST['capacite_max'] ?? 0);
    $imageUrl = '';
    
    if ($idPrestation <= 0) $formErrors[] = "Veuillez sélectionner une prestation.";
    if (empty($dateDebut)) $formErrors[] = "La date de début est requise.";
    if (empty($dateFin)) $formErrors[] = "La date de fin est requise.";
    if (empty($lieu)) $formErrors[] = "Le lieu est requis.";
    if ($capacite <= 0) $formErrors[] = "La capacité doit être supérieure à 0.";
    if (strtotime($dateFin) <= strtotime($dateDebut)) $formErrors[] = "La date de fin doit être postérieure à la date de début.";
    
    // Gestion upload d'image
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
    
    if (empty($formErrors)) {
        $payload = [
            'id_prestation' => $idPrestation,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'lieu' => $lieu,
            'capacite_max' => $capacite,
            'statut' => 'en_attente',
            'image_url' => $imageUrl
        ];
        
        $res = api_create_event($token, $payload);
        
        if (($res['status'] ?? 0) === 201) {
            notif_create($userId, 'event', 'Événement soumis', 'Votre événement a été soumis et est en attente de validation.');
            notif_notify_roles([1], 'event', 'Événement à valider', 'Un salarié a soumis un nouvel événement.');
            toast_redirect('salarie_events.php', 'success', '✅ Événement créé avec succès !');
        }
        
        $localId = (int)db_safe_exec(function (PDO $pdo) use ($payload, $userId, $imageUrl) {
            $st = $pdo->prepare('INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, id_prestation, id_validateur, id_createur, image_url) VALUES (?, ?, ?, ?, "en_attente", ?, NULL, ?, ?)');
            $st->execute([$payload['date_debut'], $payload['date_fin'], $payload['lieu'], $payload['capacite_max'], $payload['id_prestation'], $userId, $imageUrl]);
            return (int)$pdo->lastInsertId();
        }, 0);
        
        if ($localId > 0) {
            notif_create($userId, 'event', 'Événement enregistré', 'Session #' . $localId . ' créée en attente.');
            notif_notify_roles([1], 'event', 'Événement à valider', 'Événement créé localement par un salarié.');
            toast_redirect('salarie_events.php', 'success', '✅ Événement créé avec succès !');
        }
        
        $formErrors[] = "Création impossible : " . ($res['error'] ?? 'Erreur inconnue.');
    }
    
    $formData = $_POST;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un événement - Espace Salarié</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        .create-page { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #666; text-decoration: none; margin-bottom: 24px; font-size: 14px; }
        .back-link:hover { color: #2e7d32; }
        .create-card { background: white; border-radius: 24px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .create-card h1 { margin: 0 0 8px 0; font-size: 28px; color: #1a1a2e; }
        .create-card .subtitle { color: #666; font-size: 14px; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #333; }
        .form-group label .required { color: #dc2626; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #4caf50; box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .error-box { background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; }
        .info-box { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; padding: 14px 18px; border-radius: 12px; margin: 24px 0; font-size: 13px; }
        .btn-submit { background: #4caf50; color: white; width: 100%; padding: 14px; border: none; border-radius: 40px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #2e7d32; }
        .btn-secondary { background: #f0f0f0; color: #333; width: 100%; padding: 14px; border: none; border-radius: 40px; font-size: 14px; cursor: pointer; text-align: center; text-decoration: none; display: block; margin-top: 12px; }
        .btn-secondary:hover { background: #e0e0e0; }
        .image-preview { max-width: 100%; max-height: 150px; margin-top: 10px; border-radius: 12px; }
        
        @media (max-width: 640px) {
            .create-page { padding: 20px 16px; }
            .create-card { padding: 24px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>

<main class="create-page">
    <a href="salarie_events.php" class="back-link">← Retour à mes événements</a>
    
    <div class="create-card">
        <h1>➕ Créer un événement</h1>
        <div class="subtitle">Atelier, formation ou animation UpcycleConnect</div>
        
        <?php if (!empty($formErrors)): ?>
            <div class="error-box">
                <?php foreach ($formErrors as $err): ?>
                    <div>❌ <?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="create_event" value="1">
            
            <div class="form-group">
                <label>📋 Type de prestation <span class="required">*</span></label>
                <select name="id_prestation" required>
                    <option value="">-- Sélectionner une prestation --</option>
                    <?php foreach ($prestations as $p): ?>
                        <option value="<?= (int)($p['id_prestation'] ?? 0) ?>" 
                            <?= (($formData['id_prestation'] ?? '') == ($p['id_prestation'] ?? '')) ? 'selected' : '' ?>>
                            <?= e($p['titre'] ?? 'Prestation') ?> (<?= e($p['type'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>📅 Date et heure de début <span class="required">*</span></label>
                    <input type="datetime-local" name="date_debut" required value="<?= e($formData['date_debut'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>📅 Date et heure de fin <span class="required">*</span></label>
                    <input type="datetime-local" name="date_fin" required value="<?= e($formData['date_fin'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>📍 Lieu <span class="required">*</span></label>
                <input type="text" name="lieu" placeholder="Ex: 174 rue La Fayette, 75010 Paris" required value="<?= e($formData['lieu'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>👥 Capacité maximale <span class="required">*</span></label>
                <input type="number" name="capacite_max" min="1" placeholder="Nombre de participants" required value="<?= e($formData['capacite_max'] ?? '20') ?>">
            </div>
            
            <div class="form-group">
                <label>🖼️ Image (optionnel)</label>
                <input type="file" name="event_image" accept="image/jpeg,image/png,image/webp">
                <small class="muted">Formats acceptés : JPG, PNG, WEBP. Taille max : 5 Mo.</small>
            </div>
            
            <div class="info-box">
                ⚠️ <strong>Information importante</strong><br>
                L'événement sera créé avec le statut <strong>"en attente"</strong>. 
                Il devra être validé par un responsable avant d'être visible par les particuliers.
            </div>
            
            <button type="submit" class="btn-submit">📤 Soumettre l'événement</button>
            <a href="salarie_events.php" class="btn-secondary">Annuler</a>
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>