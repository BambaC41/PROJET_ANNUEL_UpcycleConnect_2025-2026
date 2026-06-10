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
    <style>
        .conseil-detail-hero {
            width: 100%;
            max-height: 400px;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 30px;
            background: #f5f5f5;
        }
        .conseil-detail-header {
            margin-bottom: 20px;
        }
        .conseil-detail-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        .conseil-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #666;
            margin-top: 12px;
        }
        .conseil-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .conseil-content {
            font-size: 15px;
            line-height: 1.7;
            color: #333;
            margin: 20px 0;
        }
        .badge-category {
            display: inline-block;
            padding: 6px 12px;
            background: #e7f3ff;
            color: #0c5460;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <a class="btn-outline" href="particulier_conseils.php" style="margin-bottom:16px;">← Retour aux conseils</a>
        
        <!-- Image -->
        <?php if (!empty($conseil['image_url'])): ?>
            <img src="<?= e(vc_media_url($conseil['image_url'])) ?>" 
                 alt="<?= e($conseil['titre'] ?? 'Conseil') ?>" 
                 class="conseil-detail-hero">
        <?php endif; ?>
        
        <!-- Catégorie -->
        <?php if (!empty($conseil['categorie'])): ?>
            <span class="badge-category"><?= e($conseil['categorie']) ?></span>
        <?php endif; ?>
        
        <!-- Titre et meta -->
        <div class="conseil-detail-header">
            <h1><?= e($conseil['titre'] ?? '') ?></h1>
            <div class="conseil-meta">
                <?php if (!empty($conseil['pseudo'])): ?>
                    <div class="conseil-meta-item">👤 <?= e($conseil['pseudo']) ?></div>
                <?php endif; ?>
                <div class="conseil-meta-item">📅 <?= e(formatDateFr($conseil['created_at'] ?? '')) ?></div>
            </div>
        </div>
        
        <!-- Contenu -->
        <div class="conseil-content">
            <?= nl2br(e($conseil['contenu'] ?? '')) ?>
        </div>
        
        <!-- Footer -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a class="btn-outline" href="particulier_conseils.php">← Retour à la liste</a>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>
