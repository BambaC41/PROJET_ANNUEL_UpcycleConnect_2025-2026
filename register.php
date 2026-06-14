<?php
session_start();
require_once 'includes/functions/api_core.php';
require_once 'includes/i18n.php';
set_lang_from_request();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['password_confirm'] ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 2);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Le mot de passe doit contenir au moins 12 caracteres.';
    }
    if ($pseudo === '' || $prenom === '' || $nom === '') {
        $errors[] = 'Pseudo, prenom et nom sont obligatoires.';
    }
    if (!in_array($roleId, [2, 3], true)) {
        $errors[] = 'Role invalide.';
    }

    if (empty($errors)) {
        $payload = [
            'email' => $email,
            'password' => $password,
            'pseudo' => $pseudo,
            'prenom' => $prenom,
            'nom' => $nom,
            'telephone' => trim($_POST['telephone'] ?? ''),
            'adresse_rue' => trim($_POST['adresse_rue'] ?? ''),
            'adresse_ville' => trim($_POST['adresse_ville'] ?? ''),
            'adresse_code_postal' => trim($_POST['adresse_code_postal'] ?? ''),
            'adresse_pays' => trim($_POST['adresse_pays'] ?? ''),
            'photo_profil' => '',
            'bio' => '',
            'role_id' => $roleId,
        ];

        $res = api_post('/register', $payload, false);
        if (($res['status'] ?? 0) === 201) {
            $success = $roleId === 3
                ? 'Compte professionnel cree. Il sera actif apres validation.'
                : 'Compte cree avec succes. Vous pouvez vous connecter.';
        } elseif (($res['status'] ?? 0) === 409) {
            $errors[] = 'Cet email est deja utilise.';
        } else {
            $errors[] = 'Impossible de creer le compte pour le moment.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title>Inscription - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body>
<header class="navbar">
    <div class="logo"><a href="index.php" style="text-decoration:none;color:#16a34a;">UpcycleConnect</a></div>
    <nav class="auth-buttons">
        <a class="btn-outline" href="index.php"><?= htmlspecialchars(t('nav.home')) ?></a>
        <a class="btn-outline" href="login.php"><?= htmlspecialchars(t('nav.login')) ?></a>
        <a class="btn-outline" href="?lang=fr">FR</a>
        <a class="btn-outline" href="?lang=en">EN</a>
    </nav>
</header>

<main class="auth-page public-auth-page">
    <div class="auth-card auth-card-wide">
        <h1><?= htmlspecialchars(t('auth.register_title')) ?></h1>
        <p class="muted">Renseignez vos informations. La bio pourra etre ajoutee apres connexion.</p>

        <?php if (!empty($success)): ?>
            <p class="success"><?= htmlspecialchars($success) ?> <a href="login.php">Se connecter</a></p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err): ?>
                    <div>- <?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="grid-2">
                <div class="form-group">
                    <label for="role_id">Type de compte</label>
                    <select id="role_id" name="role_id" required>
                        <option value="2">Particulier</option>
                        <option value="3">Professionnel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="pseudo">Pseudo</label>
                    <input type="text" id="pseudo" name="pseudo" required>
                </div>
                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="text" id="telephone" name="telephone">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="prenom">Prenom</label>
                    <input type="text" id="prenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required>
                </div>
            </div>

            <div class="form-group">
                <label for="adresse_rue">Adresse</label>
                <input type="text" id="adresse_rue" name="adresse_rue">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="adresse_ville">Ville</label>
                    <input type="text" id="adresse_ville" name="adresse_ville">
                </div>
                <div class="form-group">
                    <label for="adresse_code_postal">Code postal</label>
                    <input type="text" id="adresse_code_postal" name="adresse_code_postal">
                </div>
            </div>

            <div class="form-group">
                <label for="adresse_pays">Pays</label>
                <input type="text" id="adresse_pays" name="adresse_pays">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" minlength="12" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmation</label>
                    <input type="password" id="password_confirm" name="password_confirm" minlength="12" required>
                </div>
            </div>

            <button class="btn-primary" type="submit">Creer mon compte</button>
        </form>
    </div>
</main>
<?php  ?>
</body>
</html>
