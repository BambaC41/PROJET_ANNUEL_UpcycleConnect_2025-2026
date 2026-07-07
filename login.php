<?php
session_start();
require_once 'includes/functions/auth.php';
require_once 'includes/i18n.php';
set_lang_from_request();

function role_home(?int $roleId): string
{
    if ($roleId === 1) return 'admin.php';
    if ($roleId === 2) return 'particulier.php';
    if ($roleId === 3) return 'pro.php';
    if ($roleId === 4) return 'salarie.php';
    return 'index.php';
}

if ((int)($_GET['switch'] ?? 0) === 1 || (int)($_GET['change'] ?? 0) === 1) {
    session_unset();
    session_destroy();
    session_start();
}

$error = "";
$info = '';
$alreadyConnected = isset($_SESSION['token']) && !empty($_SESSION['token']);
if (isset($_GET['logged_out'])) {
    $info = 'Vous avez été déconnecté.';
}
if ((int)($_GET['change'] ?? 0) === 1) {
    $info = 'Session précédente fermée. Connectez-vous avec un autre compte.';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = api_login($email, $password);

    if ($result['status'] === 200 && isset($result['data'])) {
        $data = $result['data'];

        if (!empty($data['token'])) {
            session_unset();
            $_SESSION['token'] = $data['token'];
            $_SESSION['role_id'] = $data['role_id'] ?? null;
            $_SESSION['user_id'] = $data['user_id'] ?? null;
            $_SESSION['is_approved'] = $data['is_approved'] ?? null;
            $_SESSION['prompt_unread_notifs'] = true;

            if (($data['role_id'] ?? null) == 1) {
                header("Location: admin.php");
                exit();
            } elseif (($data['role_id'] ?? null) == 2) {
                header("Location: particulier.php");
                exit();
            } elseif (($data['role_id'] ?? null) == 3) {
                if (empty($data['is_approved'])) {
                    header("Location: pro_non_approuve_chat.php");
                    exit();
                }
                header("Location: pro.php");
                exit();
            } elseif (($data['role_id'] ?? null) == 4) {
                header("Location: salarie.php");
                exit();
            } else {
                $error = "Rôle utilisateur inconnu.";
            }
        } else {
            $error = "Réponse API invalide.";
        }
    } else {
        $apiError = strtolower((string)($result['error'] ?? ''));
        if (str_contains($apiError, 'invalid credentials')) {
            $error = "Mot de passe incorrect.";
        } elseif (str_contains($apiError, 'pending admin approval') || str_contains($apiError, 'not approved') || str_contains($apiError, 'appr')) {
            $error = "Compte professionnel en attente de validation.";
        } elseif (str_contains($apiError, 'banned')) {
            $error = "Compte suspendu. Contactez l'administration.";
        } else {
            $error = "Connexion impossible pour le moment.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title>Connexion - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body>
<header class="navbar">
    <div class="logo"><a href="index.php" style="text-decoration:none;color:#16a34a;">UpcycleConnect</a></div>
    <nav class="auth-buttons">
        <a class="btn-outline" href="index.php"><?= htmlspecialchars(t('nav.home')) ?></a>
        <a class="btn-primary" href="register.php"><?= htmlspecialchars(t('nav.register')) ?></a>
        <a class="btn-outline" href="<?= htmlspecialchars(lang_url('fr')) ?>">FR</a>
        <a class="btn-outline" href="<?= htmlspecialchars(lang_url('en')) ?>">EN</a>
    </nav>
</header>

<main class="auth-page public-auth-page">
    <div class="auth-card">
        <h1><?= htmlspecialchars(t('auth.login_title')) ?></h1>
        <p class="muted">Connectez-vous a votre espace UpcycleConnect.</p>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($info): ?>
            <p class="success"><?= htmlspecialchars($info) ?></p>
        <?php endif; ?>

        <?php if ($alreadyConnected): ?>
            <div class="success-box" style="margin-bottom:12px;">Vous êtes déjà connecté.</div>
            <div class="cta-actions">
                <a class="btn-primary" href="<?= htmlspecialchars(role_home((int)($_SESSION['role_id'] ?? 0))) ?>">Continuer vers mon espace</a>
                <a class="btn-outline" href="login.php?change=1">Changer de compte</a>
            </div>
        <?php else: ?>
        <form method="POST" class="auth-form">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="exemple@mail.com" required>

            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password" placeholder="••••••••••••" required>

            <!-- Lien mot de passe oublié -->
            <div style="text-align:right; margin-top:-5px; margin-bottom:10px;">
                <a href="forgot_password.php" style="font-size:0.9rem; color:#16a34a; text-decoration:none;">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Se connecter</button>
        </form>
        <?php endif; ?>
    </div>

    <section class="register-cta">
        <h2>Vous n'avez pas encore de compte ?</h2>
        <p class="muted">Créez votre compte en quelques minutes.</p>
        <div class="cta-actions">
            <a class="btn-primary" href="register.php">Creer un compte</a>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>