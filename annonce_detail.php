<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/local_db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_message'] = 'Annonce introuvable.';
    $_SESSION['flash_type'] = 'error';
    header('Location: particulier_annonces.php');
    exit;
}

// Charger annonce depuis API ou BD locale
$annonce = api_get_annonce($id)['data'] ?? null;

// Fallback: chercher en base locale
if (!$annonce) {
    $annonce = db_safe_exec(function(PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('
            SELECT a.*, 
                   u.pseudo, u.email, u.telephone, u.adresse_ville,
                   o.titre as objet_titre, o.description as objet_desc, o.poids, o.type_materiau
            FROM annonce a
            LEFT JOIN utilisateur u ON a.id_user = u.id_user
            LEFT JOIN objet o ON a.id_objet = o.id_objet
            WHERE a.id_annonce = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }, null);
}

if (!$annonce) {
    $_SESSION['flash_message'] = 'Annonce non trouvée.';
    $_SESSION['flash_type'] = 'error';
    header('Location: particulier_annonces.php');
    exit;
}

// Vérifier si disponible ou statut
$isAvailable = ($annonce['statut'] ?? '') === 'validee' && empty($annonce['id_reserve_par']) && empty($annonce['id_acheteur']);
$statusBadge = '';
if (($annonce['statut'] ?? '') === 'validee' && !empty($annonce['id_acheteur'])) {
    $statusBadge = 'Vendue';
} elseif (($annonce['statut'] ?? '') === 'validee' && !empty($annonce['id_reserve_par'])) {
    $statusBadge = 'Réservée';
} elseif (($annonce['statut'] ?? '') !== 'validee') {
    $statusBadge = ($annonce['statut'] ?? 'En attente');
} else {
    $statusBadge = 'Disponible';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= e($annonce['titre'] ?? 'Annonce') ?> - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 20px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 0 16px;
        }
        .detail-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 12px;
            background: #f5f5f5;
        }
        .detail-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            width: fit-content;
        }
        .badge.available { background: #d4edda; color: #155724; }
        .badge.unavailable { background: #f8d7da; color: #721c24; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .seller-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 14px;
            background: #fafafa;
        }
        @media (max-width: 768px) {
            .detail-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <a class="btn-outline" href="particulier_annonces.php" style="margin-bottom:16px;">← Retour</a>
        
        <div class="detail-container">
            <!-- Image -->
            <div>
                <?php if (!empty($annonce['photo_url'])): ?>
                    <img src="<?= e(vc_media_url($annonce['photo_url'])) ?>" 
                         alt="<?= e($annonce['titre'] ?? 'Annonce') ?>" 
                         class="detail-image">
                <?php else: ?>
                    <div class="detail-image" style="display:flex;align-items:center;justify-content:center;background:#e9ecef;">
                        <span style="color:#999;">Pas d'image</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Infos -->
            <div class="detail-info">
                <div>
                    <h1 style="margin:0 0 8px 0;"><?= e($annonce['titre'] ?? '') ?></h1>
                    <span class="badge <?= $isAvailable ? 'available' : 'unavailable' ?>">
                        <?= e($statusBadge) ?>
                    </span>
                </div>

                <!-- Prix/Mode -->
                <div style="border-top:1px solid #ddd; padding-top:14px;">
                    <strong><?= e(($annonce['mode'] ?? '') === 'vente' ? 'Prix' : 'Type') ?></strong>
                    <p style="margin:4px 0;">
                        <?php if (($annonce['mode'] ?? '') === 'vente'): ?>
                            <strong style="font-size:18px;color:#28a745;">
                                <?= e(formatPriceEur($annonce['prix'] ?? 0)) ?>
                            </strong>
                            <br><small style="color:#999;">Mode : Vente</small>
                        <?php else: ?>
                            <strong style="font-size:18px;color:#007bff;">Gratuit (Don)</strong>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <strong>Description</strong>
                    <p><?= e($annonce['description'] ?? '') ?></p>
                </div>

                <!-- Détails objet si disponible -->
                <?php if (!empty($annonce['objet_titre'])): ?>
                    <div style="border-top:1px solid #ddd; padding-top:14px;">
                        <strong>Détails de l'objet</strong>
                        <ul style="margin:8px 0; padding-left:20px;">
                            <?php if (!empty($annonce['objet_titre'])): ?>
                                <li><?= e($annonce['objet_titre']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($annonce['type_materiau'])): ?>
                                <li>Matériau : <?= e($annonce['type_materiau']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($annonce['poids'])): ?>
                                <li>Poids : <?= e((float)$annonce['poids']) ?> kg</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Vendeur -->
                <div class="seller-card">
                    <strong>Proposé par</strong>
                    <p style="margin:8px 0; font-size:14px;">
                        <?= e($annonce['pseudo'] ?? $annonce['email'] ?? 'Utilisateur') ?>
                    </p>
                    <?php if (!empty($annonce['adresse_ville'])): ?>
                        <small style="color:#999;">📍 <?= e($annonce['adresse_ville']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Dates -->
                <div style="border-top:1px solid #ddd; padding-top:14px;">
                    <small style="color:#999;">
                        Publié le <?= e(formatDateFr($annonce['created_at'] ?? '')) ?>
                    </small>
                </div>
            </div>
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>
