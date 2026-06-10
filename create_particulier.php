<?php
session_start();
require_once 'includes/functions/api_core.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'email' => trim($_POST['email'] ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'pseudo' => trim($_POST['pseudo'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'adresse_rue' => trim($_POST['adresse_rue'] ?? ''),
        'adresse_ville' => trim($_POST['adresse_ville'] ?? ''),
        'adresse_code_postal' => trim($_POST['adresse_code_postal'] ?? ''),
        'adresse_pays' => trim($_POST['adresse_pays'] ?? ''),
        'photo_profil' => '',
        'bio' => '',
        'role_id' => 2,
    ];

    $confirm = trim($_POST['password_confirm'] ?? '');

    if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if ($payload['password'] !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if (strlen($payload['password']) < 12) {
        $errors[] = 'Le mot de passe doit contenir au moins 12 caracteres.';
    }
    if ($payload['pseudo'] === '' || $payload['prenom'] === '' || $payload['nom'] === '') {
        $errors[] = 'Pseudo, prenom et nom sont obligatoires.';
    }

    if (empty($errors)) {
        $res = api_post('/register', $payload, false);
        if (($res['status'] ?? 0) === 201) {
            $success = 'Compte particulier cree avec succes.';
        } elseif (($res['status'] ?? 0) === 409) {
            $errors[] = 'Cet email est deja utilise.';
        } else {
            $errors[] = 'Impossible de creer le compte pour le moment.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Creer un particulier - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
</head>
<body>
<header class="navbar">
    <div class="logo"><a href="index.php" style="text-decoration:none;color:#16a34a;">UpcycleConnect</a></div>
    <nav class="auth-buttons">
        <a class="btn-outline" href="index.php">Accueil</a>
        <a class="btn-outline" href="login.php">Connexion</a>
    </nav>
</header>

<main class="auth-page public-auth-page">
    <div class="auth-card auth-card-wide">
        <h1>Creation d'un compte particulier</h1>
        <p class="muted">Tous les champs utiles sont ici. La bio se complete plus tard dans l'espace connecte.</p>

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
                    <label for="pseudo">Pseudo</label>
                    <input type="text" id="pseudo" name="pseudo" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
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

            <div class="grid-2">
                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="text" id="telephone" name="telephone">
                </div>
                <div class="form-group">
                    <label for="adresse_pays">Pays</label>
                    <input type="text" id="adresse_pays" name="adresse_pays">
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

            <button class="btn-primary" type="submit">Creer le particulier</button>
        </form>
    </div>
</main>
</body>
</html>
