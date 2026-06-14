<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/events.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Événement invalide.'];
    header('Location: admin_events.php');
    exit;
}

$cur = api_get_event($_SESSION['token'], $id);
if (($cur['status'] ?? 0) !== 200 || !is_array($cur['data'] ?? null)) {
    $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Événement introuvable.'];
    header('Location: admin_events.php');
    exit;
}
$data = $cur['data'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $payload = $data;
    $payload['lieu'] = trim((string)($_POST['lieu'] ?? ''));
    $payload['date_debut'] = trim((string)($_POST['date_debut'] ?? ''));
    $payload['date_fin'] = trim((string)($_POST['date_fin'] ?? ''));
    $payload['capacite_max'] = (int)($_POST['capacite_max'] ?? 0);
    $payload['statut'] = trim((string)($_POST['statut'] ?? $payload['statut'] ?? 'en_attente'));
    $res = api_update_event($_SESSION['token'], $id, $payload);
    $ok = (($res['status'] ?? 0) === 200);
    $_SESSION['flash_toast'] = $ok
        ? ['type' => 'success', 'message' => 'Événement mis à jour.']
        : ['type' => 'error', 'message' => 'Impossible de mettre à jour l\'événement.'];
    require_once __DIR__ . '/includes/functions/local_db.php';
    if ($ok) {
        db_safe_exec(function (PDO $pdo) use ($id): void {
            $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "event", ?, ?, NOW())');
            $audit->execute([(int)($_SESSION['user_id'] ?? 0), 'UPDATE_EVENT', $id, 'Modification événement admin']);
        }, null);
    }
    header('Location: admin_events.php');
    exit;
}

$prestations = api_get_prestations($_SESSION['token']);
$prestationsMap = [];
foreach ($prestations as $p) {
    $prestationsMap[(int)($p['id_prestation'] ?? 0)] = $p['titre'] ?? '';
}
$title = $prestationsMap[(int)($data['id_prestation'] ?? 0)] ?? 'Prestation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Modifier événement</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>✏️ Modifier événement #<?= e((string)$id) ?></h1>
        <p class="muted"><?= e($title) ?></p>
        <form method="POST" class="form-grid" style="max-width:640px;">
            <input type="hidden" name="save_event" value="1">
            <div>
                <label>Lieu</label>
                <input class="input" name="lieu" value="<?= e((string)($data['lieu'] ?? '')) ?>" required>
            </div>
            <div>
                <label>Date début</label>
                <?php $tsDebut = strtotime((string)($data['date_debut'] ?? '')); ?>
                <input class="input" type="datetime-local" name="date_debut" value="<?= e(date('Y-m-d\TH:i', $tsDebut !== false ? $tsDebut : time())) ?>">
            </div>
            <div>
                <label>Date fin</label>
                <?php $tsFin = strtotime((string)($data['date_fin'] ?? '')); ?>
                <input class="input" type="datetime-local" name="date_fin" value="<?= e(date('Y-m-d\TH:i', $tsFin !== false ? $tsFin : time())) ?>">
            </div>
            <div>
                <label>Capacité max</label>
                <input class="input" type="number" name="capacite_max" value="<?= e((string)(int)($data['capacite_max'] ?? 0)) ?>">
            </div>
            <div>
                <label>Statut</label>
                <select class="input" name="statut">
                    <?php foreach (['en_attente', 'valide', 'rejete', 'annule'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= (($data['statut'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row-actions" style="margin-top:16px;">
                <button class="btn-primary" type="submit">💾 Enregistrer</button>
                <a class="btn-outline" href="admin_events.php">❌ Annuler</a>
            </div>
        </form>
    </section>
</main>
<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>