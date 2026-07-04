<?php
require_once 'includes/particulier_bootstrap.php';

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
        header('Location: particulier_annonces.php');
        exit;
    }
    
    if ($modePost === 'vente' && $prixPost <= 0) {
        $_SESSION['flash_message'] = 'Pour une vente, le prix doit être supérieur à 0.';
        $_SESSION['flash_type'] = 'error';
        header('Location: particulier_annonces.php');
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
    header('Location: particulier_annonces.php');
    exit;
}

$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$mode = trim((string)($_GET['mode'] ?? 'all'));
$annonces = api_get_my_annonces()['data'] ?? [];
$publicAnnonces = api_get_annonces()['data'] ?? [];

$filtered = array_values(array_filter($annonces, function($a) use ($query, $mode) {
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
    <title>Annonces particulier - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>

<main class="pro-shell page-shell">
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
            
            <button class="btn-primary" type="submit" style="grid-column:1/-1;">➕ Publier</button>
        </form>
    </section>

    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">Mes annonces</h2>
        
        <form method="GET" class="row-actions" style="margin-bottom:20px;">
            <input class="input" type="search" name="q" placeholder="Rechercher..." value="<?= e($query) ?>" style="flex:1;">
            <select class="input" name="mode" style="width:120px;">
                <option value="all" <?= $mode==='all'?'selected':'' ?>>Tous</option>
                <option value="don" <?= $mode==='don'?'selected':'' ?>>Don</option>
                <option value="vente" <?= $mode==='vente'?'selected':'' ?>>Vente</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        
        <table class="table">
            <thead>
                <tr><th>Titre</th><th>Mode</th><th>Prix</th><th>Statut</th><th>Date</th><th>Commission</th></tr>
            </thead>
            <tbody>
            <?php foreach ($filtered as $a): ?>
                <?php 
                $commission = (($a['mode'] ?? '') === 'vente' && ($a['statut'] ?? '') === 'validee') 
                    ? ($a['prix'] ?? 0) * 0.05 : 0; 
                $statut = (string)($a['statut'] ?? '');
                $statutLabel = match($statut) {
                    'en_attente' => 'En attente',
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
                    data-commission-payee="<?= $commissionPayee ? '1' : '0' ?>">
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
                                <a class="btn-primary" href="paiement_stripe.php?amount=<?= $commission * 100 ?>&item=Commission+vente+<?= urlencode($a['titre'] ?? '') ?>&annonce_id=<?= $a['id_annonce'] ?>">
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
            </tbody>
        </table>
    </section>

    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">🌍 Annonces publiques validées</h2>
        <div class="pro-grid">
            <?php foreach ($publicAnnonces as $a): ?>
                <div class="pro-card annonce-clickable" 
                     style="margin:0;cursor:pointer;"
                     onclick='showPublicAnnonceModal(<?= json_encode([
                         'titre' => $a['titre'] ?? '',
                         'description' => mb_substr($a['description'] ?? '', 0, 200),
                         'mode' => $a['mode'] ?? '',
                         'prix' => $a['prix'] ?? 0,
                         'photo_url' => vc_media_url($a['photo_url'] ?? ''),
                         'date' => formatDateFr($a['created_at'] ?? ''),
                         'statut' => 'Validée'
                     ], JSON_HEX_TAG) ?>)'>
                    <?php if (!empty($a['photo_url'])): ?>
                        <img src="<?= e(vc_media_url($a['photo_url'])) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" style="width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:12px;">
                    <?php endif; ?>
                    <h3 style="margin:0 0 8px 0;font-size:18px;"><?= e($a['titre'] ?? '') ?></h3>
                    <p style="margin:0 0 8px 0;color:#666;font-size:13px;"><?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?></p>
                    <p style="margin:0;"><strong><?= e($a['mode'] ?? '') ?></strong> - <?= (($a['mode'] ?? '') === 'vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
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
            </div>
        </div>
    </div>
</div>

<div id="publicAnnonceModal" class="modal-annonce" onclick="closePublicAnnonceModal()">
    <div class="modal-annonce-content" onclick="event.stopPropagation()">
        <span class="modal-annonce-close" onclick="closePublicAnnonceModal()">&times;</span>
        <div class="modal-image-container">
            <img id="publicModalAnnonceImg" class="modal-annonce-img" src="" alt="">
        </div>
        <div class="modal-body">
            <h2 id="publicModalAnnonceTitre"></h2>
            <div class="modal-info-row"><div class="modal-info-label">💰 Mode :</div><div class="modal-info-value" id="publicModalAnnonceMode"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">💶 Prix :</div><div class="modal-info-value" id="publicModalAnnoncePrix"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📌 Statut :</div><div class="modal-info-value" id="publicModalAnnonceStatut"></div></div>
            <div class="modal-info-row"><div class="modal-info-label">📅 Date :</div><div class="modal-info-value" id="publicModalAnnonceDate"></div></div>
            <div><div class="modal-info-label" style="width:auto;margin-bottom:8px;">📝 Description :</div><div class="modal-description-box" id="publicModalAnnonceDesc"></div></div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-secondary" onclick="closePublicAnnonceModal()">Fermer</button>
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

document.querySelectorAll('.annonce-row').forEach(row => {
    row.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
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
    });
});

function showPublicAnnonceModal(annonce) {
    document.getElementById('publicModalAnnonceTitre').textContent = annonce.titre;
    document.getElementById('publicModalAnnonceMode').textContent = annonce.mode === 'don' ? 'Don' : 'Vente';
    document.getElementById('publicModalAnnoncePrix').textContent = annonce.mode === 'vente' ? parseFloat(annonce.prix).toFixed(2) + ' €' : 'Gratuit';
    document.getElementById('publicModalAnnonceStatut').textContent = annonce.statut;
    document.getElementById('publicModalAnnonceDate').textContent = annonce.date;
    document.getElementById('publicModalAnnonceDesc').textContent = annonce.description || 'Aucune description';
    const img = document.getElementById('publicModalAnnonceImg');
    if (annonce.photo_url && annonce.photo_url !== '' && annonce.photo_url !== 'null') {
        img.src = annonce.photo_url;
        img.style.display = 'block';
    } else {
        img.src = '';
        img.style.display = 'block';
    }
    document.getElementById('publicAnnonceModal').classList.add('active');
}

function closeAnnonceModal() { 
    document.getElementById('annonceModal').classList.remove('active'); 
    currentAnnonceId = null; 
}

function closePublicAnnonceModal() { 
    document.getElementById('publicAnnonceModal').classList.remove('active'); 
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
        if (document.getElementById('publicAnnonceModal').classList.contains('active')) closePublicAnnonceModal();
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