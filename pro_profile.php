<?php
require_once 'includes/pro_bootstrap.php';
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $photo = trim((string)($_POST['photo_profil'] ?? ''));
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
        'bio' => trim((string)($_POST['bio'] ?? '')),
    ];
    $res = callAPI('PUT', '/me/update', $_SESSION['token'], $payload);
    $_SESSION['flash_message'] = (($res['status'] ?? 0) === 200) ? '✅ Profil mis à jour.' : '❌ Mise à jour impossible.';
    $_SESSION['flash_type'] = (($res['status'] ?? 0) === 200) ? 'success' : 'error';
    header('Location: pro_profile.php');
    exit;
}

$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil pro</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>👤 Profil professionnel</h1>
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= e($flash) ?></div>
        <?php endif; ?>
        <div class="row-actions">
            <?php if (!empty($me['photo_profil'])): ?>
                <img src="<?= e(vc_media_url($me['photo_profil'])) ?>" alt="Photo profil" style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:2px solid #16a34a;">
            <?php endif; ?>
        </div>
        <table class="table"><tbody>
            <tr><th>Pseudo</th><td><?= e($me['pseudo'] ?? '') ?></td></tr>
            <tr><th>Prénom</th><td><?= e($me['prenom'] ?? '') ?></td></tr>
            <tr><th>Nom</th><td><?= e($me['nom'] ?? '') ?></td></tr>
            <tr><th>Email</th><td><?= e($me['email'] ?? '') ?></td></tr>
            <tr><th>Téléphone</th><td><?= e($me['telephone'] ?? '') ?></td></tr>
            <tr><th>Ville</th><td><?= e($me['adresse_ville'] ?? '') ?></td></tr>
            <tr><th>Bio</th><td><?= e($me['bio'] ?? '') ?></td></tr>
        </tbody></table>
        <h2 style="margin-top:16px;">Modifier mon profil</h2>
        <form method="POST" enctype="multipart/form-data" class="row-actions" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <input type="hidden" name="update_profile" value="1">
            <input class="input" name="pseudo" value="<?= e($me['pseudo'] ?? '') ?>" placeholder="Pseudo">
            <input class="input" name="prenom" value="<?= e($me['prenom'] ?? '') ?>" placeholder="Prénom">
            <input class="input" name="nom" value="<?= e($me['nom'] ?? '') ?>" placeholder="Nom">
            <input class="input" name="telephone" value="<?= e($me['telephone'] ?? '') ?>" placeholder="Téléphone">
            <input class="input" name="adresse_rue" value="<?= e($me['adresse_rue'] ?? '') ?>" placeholder="Adresse">
            <input class="input" name="adresse_ville" value="<?= e($me['adresse_ville'] ?? '') ?>" placeholder="Ville">
            <input class="input" name="adresse_code_postal" value="<?= e($me['adresse_code_postal'] ?? '') ?>" placeholder="Code postal">
            <input class="input" name="adresse_pays" value="<?= e($me['adresse_pays'] ?? '') ?>" placeholder="Pays">
            <input class="input" name="photo_profil" value="<?= e($me['photo_profil'] ?? '') ?>" placeholder="URL photo">
            <input class="input" type="file" name="photo_profil_file" accept="image/*">
            <textarea class="input" name="bio" placeholder="Bio" style="grid-column:1/-1;min-height:96px;"><?= e($me['bio'] ?? '') ?></textarea>
            <button class="btn-primary" type="submit" style="grid-column:1/-1;">💾 Enregistrer</button>
        </form>
    </section>
</main>
<?php  ?>
</body>
</html>