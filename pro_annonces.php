<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/pro_bootstrap.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';

$myId = (int)$_SESSION['user_id'];

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ============================================
// TRAITEMENT CREATION ANNONCE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_annonce'])) {
    $photo = '';
    if (isset($_FILES['photo_file']) && ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = api_upload_file('file', $_FILES['photo_file']['tmp_name'], $_FILES['photo_file']['name'], $_SESSION['token']);
        if (($up['status'] ?? 0) === 200 && !empty($up['data']['file_url'])) {
            $photo = $up['data']['file_url'];
        }
    }
    
    $modePost = trim((string)($_POST['mode'] ?? 'don'));
    $prixPost = (float)($_POST['prix'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    
    if (mb_strlen($description) > 200) {
        $_SESSION['flash_message'] = 'La description ne peut pas dépasser 200 caractères.';
        $_SESSION['flash_type'] = 'error';
        header('Location: pro_annonces.php');
        exit;
    }
    
    if ($modePost === 'vente' && $prixPost <= 0) {
        $_SESSION['flash_message'] = 'Pour une vente, le prix doit être supérieur à 0.';
        $_SESSION['flash_type'] = 'error';
        header('Location: pro_annonces.php');
        exit;
    }
    
    $payload = [
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'description' => $description,
        'mode' => $modePost,
        'prix' => ($modePost === 'vente') ? $prixPost : 0,
        'photo_url' => $photo
    ];
    
    $res = api_create_annonce($payload);
    $_SESSION['flash_message'] = (($res['status'] ?? 0) === 201) 
        ? 'Annonce envoyée, elle sera visible après validation.' 
        : '❌ Création annonce impossible.';
    $_SESSION['flash_type'] = (($res['status'] ?? 0) === 201) ? 'success' : 'error';
    header('Location: pro_annonces.php');
    exit;
}

// ============================================
// RÉSERVATION D'UN DON (MARKETPLACE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reserve_don_id'])) {
    $aid = (int)$_POST['reserve_don_id'];
    $ok = (bool)db_safe_exec(function (PDO $pdo) use ($aid, $myId) {
        $stmt = $pdo->prepare('UPDATE annonce SET id_reserve_par = ?, date_reserve = NOW() WHERE id_annonce = ? AND mode = "don" AND statut = "validee" AND id_reserve_par IS NULL AND id_user <> ?');
        $stmt->execute([$myId, $aid, $myId]);
        return $stmt->rowCount() > 0;
    }, false);
    if ($ok) {
        notif_create($myId, 'annonce', 'Don réservé', "Vous avez réservé l'annonce don n°" . $aid);
        notif_notify_roles([1], 'annonce', 'Réservation don', "Le professionnel #" . $myId . " a réservé l'annonce don #" . $aid);
        $_SESSION['flash_message'] = 'Réservation enregistrée.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Impossible de réserver (déjà pris, ou annonce non disponible).';
        $_SESSION['flash_type'] = 'error';
    }
    header('Location: pro_annonces.php');
    exit;
}

// ============================================
// RECUPERATION DES DONNEES
// ============================================
$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$mode = trim((string)($_GET['mode'] ?? 'all'));

// MES ANNONCES (celles que j'ai créées)
$myAnnonces = api_get_my_annonces()['data'] ?? [];

// ANNONCES PUBLIQUES (marketplace)
$publicAnnonces = api_get_annonces()['data'] ?? [];

