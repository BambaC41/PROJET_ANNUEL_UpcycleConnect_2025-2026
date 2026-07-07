<?php
session_start();
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/users.php';

$token = $_GET['token'] ?? '';
$error = null;
$success = null;
$user = null;

if ($token) {
    $reset = validate_reset_token($token);
    if ($reset) {
        $user = find_user_by_id($reset['user_id']);
        if (!$user) {
            $error = 'Utilisateur introuvable.';
        }
    } else {
        $error = 'Le lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.';
    }
} else {
    $error = 'Aucun token fourni. Veuillez utiliser le lien reçu par email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Le mot de passe doit faire au moins 8 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (preg_match('/\s/', $password)) {
        $error = 'Le mot de passe ne doit pas contenir d\'espaces.';
    } else {
        update_user_password($user['id_user'], $password);
        mark_reset_token_as_used($token);
        $success = 'Votre mot de passe a été réinitialisé avec succès. <a href="login.php">Connectez-vous</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <!-- Header minimal (sans menu admin) -->
    <header style="background:#1a1a2e; color:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
        <div style="font-weight:700; font-size:1.2rem;">
            <a href="index.php" style="color:#16a34a; text-decoration:none;">UpcycleConnect</a>
        </div>
        <nav style="display:flex; gap:15px;">
            <a href="index.php" style="color:#fff; text-decoration:none;">Accueil</a>
            <a href="login.php" style="color:#fff; text-decoration:none;">Connexion</a>
            <a href="register.php" style="color:#fff; text-decoration:none;">Inscription</a>
        </nav>
    </header>

    <main style="max-width:500px;margin:40px auto;padding:0 20px;">
        <h1>Réinitialisation du mot de passe</h1>

        <?php if ($error): ?>
            <div style="color:#b91c1c;background:#fee2e2;padding:10px;border-radius:8px;margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="color:#065f46;background:#d1fae5;padding:10px;border-radius:8px;margin-bottom:15px;"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($user && !$success): ?>
            <form method="post">
                <div style="margin-bottom:15px;">
                    <label for="password" style="display:block;font-weight:600;margin-bottom:5px;">Nouveau mot de passe (8 caractères minimum)</label>
                    <input type="password" id="password" name="password" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;" required minlength="8">
                </div>
                <div style="margin-bottom:15px;">
                    <label for="confirm_password" style="display:block;font-weight:600;margin-bottom:5px;">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;" required>
                </div>
                <button type="submit" style="width:100%;padding:12px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:16px;cursor:pointer;">Réinitialiser</button>
            </form>
        <?php elseif (!$user && !$error && !$success): ?>
            <p>Le lien de réinitialisation est invalide. <a href="forgot_password.php">Refaire une demande</a></p>
        <?php endif; ?>
    </main>
</body>
</html>