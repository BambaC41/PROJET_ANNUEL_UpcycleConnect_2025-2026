<?php
require_once 'includes/particulier_bootstrap.php';
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_annonce'])) {
    $photo = trim((string)($_POST['photo_url'] ?? ''));
    if (isset($_FILES['photo_file']) && ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = api_upload_file('file', $_FILES['photo_file']['tmp_name'], $_FILES['photo_file']['name'], $_SESSION['token']);
        if (($up['status'] ?? 0) === 200 && !empty($up['data']['file_url'])) $photo = $up['data']['file_url'];
    }
    $modePost = trim((string)($_POST['mode'] ?? 'don'));
    $prixPost = (float)($_POST['prix'] ?? 0);
    if ($modePost === 'vente' && $prixPost <= 0) {
        $_SESSION['flash_message'] = 'Pour une vente, le prix doit être supérieur à 0.';
        $_SESSION['flash_type'] = 'error';
        header('Location: particulier_annonces.php');
        exit;
    }
    $payload = [
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'mode' => $modePost,
        'prix' => ($modePost === 'vente') ? $prixPost : 0,
        'photo_url' => $photo
    ];
    $res = api_create_annonce($payload);
    $_SESSION['flash_message'] = (($res['status'] ?? 0) === 201) ? 'Annonce envoyée, elle sera visible après validation.' : '❌ Création annonce impossible.';
    $_SESSION['flash_type'] = (($res['status'] ?? 0) === 201) ? 'success' : 'error';
    header('Location: particulier_annonces.php');
    exit;
}

$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$mode = trim((string)($_GET['mode'] ?? 'all'));
$annonces = api_get_my_annonces()['data'] ?? [];
$publicAnnonces = api_get_annonces()['data'] ?? [];
$filtered = array_values(array_filter($annonces, function($a) use ($query, $mode) {
    $okQ = ($query === '') || str_contains(mb_strtolower((string)($a['titre'] ?? '')), $query) || str_contains(mb_strtolower((string)($a['description'] ?? '')), $query);
    $aMode = (string)($a['mode'] ?? 'don');
    $okM = ($mode === 'all') || ($aMode === $mode);
    return $okQ && $okM;
}));
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Annonces particulier</title>
<link rel="stylesheet" href="styles/style.css"><link rel="stylesheet" href="styles/pro.css"></head>
<body class="pro-page"><?php include 'includes/particulier_nav.php'; ?><main class="pro-shell page-shell">
<section class="pro-card"><h1>📦 Mes annonces</h1>
<?php if ($flash !== ''): ?><div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= e($flash) ?></div><?php endif; ?>
<h2 style="font-size:18px;">Créer une annonce</h2>
<form method="POST" enctype="multipart/form-data" class="row-actions" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <input type="hidden" name="create_annonce" value="1">
    <label>Titre</label><input class="input" name="titre" placeholder="Titre" required>
    <label>Mode</label><select class="input" id="mode" name="mode"><option value="don">Don</option><option value="vente">Vente</option></select>
    <div id="prix-wrap"><label>Prix</label><input class="input" id="prix" name="prix" type="number" step="0.01" min="0.01" placeholder="Prix (vente)"></div>
    <input class="input" name="photo_url" placeholder="URL photo (optionnel)">
    <input class="input" type="file" name="photo_file" accept="image/*" style="grid-column:1/-1;">
    <textarea class="input" name="description" placeholder="Description" style="grid-column:1/-1;min-height:90px;"></textarea>
    <button class="btn-primary" type="submit" style="grid-column:1/-1;">➕ Publier</button>
</form>
<h2 style="font-size:18px;margin-top:14px;">Mes annonces</h2>
<form method="GET" class="row-actions"><input class="input" type="search" name="q" placeholder="Rechercher..." value="<?= e($query) ?>">
<select class="input" name="mode"><option value="all" <?= $mode==='all'?'selected':'' ?>>Tous</option><option value="don" <?= $mode==='don'?'selected':'' ?>>Don</option><option value="vente" <?= $mode==='vente'?'selected':'' ?>>Vente</option></select>
<button class="btn-outline" type="submit">Filtrer</button></form>
<table class="table"><thead><tr><th>Titre</th><th>Mode</th><th>Prix</th><th>Statut</th><th>Date</th></tr></thead><tbody>
<?php foreach ($filtered as $a): ?><tr><td><?= e($a['titre'] ?? '') ?></td><td><?= e($a['mode'] ?? '') ?></td><td><?= (($a['mode'] ?? '')==='vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></td><td><?php $s=(string)($a['statut']??''); echo e($s==='en_attente'?'En attente':($s==='validee'?'Validée':($s==='rejetee'?'Rejetée':$s))); ?></td><td><?= e(formatDateFr($a['created_at'] ?? '')) ?></td></tr><?php endforeach; ?>
</tbody></table>
</section>

<section class="pro-card">
    <h1>🌍 Annonces publiques validées</h1>
    <div class="pro-grid">
        <?php foreach ($publicAnnonces as $a): ?>
            <article class="pro-card">
                <?php if (!empty($a['photo_url'])): ?><img src="<?= e(vc_media_url($a['photo_url'])) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" style="width:100%;height:170px;object-fit:cover;border-radius:10px;margin-bottom:8px;"><?php endif; ?>
                <h2><?= e($a['titre'] ?? '') ?></h2>
                <p><?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 140, '...')) ?></p>
                <p><strong><?= e($a['mode'] ?? '') ?></strong> - <?= (($a['mode'] ?? '') === 'vente') ? e(formatPriceEur($a['prix'] ?? 0)) : 'Gratuit' ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
</main><?php include 'includes/flash_toast.php'; ?>
<script>
const modeSel = document.getElementById('mode');
const prixWrap = document.getElementById('prix-wrap');
const prix = document.getElementById('prix');
function togglePrix(){const isV=modeSel && modeSel.value==='vente';prixWrap.style.display=isV?'block':'none';if(prix){prix.required=isV;prix.disabled=!isV;if(!isV) prix.value='';}}
if(modeSel){modeSel.addEventListener('change',togglePrix);togglePrix();}
</script>
</body></html>