// MES ACHATS
$myPurchases = (array)db_safe_exec(function (PDO $pdo) use ($myId) {
    $stmt = $pdo->prepare('
        SELECT a.*, o.titre, o.description, o.photo_url, u.pseudo AS vendeur_pseudo,
               a.date_achat, a.prix
        FROM annonce a
        JOIN objet o ON o.id_objet = a.id_objet
        JOIN utilisateur u ON u.id_user = a.id_user
        WHERE a.id_acheteur = ?
        ORDER BY a.date_achat DESC
    ');
    $stmt->execute([$myId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);

// Récupérer les infos de réservation/achat
$locks = (array)db_safe_exec(static function (PDO $pdo) {
    $stmt = $pdo->query('SELECT id_annonce, id_reserve_par, id_acheteur FROM annonce');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}, []);
$lockById = [];
foreach ($locks as $row) {
    $lockById[(int)($row['id_annonce'] ?? 0)] = $row;
}

foreach ($publicAnnonces as &$a) {
    $id = (int)($a['id_annonce'] ?? 0);
    if (isset($lockById[$id])) {
        $a['_id_reserve_par'] = $lockById[$id]['id_reserve_par'] ?? null;
        $a['_id_acheteur'] = $lockById[$id]['id_acheteur'] ?? null;
    }
}
unset($a);

// Filtrer mes annonces
$filteredMyAnnonces = array_values(array_filter($myAnnonces, function($a) use ($query, $mode) {
    $okQ = ($query === '') 
        || str_contains(mb_strtolower((string)($a['titre'] ?? '')), $query) 
        || str_contains(mb_strtolower((string)($a['description'] ?? '')), $query);
    $aMode = (string)($a['mode'] ?? 'don');
    $okM = ($mode === 'all') || ($aMode === $mode);
    return $okQ && $okM;
}));

// Filtrer les annonces publiques (marketplace)
$filteredPublic = array_values(array_filter($publicAnnonces, function($a) use ($query, $mode) {
    $okQ = ($query === '') 
        || str_contains(mb_strtolower((string)($a['titre'] ?? '')), $query) 
        || str_contains(mb_strtolower((string)($a['description'] ?? '')), $query);
    $aMode = (string)($a['mode'] ?? 'don');
    $okM = ($mode === 'all') || ($aMode === $mode);
    return $okQ && $okM;
}));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces professionnel - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .pro-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }
        .btn-primary {
            background: #4caf50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        .table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .pro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .annonce-clickable {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .annonce-clickable:hover {
            transform: scale(1.02);
        }
        .annonce-row {
            cursor: pointer;
        }
        .annonce-row:hover {
            background-color: #f5f5f5;
        }
        .char-counter {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            text-align: right;
        }
        .char-counter.warning {
            color: #f44336;
        }
        
        .marketplace-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            color: white;
        }
        .badge-available { background: #4caf50; }
        .badge-reserved { background: #ff9800; }
        .badge-sold { background: #999; }
        .badge-purchased { background: #2e7d32; }
        .annonce-card {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            transition: transform 0.2s;
        }
        .annonce-card:hover {
            transform: translateY(-3px);
        }
        .annonce-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .annonce-content {
            padding: 12px;
        }
        .grayscale {
            filter: grayscale(40%);
            opacity: 0.85;
        }
        
        /* MODAL */
        .modal-annonce {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal-annonce.active {
            display: flex;
        }
        .modal-annonce-content {
            background: white;
            border-radius: 20px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 0;
            position: relative;
            cursor: default;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .modal-annonce-close {
            position: absolute;
            top: 12px;
            right: 16px;
            cursor: pointer;
            font-size: 28px;
            color: white;
            z-index: 20;
            background: rgba(0,0,0,0.5);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-image-container {
            width: 100%;
            background: #1a1a2e;
            border-radius: 20px 20px 0 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            max-height: 300px;
        }
        .modal-annonce-img {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: contain;
            display: block;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-body h2 {
            margin: 0 0 20px 0;
            font-size: 24px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 12px;
        }
        .modal-info-row {
            display: flex;
            margin-bottom: 14px;
        }
        .modal-info-label {
            width: 100px;
            font-weight: 600;
            color: #555;
        }
        .modal-info-value {
            flex: 1;
            color: #333;
        }
        .modal-description-box {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            margin-top: 8px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .modal-actions {
            margin-top: 24px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            display: flex;
            gap: 12px;
        }
        .btn-modal {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        .btn-modal-primary { background: #2196f3; color: white; }
        .btn-modal-danger { background: #f44336; color: white; }
        .btn-modal-secondary { background: #9e9e9e; color: white; }
        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .badge-paid {
            background: #4caf50;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        .btn-create {
            background: #4caf50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-create:hover {
            background: #2e7d32;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>

<main class="pro-shell page-shell">
    <!-- SECTION CREATION ANNONCE -->
    <section class="pro-card">
        <h1>📦 Mes annonces</h1>
        
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>">
                <?= e($flash) ?>
            </div>
        <?php endif; ?>
        
        <h2 style="font-size:18px;">Créer une annonce</h2>
        <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <input type="hidden" name="create_annonce" value="1">
            
            <label>Titre</label>
            <input class="input" name="titre" placeholder="Titre" required maxlength="100">
            
            <label>Mode</label>
            <select class="input" id="mode" name="mode">
                <option value="don">Don</option>
                <option value="vente">Vente</option>
            </select>
            
            <div id="prix-wrap">
                <label>Prix (€)</label>
                <input class="input" id="prix" name="prix" type="number" step="0.01" min="0.01" placeholder="Prix">
            </div>
            
            <label style="grid-column:1/-1;">Photo</label>
            <input class="input" type="file" name="photo_file" accept="image/*" style="grid-column:1/-1;">
            
            <label style="grid-column:1/-1;">Description (max 200 caractères)</label>
            <textarea class="input" name="description" placeholder="Description" style="grid-column:1/-1;min-height:90px;" maxlength="200" oninput="updateCharCount(this)"></textarea>
            <div class="char-counter" id="descCounter">0 / 200 caractères</div>
            
            <button class="btn-create" type="submit" style="grid-column:1/-1;">➕ Publier</button>
        </form>
    </section>

    <!-- SECTION MES ANNONCES -->
    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">📋 Mes annonces</h2>
        
        <form method="GET" class="row-actions" style="margin-bottom:20px;">
            <input class="input" type="search" name="q" placeholder="Rechercher..." value="<?= e($query) ?>" style="flex:1;">
            <select class="input" name="mode" style="width:120px;">
                <option value="all" <?= $mode==='all'?'selected':'' ?>>Tous</option>
                <option value="don" <?= $mode==='don'?'selected':'' ?>>Don</option>
                <option value="vente" <?= $mode==='vente'?'selected':'' ?>>Vente</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Titre</th><th>Mode</th><th>Prix</th><th>Statut</th><th>Date</th><th>Commission</th></tr>
                </thead>
                <tbody>
                <?php foreach ($filteredMyAnnonces as $a): ?>
                    <?php 
                    $commission = (($a['mode'] ?? '') === 'vente' && ($a['statut'] ?? '') === 'validee') 
                        ? ($a['prix'] ?? 0) * 0.05 : 0; 
                    $statut = (string)($a['statut'] ?? '');
                    $statutLabel = match($statut) {
                        'en_attente' => '⏳ En attente',
                        'validee' => '✅ Validée',
                        'rejetee' => '❌ Rejetée',
                        default => $statut
                    };
                    $commissionPayee = ($a['commission_payee'] ?? 0) == 1;
                    ?>
                    <tr class="annonce-row" 
                        data-id="<?= $a['id_annonce'] ?? 0 ?>" 
                        data-titre="<?= e($a['titre'] ?? '') ?>"
                        data-description="<?= e(mb_substr($a['description'] ?? '', 0, 200)) ?>"
                        data-mode="<?= e($a['mode'] ?? '') ?>"
                        data-prix="<?= e($a['prix'] ?? 0) ?>"
                        data-statut="<?= $statutLabel ?>"
                        data-date="<?= e(formatDateFr($a['created_at'] ?? '')) ?>"
                        data-photo="<?= e(vc_media_url($a['photo_url'] ?? '')) ?>"
                        data-commission-payee="<?= $commissionPayee ? '1' : '0' ?>"
                        onclick="showMyAnnonceModal(this)">
                        <td><?= e($a['titre'] ?? '') ?></td>
                        <td><?= e($a['mode'] ?? '') ?></td>
                        <td><?= (($a['mode'] ?? '') === 'vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></td>
                        <td><?= $statutLabel ?></td>
                        <td><?= e(formatDateFr($a['created_at'] ?? '')) ?></td>
                        <td>
                            <?php if ($commission > 0 && $statut === 'validee'): ?>
                                <?php if ($commissionPayee): ?>
                                    <span class="badge-paid">✅ Commission payée</span>
                                <?php else: ?>
                                    <a class="btn-primary" href="paiement_stripe.php?amount=<?= $commission * 100 ?>&item=Commission+vente+<?= urlencode($a['titre'] ?? '') ?>&annonce_id=<?= $a['id_annonce'] ?>" onclick="event.stopPropagation();">
                                        Payer (<?= e(formatPriceEur($commission)) ?>)
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($commission > 0): ?>
                                <span class="badge-paid" style="background:#ff9800;">⏳ En attente validation</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($filteredMyAnnonces)): ?>
                    <tr><td colspan="6" style="text-align: center;">Aucune annonce créée pour le moment.<?php endif; ?>
                </tbody>
             </div>
        </div>
    </section>

    <!-- SECTION MES ACHATS -->
    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">🛍️ Mes achats</h2>
        
        <?php if (empty($myPurchases)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <h3>Aucun achat effectué</h3>
                <p>Les objets que vous achetez apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="pro-grid">
                <?php foreach ($myPurchases as $purchase): ?>
                    <div class="annonce-card" style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08); cursor:pointer;"
                         onclick='showMarketplaceAnnonceModal(<?= json_encode([
                             'titre' => $purchase['titre'] ?? '',
                             'description' => $purchase['description'] ?? '',
                             'mode' => 'vente',
                             'prix' => $purchase['prix'] ?? 0,
                             'photo_url' => vc_media_url($purchase['photo_url'] ?? ''),
                             'date' => formatDateFr($purchase['date_achat'] ?? ''),
                             'statut' => 'Acheté',
                             'vendeur' => $purchase['vendeur_pseudo'] ?? 'Inconnu'
                         ], JSON_HEX_TAG) ?>)'>
                        <?php if (!empty($purchase['photo_url'])): ?>
                            <img src="<?= e(vc_media_url($purchase['photo_url'])) ?>" class="annonce-img" style="height:140px;">
                        <?php else: ?>
                            <div style="height:140px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999;">📷 Pas d'image</div>
                        <?php endif; ?>
                        <div class="annonce-content">
                            <h3 style="margin:0 0 8px 0; font-size:16px;"><?= e($purchase['titre'] ?? '') ?></h3>
                            <p class="muted" style="font-size:12px; margin-bottom:8px;">
                                📦 Vendu par <strong><?= e($purchase['vendeur_pseudo'] ?? 'Inconnu') ?></strong>
                            </p>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="badge-paid">✅ Acheté</span>
                                <span style="font-weight:bold; color:#2e7d32;"><?= formatPriceEur($purchase['prix'] ?? 0) ?></span>
                            </div>
                            <div class="muted" style="font-size:11px; margin-top:8px;">
                                📅 Acheté le <?= formatDateFr($purchase['date_achat'] ?? '') ?>
                            </div>
                            <?php if (!empty($purchase['description'])): ?>
                                <details style="margin-top: 12px;">
                                    <summary style="font-size:11px; color:#666; cursor:pointer;">📝 Voir description</summary>
                                    <p style="font-size:12px; margin-top:8px; color:#555;"><?= e($purchase['description']) ?></p>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION MARKETPLACE (annonces publiques) -->
    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">🌍 Marketplace - Annonces disponibles</h2>
        
        <?php if (empty($filteredPublic)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <h3>Aucune annonce disponible</h3>
                <p>Revenez plus tard pour découvrir de nouvelles annonces.</p>
            </div>
        <?php else: ?>
            <div class="pro-grid">
                <?php foreach ($filteredPublic as $a): 
                    $aid = (int)($a['id_annonce'] ?? 0);
                    $owner = (int)($a['id_user'] ?? 0);
                    $isDon = ($a['mode'] ?? '') === 'don';
                    $isVente = ($a['mode'] ?? '') === 'vente';
                    $prix = (float)($a['prix'] ?? 0);
                    $res = isset($a['_id_reserve_par']) ? (int)$a['_id_reserve_par'] : 0;
                    $buyer = isset($a['_id_acheteur']) ? (int)$a['_id_acheteur'] : 0;
                    
                    $badgeClass = $buyer > 0 ? 'badge-sold' : ($res > 0 ? 'badge-reserved' : 'badge-available');
                    $badgeText = $buyer > 0 ? 'Vendu' : ($res > 0 ? 'Réservé' : 'Disponible');
                    $isMyAnnonce = ($owner === $myId);
                    ?>
                    <div class="annonce-card" 
                         style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08); position:relative; cursor:pointer;"
                         onclick='showMarketplaceAnnonceModal(<?= json_encode([
                             'titre' => $a['titre'] ?? '',
                             'description' => $a['description'] ?? '',
                             'mode' => $a['mode'] ?? '',
                             'prix' => $a['prix'] ?? 0,
                             'photo_url' => vc_media_url($a['photo_url'] ?? ''),
                             'date' => formatDateFr($a['created_at'] ?? ''),
                             'statut' => $a['statut'] ?? 'Validée'
                         ], JSON_HEX_TAG) ?>)'>
                        <div style="position:relative;">
                            <?php if (!empty($a['photo_url'])): ?>
                                <img src="<?= e(vc_media_url($a['photo_url'])) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" class="annonce-img <?= ($buyer > 0 || $res > 0) ? 'grayscale' : '' ?>">
                            <?php else: ?>
                                <div style="width:100%;height:160px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;">📷 Pas d'image</div>
                            <?php endif; ?>
                            <span class="marketplace-badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                        </div>
                        <div class="annonce-content">
                            <h3 style="margin:0 0 8px 0;font-size:16px;"><?= e($a['titre'] ?? '') ?></h3>
                            <p class="muted" style="font-size:12px; margin-bottom:8px;"><?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?></p>
                            <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                <span><?= $isDon ? '🎁 Don' : '💰 Vente' ?></span>
                                <span style="font-weight:bold; color:#2196f3;"><?= $isVente ? e(formatPriceEur($prix)) : 'Gratuit' ?></span>
                            </div>
                            <?php if (!$isMyAnnonce): ?>
                                <?php if ($isDon): ?>
                                    <?php if ($res > 0): ?>
                                        <?php if ($res === $myId): ?>
                                            <span style="color:#4caf50; font-weight:bold;">✓ Réservé par vous</span>
                                        <?php else: ?>
                                            <span class="muted">Déjà réservé</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="POST" onclick="event.stopPropagation()">
                                            <input type="hidden" name="reserve_don_id" value="<?= $aid ?>">
                                            <button class="btn-primary" type="submit" style="width:100%; font-size:12px; padding:8px;">📦 Réserver</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($isVente): ?>
                                    <?php if ($buyer > 0): ?>
                                        <?php if ($buyer === $myId): ?>
                                            <span style="color:#4caf50; font-weight:bold;">✓ Acheté</span>
                                        <?php else: ?>
                                            <span class="muted">Déjà vendu</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a class="btn-primary" href="paiement_stripe.php?amount=<?= $prix * 100 ?>&item=Achat+annonce+<?= urlencode($a['titre'] ?? '') ?>&annonce_id=<?= $aid ?>" style="display:block; text-align:center; font-size:12px; padding:8px;" onclick="event.stopPropagation();">💳 Acheter</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;">Votre annonce</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- MODAL MES ANNONCES -->
<div id="annonceModal" class="modal-annonce" onclick="closeAnnonceModal()">
    <div class="modal-annonce-content" onclick="event.stopPropagation()">
        <span class="modal-annonce-close" onclick="closeAnnonceModal()">&times;</span>
        <div class="modal-image-container">
            <img id="modalAnnonceImg" class="modal-annonce-img" src="" alt="">
        </div>
        <div class="modal-body">
            <h2 id="modalAnnonceTitre"></h2>
            <div class="modal-info-row"><div class="modal-info-label">💰 Mode :</div><div class="modal-info-value" id="modalAnnonceMode"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">💶 Prix :</div><div class="modal-info-value" id="modalAnnoncePrix"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📌 Statut :</div><div class="modal-info-value" id="modalAnnonceStatut"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📅 Date :</div><div class="modal-info-value" id="modalAnnonceDate"></div></div>
            <div><div class="modal-info-label" style="width:auto;margin-bottom:8px;">📝 Description :</div><div class="modal-description-box" id="modalAnnonceDesc"></div></div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-primary" onclick="modifierAnnonce()">✏️ Modifier</button>
                <button class="btn-modal btn-modal-danger" onclick="supprimerAnnonce()">🗑️ Supprimer</button>
                <button class="btn-modal btn-modal-secondary" onclick="closeAnnonceModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MARKETPLACE (zoom avec infos vendeur) -->
<div id="marketplaceAnnonceModal" class="modal-annonce" onclick="closeMarketplaceAnnonceModal()">
    <div class="modal-annonce-content" onclick="event.stopPropagation()">
        <span class="modal-annonce-close" onclick="closeMarketplaceAnnonceModal()">&times;</span>
        <div class="modal-image-container">
            <img id="marketModalAnnonceImg" class="modal-annonce-img" src="" alt="">
        </div>
        <div class="modal-body">
            <h2 id="marketModalAnnonceTitre"></h2>
            <div class="modal-info-row"><div class="modal-info-label">💰 Mode :</div><div class="modal-info-value" id="marketModalAnnonceMode"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">💶 Prix :</div><div class="modal-info-value" id="marketModalAnnoncePrix"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📌 Statut :</div><div class="modal-info-value" id="marketModalAnnonceStatut"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📅 Date :</div><div class="modal-info-value" id="marketModalAnnonceDate"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">👤 Vendeur :</div><div class="modal-info-value" id="marketModalAnnonceVendeur"></div></div>
            <div><div class="modal-info-label" style="width:auto;margin-bottom:8px;">📝 Description :</div><div class="modal-description-box" id="marketModalAnnonceDesc"></div></div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-secondary" onclick="closeMarketplaceAnnonceModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function updateCharCount(textarea) {
    let len = textarea.value.length;
    let counter = document.getElementById('descCounter');
    counter.textContent = len + ' / 200 caractères';
    if (len >= 200) {
        counter.classList.add('warning');
    } else {
        counter.classList.remove('warning');
    }
}

let currentAnnonceId = null;

// Fonction pour afficher la modale de mes annonces
function showMyAnnonceModal(row) {
    currentAnnonceId = row.dataset.id;
    document.getElementById('modalAnnonceTitre').textContent = row.dataset.titre;
    const mode = row.dataset.mode;
    document.getElementById('modalAnnonceMode').textContent = mode === 'don' ? 'Don' : 'Vente';
    document.getElementById('modalAnnoncePrix').textContent = mode === 'vente' ? parseFloat(row.dataset.prix).toFixed(2) + ' €' : 'Gratuit';
    document.getElementById('modalAnnonceStatut').textContent = row.dataset.statut;
    document.getElementById('modalAnnonceDate').textContent = row.dataset.date;
    document.getElementById('modalAnnonceDesc').textContent = row.dataset.description || 'Aucune description';
    const img = document.getElementById('modalAnnonceImg');
    if (row.dataset.photo && row.dataset.photo !== '' && row.dataset.photo !== 'null') {
        img.src = row.dataset.photo;
        img.style.display = 'block';
    } else {
        img.src = '';
        img.style.display = 'block';
    }
    document.getElementById('annonceModal').classList.add('active');
}

// Fonction pour afficher la modale de la marketplace
function showMarketplaceAnnonceModal(annonce) {
    document.getElementById('marketModalAnnonceTitre').textContent = annonce.titre;
    document.getElementById('marketModalAnnonceMode').textContent = annonce.mode === 'don' ? 'Don' : 'Vente';
    document.getElementById('marketModalAnnoncePrix').textContent = annonce.mode === 'vente' ? parseFloat(annonce.prix).toFixed(2) + ' €' : 'Gratuit';
    document.getElementById('marketModalAnnonceStatut').textContent = annonce.statut;
    document.getElementById('marketModalAnnonceDate').textContent = annonce.date;
    document.getElementById('marketModalAnnonceDesc').textContent = annonce.description || 'Aucune description';
    document.getElementById('marketModalAnnonceVendeur').textContent = annonce.vendeur || 'Inconnu';
    const img = document.getElementById('marketModalAnnonceImg');
    if (annonce.photo_url && annonce.photo_url !== '' && annonce.photo_url !== 'null') {
        img.src = annonce.photo_url;
        img.style.display = 'block';
    } else {
        img.src = '';
        img.style.display = 'block';
    }
    document.getElementById('marketplaceAnnonceModal').classList.add('active');
}

function closeAnnonceModal() { 
    document.getElementById('annonceModal').classList.remove('active'); 
    currentAnnonceId = null; 
}

function closeMarketplaceAnnonceModal() {
    document.getElementById('marketplaceAnnonceModal').classList.remove('active');
}

function modifierAnnonce() { 
    if (currentAnnonceId) {
        window.location.href = 'modifier_annonce.php?id=' + currentAnnonceId;
    }
}

function supprimerAnnonce() { 
    if (confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?') && currentAnnonceId) {
        window.location.href = 'supprimer_annonce.php?id=' + currentAnnonceId;
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (document.getElementById('annonceModal').classList.contains('active')) closeAnnonceModal();
        if (document.getElementById('marketplaceAnnonceModal').classList.contains('active')) closeMarketplaceAnnonceModal();
    }
});

const modeSel = document.getElementById('mode');
const prixWrap = document.getElementById('prix-wrap');
const prixInput = document.getElementById('prix');

function togglePrix() {
    const isVente = modeSel && modeSel.value === 'vente';
    if (prixWrap) prixWrap.style.display = isVente ? 'block' : 'none';
    if (prixInput) { 
        prixInput.required = isVente; 
        prixInput.disabled = !isVente; 
        if (!isVente) prixInput.value = ''; 
    }
}

if (modeSel) { 
    modeSel.addEventListener('change', togglePrix); 
    togglePrix(); 
}

const textarea = document.querySelector('textarea[name="description"]');
if (textarea) {
    updateCharCount(textarea);
}
</script>

<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>