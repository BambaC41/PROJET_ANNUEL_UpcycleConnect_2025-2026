<?php
session_start();
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/users.php';
require_once 'includes/bootstrap_mail.php';
require_once 'includes/i18n.php';

set_lang_from_request();

$success = null;
$error = null;
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $submittedEmail = htmlspecialchars($email);

    if (empty($email)) {
        $error = 'Veuillez saisir votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $user = find_user_by_email($email);
        $mailSent = false;

        if ($user) {
            $token = create_password_reset_token($user['id_user']);
            $resetLink = 'https://upcycle-connect.tech/reset_password.php?token=' . urlencode($token);

            $subject = 'Réinitialisation de votre mot de passe';
            $html = '
                <h1>Réinitialisation de votre mot de passe</h1>
                <p>Bonjour ' . htmlspecialchars($user['pseudo'] ?? $user['email']) . ',</p>
                <p>Vous avez demandé à réinitialiser votre mot de passe pour votre compte UpcycleConnect.</p>
                <p>Cliquez sur le lien ci-dessous (valable 1 heure) :</p>
                <p><a href="' . $resetLink . '" style="background:#16a34a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;">Réinitialiser mon mot de passe</a></p>
                <p>Si le lien ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>' . $resetLink . '</p>
                <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>
                <p>L\'équipe UpcycleConnect</p>
            ';

            $mailer = getMailService();
            $result = $mailer->send($email, $subject, $html);
            $mailSent = $result['success'];

            if (!$mailSent) {
                error_log('Erreur envoi email réinitialisation : ' . $result['message']);
            }
        }

        if ($mailSent) {
            $success = 'Un email de réinitialisation a été envoyé à <strong>' . htmlspecialchars($email) . '</strong> (si cette adresse est associée à un compte).';
        } else {
            $success = 'Un email de réinitialisation a été envoyé si cette adresse est associée à un compte.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
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
        <h1>Mot de passe oublié</h1>
        <p>Entrez votre adresse email, nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>

        <?php if ($error): ?>
            <div style="color:#b91c1c;background:#fee2e2;padding:10px;border-radius:8px;margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="color:#065f46;background:#d1fae5;padding:10px;border-radius:8px;margin-bottom:15px;"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div style="margin-bottom:15px;">
                <label for="email" style="display:block;font-weight:600;margin-bottom:5px;">Adresse email</label>
                <input type="email" id="email" name="email" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;" required autofocus value="<?= htmlspecialchars($submittedEmail) ?>">
            </div>
            <button type="submit" style="width:100%;padding:12px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:16px;cursor:pointer;">Envoyer le lien</button>
        </form>
        <p style="margin-top:15px;"><a href="login.php">Retour à la connexion</a></p>
    </main>
</body>
</html>
