<?php
require_once 'includes/particulier_bootstrap.php';
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $photo = trim((string)($_POST['photo_profil'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    
    if (mb_strlen($bio) > 300) {
        $_SESSION['flash_message'] = 'La bio ne peut pas dépasser 300 caractères.';
        $_SESSION['flash_type'] = 'error';
        header('Location: particulier_profile.php');
        exit;
    }
    
    if (isset($_FILES['photo_profil_file']) && ($_FILES['photo_profil_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $up = api_upload_file('file', $_FILES['photo_profil_file']['tmp_name'], $_FILES['photo_profil_file']['name'], $_SESSION['token']);
        if (($up['status'] ?? 0) === 200 && !empty($up['data']['file_url'])) $photo = $up['data']['file_url'];
    }
    $payload = [
        'pseudo' => trim((string)($_POST['pseudo'] ?? '')),
        'prenom' => trim((string)($_POST['prenom'] ?? '')),
        'nom' => trim((string)($_POST['nom'] ?? '')),
        'telephone' => trim((string)($_POST['telephone'] ?? '')),
        'adresse_rue' => trim((string)($_POST['adresse_rue'] ?? '')),
        'adresse_ville' => trim((string)($_POST['adresse_ville'] ?? '')),
        'adresse_code_postal' => trim((string)($_POST['adresse_code_postal'] ?? '')),
        'adresse_pays' => trim((string)($_POST['adresse_pays'] ?? '')),
        'photo_profil' => $photo,
        'bio' => $bio,
    ];
    $res = callAPI('PUT', '/me/update', $_SESSION['token'], $payload);
    $_SESSION['flash_message'] = (($res['status'] ?? 0) === 200) ? '✅ Profil mis à jour.' : '❌ Mise à jour impossible.';
    $_SESSION['flash_type'] = (($res['status'] ?? 0) === 200) ? 'success' : 'error';
    header('Location: particulier_profile.php');
    exit;
}

$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil particulier</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
<section class="pro-card">
    <h1>👤 Mon profil</h1>
    
    <?php if ($flash !== ''): ?>
        <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= e($flash) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($me['photo_profil'])): ?>
        <img src="<?= e(vc_media_url($me['photo_profil'])) ?>" alt="Photo profil" class="profile-photo">
    <?php endif; ?>
    
    <table class="table">
        <tbody>
            <tr><th>Pseudo</th><td><?= e($me['pseudo'] ?? '') ?></td></tr>
            <tr><th>Prénom</th><td><?= e($me['prenom'] ?? '') ?></td></tr>
            <tr><th>Nom</th><td><?= e($me['nom'] ?? '') ?></td></tr>
            <tr><th>Email</th><td><?= e($me['email'] ?? '') ?></td></tr>
            <tr><th>Téléphone</th><td><?= e($me['telephone'] ?? '') ?></td></tr>
            <tr><th>Adresse</th><td><?= e(trim(($me['adresse_rue'] ?? '') . ' ' . ($me['adresse_ville'] ?? '') . ' ' . ($me['adresse_code_postal'] ?? ''))) ?></td></tr>
            <tr><th>Bio</th><td><?= e(mb_substr($me['bio'] ?? '', 0, 300)) ?></td></tr>
            <tr><th>Tutoriel</th><td><a class="btn-outline" href="tutorial_reset.php">Revoir le tutoriel</a></td></tr>
        </tbody>
    </table>
    
    <h2 style="margin-top:16px;">Modifier mon profil</h2>
    <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <input type="hidden" name="update_profile" value="1">
        <input class="input" name="pseudo" value="<?= e($me['pseudo'] ?? '') ?>" placeholder="Pseudo" maxlength="100">
        <input class="input" name="prenom" value="<?= e($me['prenom'] ?? '') ?>" placeholder="Prénom" maxlength="100">
        <input class="input" name="nom" value="<?= e($me['nom'] ?? '') ?>" placeholder="Nom" maxlength="100">
        <input class="input" name="telephone" value="<?= e($me['telephone'] ?? '') ?>" placeholder="Téléphone" maxlength="20">
        <input class="input" name="adresse_rue" value="<?= e($me['adresse_rue'] ?? '') ?>" placeholder="Adresse" maxlength="150">
        <input class="input" name="adresse_ville" value="<?= e($me['adresse_ville'] ?? '') ?>" placeholder="Ville" maxlength="100">
        <input class="input" name="adresse_code_postal" value="<?= e($me['adresse_code_postal'] ?? '') ?>" placeholder="Code postal" maxlength="20">
        <input class="input" name="adresse_pays" value="<?= e($me['adresse_pays'] ?? '') ?>" placeholder="Pays" maxlength="100">
        <input class="input" type="file" name="photo_profil_file" accept="image/*" style="grid-column:span 2;">
        
        <label style="grid-column:1/-1;">Bio (max 300 caractères)</label>
        <textarea class="input" name="bio" placeholder="Bio" style="grid-column:1/-1;min-height:96px;" maxlength="300" oninput="updateCharCount(this)"><?= e($me['bio'] ?? '') ?></textarea>
        <div class="char-counter" id="bioCounter" style="grid-column:1/-1;"><?= mb_strlen($me['bio'] ?? '') ?> / 300 caractères</div>
        
        <button class="btn-primary" type="submit" style="grid-column:1/-1;">💾 Enregistrer</button>
    </form>
</section>
</main>
<script>
function updateCharCount(textarea) {
    let len = textarea.value.length;
    let counter = document.getElementById('bioCounter');
    if (counter) {
        counter.textContent = len + ' / 300 caractères';
        if (len >= 300) {
            counter.classList.add('warning');
        } else {
            counter.classList.remove('warning');
        }
    }
}
const bioTextarea = document.querySelector('textarea[name="bio"]');
if (bioTextarea) {
    updateCharCount(bioTextarea);
    bioTextarea.addEventListener('input', function() { updateCharCount(this); });
}
</script>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>