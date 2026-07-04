<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/pro_bootstrap.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';

$myId = (int)$_SESSION['user_id'];

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

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

$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$mode = trim((string)($_GET['mode'] ?? 'all'));

$myAnnonces = api_get_my_annonces()['data'] ?? [];

$publicAnnonces = api_get_annonces()['data'] ?? [];

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

$filteredMyAnnonces = array_values(array_filter($myAnnonces, function($a) use ($query, $mode) {
    $okQ = ($query === '') 
        || str_contains(mb_strtolower((string)($a['titre'] ?? '')), $query) 
        || str_contains(mb_strtolower((string)($a['description'] ?? '')), $query);
    $aMode = (string)($a['mode'] ?? 'don');
    $okM = ($mode === 'all') || ($aMode === $mode);
    return $okQ && $okM;
}));

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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>

<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>📦 annonces</h1>
        
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
                    <tr><td colspan="6" style="text-align: center;">Aucune annonce créée pour le moment.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    
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