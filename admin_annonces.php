<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_annonce_id'], $_POST['moderation_statut'])) {
    $annonceId = (int)$_POST['moderate_annonce_id'];
    $newStatus = trim((string)$_POST['moderation_statut']);
    api_moderate_annonce($annonceId, $newStatus);
    db_safe_exec(function(PDO $pdo) use ($annonceId, $newStatus) {
        $stmt = $pdo->prepare('SELECT id_user FROM annonce WHERE id_annonce = ?');
        $stmt->execute([$annonceId]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId > 0) {
            $title = $newStatus === 'validee' ? 'Annonce validee' : 'Annonce rejetee';
            $msg = $newStatus === 'validee' ? 'Votre annonce a ete validee par l administration.' : 'Votre annonce a ete rejetee.';
            notif_create($ownerId, 'annonce', $title, $msg);
        }
        $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "annonce", ?, ?, NOW())');
        $audit->execute([(int)$_SESSION['user_id'], strtoupper($newStatus) . '_ANNONCE', $annonceId, 'Moderation annonce']);
    });
    header('Location: admin_annonces.php');
    exit();
}

$pendingRes = api_get_pending_annonces();
$pendingAnnonces = (($pendingRes['status'] ?? 0) === 200 && is_array($pendingRes['data'] ?? null)) ? $pendingRes['data'] : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Validation des annonces</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>📦 Validation des annonces</h1>
        
        <?php if (empty($pendingAnnonces)): ?>
            <div class="success-box" style="text-align:center;">✅ Aucune annonce en attente de validation.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Titre</th><th>Mode</th><th>Prix</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingAnnonces as $a): ?>
                        <tr>
                            <td><?= e($a['id_annonce'] ?? '') ?></td>
                            <td><strong><?= e($a['titre'] ?? '') ?></strong></td>
                            <td><?= e($a['mode'] ?? '') ?></td>
                            <td><?= (($a['mode'] ?? '') === 'vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></td>
                            <td><?= e(formatDateFr($a['created_at'] ?? '')) ?></td>
                            <td class="row-actions" style="gap:8px;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="moderate_annonce_id" value="<?= e($a['id_annonce'] ?? 0) ?>">
                                    <input type="hidden" name="moderation_statut" value="validee">
                                    <button class="btn-success" type="submit">✅ Valider</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="moderate_annonce_id" value="<?= e($a['id_annonce'] ?? 0) ?>">
                                    <input type="hidden" name="moderation_statut" value="rejetee">
                                    <button class="btn-danger" type="submit">❌ Rejeter</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>