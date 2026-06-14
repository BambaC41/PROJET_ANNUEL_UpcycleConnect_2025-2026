<?php
require_once 'includes/particulier_bootstrap.php';

$id_annonce = (int)($_GET['id'] ?? 0);
if ($id_annonce <= 0) {
    header('Location: particulier_annonces.php');
    exit;
}

// Récupérer l'annonce
$response = api_get_annonce($id_annonce);
$annonce = $response['data'] ?? null;

// Vérifier que l'annonce existe et appartient à l'utilisateur
if (!$annonce || ($annonce['id_user'] ?? 0) != ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['flash_message'] = 'Vous ne pouvez pas modifier cette annonce.';
    $_SESSION['flash_type'] = 'error';
    header('Location: particulier_annonces.php');
    exit;
}

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo = $annonce['photo_url'] ?? '';
    
    if (isset($_FILES['photo_file']) && ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = api_upload_file('file', $_FILES['photo_file']['tmp_name'], $_FILES['photo_file']['name'], $_SESSION['token']);
        if (($up['status'] ?? 0) === 200 && !empty($up['data']['file_url'])) {
            $photo = $up['data']['file_url'];
        }
    }
    
    $modePost = trim((string)($_POST['mode'] ?? 'don'));
    $prixPost = (float)($_POST['prix'] ?? 0);
    
    if ($modePost === 'vente' && $prixPost <= 0) {
        $_SESSION['flash_message'] = 'Pour une vente, le prix doit être supérieur à 0.';
        $_SESSION['flash_type'] = 'error';
        header("Location: modifier_annonce.php?id=$id_annonce");
        exit;
    }
    
    $payload = [
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'mode' => $modePost,
        'prix' => ($modePost === 'vente') ? $prixPost : 0,
        'photo_url' => $photo
    ];
    
    $res = api_update_annonce($id_annonce, $payload);
    
    if (($res['status'] ?? 0) === 200) {
        $_SESSION['flash_message'] = 'Annonce modifiée avec succès.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = '❌ Erreur lors de la modification : ' . ($res['error'] ?? '');
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: particulier_annonces.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier annonce - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .current-photo {
            margin-top: 10px;
        }
        .current-photo img {
            max-width: 200px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>✏️ Modifier mon annonce</h1>
        
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= e($flash) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Titre *</label>
                <input class="input" name="titre" value="<?= e($annonce['titre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Mode *</label>
                <select class="input" id="mode" name="mode">
                    <option value="don" <?= ($annonce['mode'] ?? '') === 'don' ? 'selected' : '' ?>>Don</option>
                    <option value="vente" <?= ($annonce['mode'] ?? '') === 'vente' ? 'selected' : '' ?>>Vente</option>
                </select>
            </div>
            
            <div class="form-group" id="prix-wrap" style="display: <?= ($annonce['mode'] ?? '') === 'vente' ? 'block' : 'none' ?>;">
                <label>Prix (€)</label>
                <input class="input" id="prix" name="prix" type="number" step="0.01" min="0.01" value="<?= e($annonce['prix'] ?? 0) ?>">
            </div>
            
            <?php if (!empty($annonce['photo_url'])): ?>
                <div class="form-group">
                    <label>Photo actuelle</label>
                    <div class="current-photo">
                        <img src="<?= e(vc_media_url($annonce['photo_url'])) ?>" alt="Photo actuelle">
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nouvelle photo (optionnel)</label>
                <input class="input" type="file" name="photo_file" accept="image/*">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea class="input" name="description" rows="5"><?= e($annonce['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-actions">
                <button class="btn-primary" type="submit">💾 Enregistrer</button>
                <a href="particulier_annonces.php" class="btn-outline">Annuler</a>
            </div>
        </form>
    </section>
</main>

<script>
const modeSel = document.getElementById('mode');
const prixWrap = document.getElementById('prix-wrap');
const prix = document.getElementById('prix');

function togglePrix() {
    const isV = modeSel && modeSel.value === 'vente';
    if (prixWrap) prixWrap.style.display = isV ? 'block' : 'none';
    if (prix) {
        prix.required = isV;
        if (!isV) prix.value = '';
    }
}
if (modeSel) {
    modeSel.addEventListener('change', togglePrix);
    togglePrix();
}
</script>
<?php  ?>
</body>
</html>