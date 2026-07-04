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

if (!empty($pendingAnnonces)) {
    $userIds = array_column($pendingAnnonces, 'id_user');
    $userIds = array_unique(array_filter($userIds));
    
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $users = (array)db_safe_exec(function(PDO $pdo) use ($userIds, $placeholders) {
            $stmt = $pdo->prepare("SELECT id_user, pseudo, email FROM utilisateur WHERE id_user IN ($placeholders)");
            $stmt->execute($userIds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, []);
        
        $userMap = [];
        foreach ($users as $u) {
            $userMap[(int)$u['id_user']] = $u;
        }
        
        foreach ($pendingAnnonces as &$a) {
            $uid = (int)($a['id_user'] ?? 0);
            if (isset($userMap[$uid])) {
                $a['_pseudo'] = $userMap[$uid]['pseudo'] ?? null;
                $a['_email'] = $userMap[$uid]['email'] ?? null;
            }
        }
        unset($a);
    }
}

$totalAnnonces = (int)db_safe_exec(function (PDO $pdo) {
    $st = $pdo->query("SELECT COUNT(*) FROM annonce");
    return (int)$st->fetchColumn();
}, 0);
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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>

<main class="pro-shell page-shell">
    <section class="pro-card">
        <div class="header-actions">
            <h1>📦 Validation des annonces</h1>
            <a href="admin_annonces_all.php" class="btn-voir-toutes">
                📋 Voir toutes les annonces
                <span class="badge"><?= $totalAnnonces ?></span>
            </a>
        </div>
        
        <?php if (empty($pendingAnnonces)): ?>
            <div class="success-box" style="text-align:center;">
                ✅ Aucune annonce en attente de validation.
            </div>
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>Toutes les annonces ont été traitées.</p>
                <a href="admin_annonces_all.php" class="btn-voir-toutes" style="display:inline-flex;margin-top:12px;">
                    📋 Voir toutes les annonces
                    <span class="badge"><?= $totalAnnonces ?></span>
                </a>
            </div>
        <?php else: ?>
            <div class="pro-grid">
                <?php foreach ($pendingAnnonces as $a): 
                    $pseudo = $a['_pseudo'] ?? $a['pseudo'] ?? null;
                    $email = $a['_email'] ?? $a['email'] ?? null;
                    
                    if (!empty($pseudo) && !empty($email)) {
                        $auteur = $pseudo . ' <span class="email">(' . $email . ')</span>';
                    } elseif (!empty($pseudo)) {
                        $auteur = $pseudo;
                    } elseif (!empty($email)) {
                        $auteur = $email;
                    } else {
                        $auteur = '#' . ($a['id_user'] ?? '?');
                    }
                    
                    $photo = !empty($a['photo_url']) ? vc_media_url($a['photo_url']) : '';
                    $mode = (string)($a['mode'] ?? 'don');
                    $prix = (float)($a['prix'] ?? 0);
                ?>
                    <div class="pro-card-item" style="margin:0;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?= e($photo) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" class="annonce-image">
                        <?php else: ?>
                            <div class="annonce-placeholder">
                                📷 Pas d'image
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="annonce-title"><?= e($a['titre'] ?? 'Sans titre') ?></h3>
                        <p class="auteur-info">
                            👤 <?= $auteur ?>
                        </p>
                        <p class="annonce-description">
                            <?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?>
                        </p>
                        <p class="annonce-meta">
                            <strong><?= $mode === 'don' ? '🎁 Don' : '💰 Vente' ?></strong> - 
                            <?= $mode === 'vente' ? e(formatPriceEur($prix)) : 'Gratuit' ?>
                            <br>
                            <span class="status-badge status-warn">⏳ En attente</span>
                            <span class="annonce-date">📅 <?= e(formatDateFr($a['created_at'] ?? '')) ?></span>
                        </p>
                        
                        <div class="row-actions">
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-20">
                <a href="admin_annonces_all.php" class="btn-voir-toutes" style="display:inline-flex;">
                    📋 Voir toutes les annonces
                    <span class="badge"><?= $totalAnnonces ?></span>
                </a>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/flash_toast.php'; ?>
</body>
</html>