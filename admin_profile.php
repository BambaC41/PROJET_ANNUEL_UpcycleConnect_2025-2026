<?php
require_once 'includes/admin_bootstrap.php';
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
    $_SESSION['flash_message'] = (($res['status'] ?? 0) === 200) ? '✅ Profil admin mis à jour.' : '❌ Mise à jour impossible.';
    $_SESSION['flash_type'] = (($res['status'] ?? 0) === 200) ? 'success' : 'error';
    header('Location: admin_profile.php');
    exit;
}

$me = callAPI('GET', '/me', $_SESSION['token'])['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>👤 Mon profil admin</h1>
        <?php if ($flash !== ''): ?><div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>"><?= e($flash) ?></div><?php endif; ?>
        <?php if (!empty($me['photo_profil'])): ?><img src="<?= e(vc_media_url($me['photo_profil'])) ?>" alt="Photo profil" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:2px solid #16a34a;margin-bottom:10px;"><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
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
            <textarea class="input" name="bio" style="grid-column:1/-1;min-height:100px;" placeholder="Bio"><?= e($me['bio'] ?? '') ?></textarea>
            <button class="btn-primary" type="submit">💾 Enregistrer</button>
        </form>
    </section>
</section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>
