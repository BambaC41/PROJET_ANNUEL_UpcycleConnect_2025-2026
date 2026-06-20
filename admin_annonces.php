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
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .pro-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .pro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .btn-success {
            background: #4caf50;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-success:hover {
            background: #2e7d32;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-voir-toutes {
            background: #2196f3;
            color: white;
            padding: 10px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-voir-toutes:hover {
            background: #1976d2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }
        .btn-voir-toutes .badge {
            background: rgba(255,255,255,0.3);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }
        .header-actions h1 {
            margin: 0;
        }
        .text-muted { color: #999; }
        .mt-20 { margin-top: 20px; }
        .text-center { text-align: center; }
        .row-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        .auteur-info {
            font-size: 12px;
            color: #555;
            margin: 4px 0 8px 0;
        }
        .auteur-info .email {
            color: #999;
            font-size: 11px;
        }
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-voir-toutes {
                justify-content: center;
            }
        }
    </style>
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
            <div style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 16px;border-radius:8px;margin-bottom:20px;text-align:center;">
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
                    <div class="pro-card" style="margin:0;">
                        <?php if (!empty($photo)): ?>
                            <img src="<?= e($photo) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" style="width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:12px;">
                        <?php else: ?>
                            <div style="width:100%;height:160px;background:#f0f0f0;border-radius:10px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;color:#999;">
                                📷 Pas d'image
                            </div>
                        <?php endif; ?>
                        
                        <h3 style="margin:0 0 8px 0;font-size:18px;"><?= e($a['titre'] ?? 'Sans titre') ?></h3>
                        <p class="auteur-info">
                            👤 <?= $auteur ?>
                        </p>
                        <p style="margin:0 0 8px 0;color:#666;font-size:13px;">
                            <?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?>
                        </p>
                        <p style="margin:0;">
                            <strong><?= $mode === 'don' ? '🎁 Don' : '💰 Vente' ?></strong> - 
                            <?= $mode === 'vente' ? e(formatPriceEur($prix)) : 'Gratuit' ?>
                            <br>
                            <span style="font-size:12px;color:#ef6c00;">⏳ En attente</span>
                            <span style="font-size:12px;color:#999;margin-left:8px;">📅 <?= e(formatDateFr($a['created_at'] ?? '')) ?></span>
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