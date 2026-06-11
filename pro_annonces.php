<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/pro_bootstrap.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';

$myId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reserve_don_id'])) {
    $aid = (int)$_POST['reserve_don_id'];
    $ok = (bool)db_safe_exec(function (PDO $pdo) use ($aid, $myId) {
        $stmt = $pdo->prepare('UPDATE annonce SET id_reserve_par = ?, date_reserve = NOW() WHERE id_annonce = ? AND mode = "don" AND statut = "validee" AND id_reserve_par IS NULL AND id_user <> ?');
        $stmt->execute([$myId, $aid, $myId]);
        return $stmt->rowCount() > 0;
    }, false);
    if ($ok) {
        notif_create($myId, 'annonce', 'Don réservé', "Vous avez réservé l'annonce don n°" . $aid . ' (démonstration).');
        notif_notify_roles([1], 'annonce', 'Réservation don', "Le professionnel #" . $myId . " a réservé l'annonce don #" . $aid . '.');
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Réservation enregistrée.'];
    } else {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Impossible de réserver (déjà pris, ou annonce non disponible).'];
    }
    header('Location: pro_annonces.php');
    exit;
}

$mode = trim((string)($_GET['mode'] ?? 'all'));
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$annonces = api_get_annonces()['data'] ?? [];

$locks = (array)db_safe_exec(static function (PDO $pdo) {
    $stmt = $pdo->query('SELECT id_annonce, id_reserve_par, id_acheteur FROM annonce');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}, []);
$lockById = [];
foreach ($locks as $row) {
    $lockById[(int)($row['id_annonce'] ?? 0)] = $row;
}

foreach ($annonces as &$a) {
    $id = (int)($a['id_annonce'] ?? 0);
    if (isset($lockById[$id])) {
        $a['_id_reserve_par'] = $lockById[$id]['id_reserve_par'] ?? null;
        $a['_id_acheteur'] = $lockById[$id]['id_acheteur'] ?? null;
    }
}
unset($a);

$filtered = array_values(array_filter($annonces, function ($a) use ($mode, $q) {
    $okMode = ($mode === 'all') || (($a['mode'] ?? '') === $mode);
    $okQ = ($q === '') || str_contains(mb_strtolower((string)($a['titre'] ?? '')), $q) || str_contains(mb_strtolower((string)($a['description'] ?? '')), $q);
    return $okMode && $okQ;
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Marketplace - Annonces</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        .annonce-card {
            position: relative;
            padding: 0;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .annonce-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .annonce-img-container {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #f0f0f0;
        }
        .annonce-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .annonce-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        .badge-available {
            background: #4caf50;
        }
        .badge-reserved {
            background: #ff9800;
        }
        .badge-sold {
            background: #999;
        }
        .annonce-content {
            padding: 12px;
        }
        .annonce-title {
            margin: 0 0 6px 0;
            font-size: 14px;
            font-weight: bold;
        }
        .annonce-desc {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        .annonce-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .annonce-footer span {
            font-weight: bold;
        }
        .annonce-price {
            color: #2196f3;
        }
        .annonce-actions {
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .grayscale {
            filter: grayscale(40%);
            opacity: 0.85;
        }
    </style>
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1>🏪 Marketplace - Annonces & Récupérations</h1>
        <p class="muted" style="margin-bottom:14px;">Consultez les objets disponibles pour don ou achat. Réservez des dons ou simulez des achats.</p>
        
        <form method="GET" class="row-actions" style="gap: 10px; flex-wrap: wrap; margin-bottom: 14px;">
            <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un objet..." style="flex:1;min-width:200px;">
            <select class="input" name="mode">
                <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>Tous les types</option>
                <option value="don" <?= $mode === 'don' ? 'selected' : '' ?>>Don (gratuit)</option>
                <option value="vente" <?= $mode === 'vente' ? 'selected' : '' ?>>Vente (payant)</option>
            </select>
            <button class="btn-outline" type="submit">🔍 Filtrer</button>
        </form>
        
        <?php if (empty($filtered)): ?>
            <div style="padding: 30px; text-align: center; color: #999;">Aucune annonce ne correspond à votre recherche.</div>
        <?php else: ?>
            <div class="pro-grid" style="gap: 14px;">
                <?php foreach ($filtered as $a): ?>
                    <?php
                    $aid = (int)($a['id_annonce'] ?? 0);
                    $owner = (int)($a['id_user'] ?? 0);
                    $isDon = ($a['mode'] ?? '') === 'don';
                    $isVente = ($a['mode'] ?? '') === 'vente';
                    $prix = (float)($a['prix'] ?? 0);
                    $res = isset($a['_id_reserve_par']) ? (int)$a['_id_reserve_par'] : 0;
                    $buyer = isset($a['_id_acheteur']) ? (int)$a['_id_acheteur'] : 0;
                    
                    $isAvailable = ($res === 0 && $buyer === 0);
                    $isReservedByMe = ($res === $myId);
                    $isBoughtByMe = ($buyer === $myId);
                    
                    $badgeClass = $buyer > 0 ? 'badge-sold' : ($res > 0 ? 'badge-reserved' : 'badge-available');
                    $badgeText = $buyer > 0 ? 'Vendu' : ($res > 0 ? 'Réservé' : 'Disponible');
                    ?>
                    <article class="annonce-card">
                        <div class="annonce-img-container">
                            <?php if (!empty($a['photo_url'])): ?>
                                <img src="<?= e(vc_media_url($a['photo_url'])) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" class="<?= ($buyer > 0 || $res > 0) ? 'grayscale' : '' ?>">
                            <?php else: ?>
                                <div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">Pas d'image</div>
                            <?php endif; ?>
                            <span class="annonce-badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                        </div>
                        <div class="annonce-content">
                            <h3 class="annonce-title"><?= e($a['titre'] ?? '') ?></h3>
                            <p class="annonce-desc"><?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?></p>
                            <div class="annonce-footer">
                                <span><?= $isDon ? '🎁 Don' : '💰 Vente' ?></span>
                                <span class="annonce-price"><?= $isVente ? e(formatPriceEur($prix)) : 'Gratuit' ?></span>
                            </div>
                            <div class="annonce-actions">
                                <?php if ($owner === $myId): ?>
                                    <span class="muted" style="font-size:11px;">Votre annonce</span>
                                <?php elseif ($isDon): ?>
                                    <?php if ($res > 0): ?>
                                        <?php if ($isReservedByMe): ?>
                                            <span style="font-size:11px;color:#4caf50;font-weight:bold;">✓ Réservé par vous</span>
                                        <?php else: ?>
                                            <span class="muted" style="font-size:11px;">Déjà réservé</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="reserve_don_id" value="<?= $aid ?>">
                                            <button class="btn-primary" type="submit" style="font-size:11px;padding:6px 10px;">📦 Réserver</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($isVente): ?>
                                    <?php if ($buyer > 0): ?>
                                        <?php if ($isBoughtByMe): ?>
                                            <span style="font-size:11px;color:#4caf50;font-weight:bold;">✓ Acheté</span>
                                        <?php else: ?>
                                            <span class="muted" style="font-size:11px;">Déjà vendu</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a class="btn-outline" href="paiement_checkout_demo.php?<?= e(http_build_query([
                                            'payment_type' => 'achat_annonce',
                                            'related_id' => $aid,
                                            'amount' => $prix,
                                            'label' => 'Achat annonce #' . $aid,
                                        ])) ?>" style="font-size:11px;padding:6px 10px;text-decoration:none;">💳 Acheter</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
