<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/local_db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: particulier_conseils.php');
    exit;
}

$conseil = api_get_conseil($id)['data'] ?? null;

if (!$conseil) {
    $conseil = db_safe_exec(function(PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('
            SELECT c.*, u.pseudo, u.email 
            FROM conseil c
            LEFT JOIN utilisateur u ON c.id_auteur = u.id_user
            WHERE c.id_conseil = ? AND c.is_active = TRUE
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }, null);
}

if (!$conseil) {
    header('Location: particulier_conseils.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= e($conseil['titre'] ?? 'Conseil') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <a class="btn-outline" href="particulier_conseils.php" style="margin-bottom:16px;">← Retour aux conseils</a>
        
        <?php if (!empty($conseil['image_url'])): ?>
            <img src="<?= e(vc_media_url($conseil['image_url'])) ?>" 
                 alt="<?= e($conseil['titre'] ?? 'Conseil') ?>" 
                 class="conseil-detail-hero">
        <?php endif; ?>
        
        <?php if (!empty($conseil['categorie'])): ?>
            <span class="badge-category"><?= e($conseil['categorie']) ?></span>
        <?php endif; ?>
        
        <div class="conseil-detail-header">
            <h1><?= e($conseil['titre'] ?? '') ?></h1>
            <div class="conseil-meta">
                <?php if (!empty($conseil['pseudo'])): ?>
                    <div class="conseil-meta-item">👤 <?= e($conseil['pseudo']) ?></div>
                <?php endif; ?>
                <div class="conseil-meta-item">📅 <?= e(formatDateFr($conseil['created_at'] ?? '')) ?></div>
            </div>
        </div>
        
        <div class="conseil-content">
            <?= nl2br(e($conseil['contenu'] ?? '')) ?>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a class="btn-outline" href="particulier_conseils.php">← Retour à la liste</a>
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>