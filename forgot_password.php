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
    $submittedEmail = $email;

    if (empty($email)) {
        $error = 'Veuillez saisir votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $user = find_user_by_email($email);

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

            if (!$result['success']) {
                error_log('Erreur envoi email réinitialisation : ' . $result['message']);
            }
        }

        // Message personnalisé avec l'email saisi
        $success = '✅ Un email de réinitialisation a été envoyé à l\'adresse <strong>' . htmlspecialchars($email) . '</strong> (si elle est associée à un compte).';
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
    <style>
        /* Header public minimal (comme sur login.php) */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }
        .navbar .logo a {
            font-size: 1.5rem;
            font-weight: 700;
            color: #16a34a;
            text-decoration: none;
        }
        .navbar .auth-buttons a {
            margin-left: 15px;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .btn-outline {
            color: #333;
            border: 1px solid #ccc;
            background: transparent;
        }
        .btn-outline:hover {
            background: #f5f5f5;
        }
        .btn-primary {
            background: #16a34a;
            color: #fff;
            border: none;
        }
        .btn-primary:hover {
            background: #15803d;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #15803d;
        }
        .alert-danger {
            color: #b91c1c;
            background: #fee2e2;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success {
            color: #065f46;
            background: #d1fae5;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .back-link {
            margin-top: 15px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="logo"><a href="index.php">UpcycleConnect</a></div>
        <nav class="auth-buttons">
            <a class="btn-outline" href="index.php">Accueil</a>
            <a class="btn-outline" href="login.php">Connexion</a>
            <a class="btn-primary" href="register.php">Créer un compte</a>
        </nav>
    </header>

    <main class="container">
        <h1>Mot de passe oublié</h1>
        <p>Entrez votre adresse email, nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>

        <?php if ($error): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($submittedEmail) ?>" required autofocus>
            </div>
            <button type="submit" class="btn-submit">Envoyer le lien</button>
        </form>
        <p class="back-link"><a href="login.php">Retour à la connexion</a></p>
    </main>
</body>
</html>