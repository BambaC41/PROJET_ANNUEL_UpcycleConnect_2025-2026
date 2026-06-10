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
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>Validation des annonces</h1>
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Titre</th><th>Mode</th><th>Prix</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($pendingAnnonces)): ?>
                <tr><td colspan="6" style="text-align:center;">Aucune annonce en attente.</td></tr>
            <?php else: foreach ($pendingAnnonces as $a): ?>
                <tr>
                    <td><?= e($a['id_annonce'] ?? '') ?></td>
                    <td><?= e($a['titre'] ?? '') ?></td>
                    <td><?= e($a['mode'] ?? '') ?></td>
                    <td><?= (($a['mode'] ?? '') === 'vente') ? e(number_format((float)($a['prix'] ?? 0), 2, ',', ' ')) . ' €' : 'Gratuit' ?></td>
                    <td><?= e(formatDateFr($a['created_at'] ?? '')) ?></td>
                    <td style="display:flex; gap:8px;">
                        <form method="POST">
                            <input type="hidden" name="moderate_annonce_id" value="<?= e($a['id_annonce'] ?? 0) ?>">
                            <input type="hidden" name="moderation_statut" value="validee">
                            <button class="btn-primary" type="submit">Valider</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="moderate_annonce_id" value="<?= e($a['id_annonce'] ?? 0) ?>">
                            <input type="hidden" name="moderation_statut" value="rejetee">
                            <button class="btn-danger" type="submit">Rejeter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</section>
</main>
</body>
</html>
