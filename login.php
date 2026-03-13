<?php
session_start();
require_once 'includes/functions/auth.php';

if (isset($_SESSION['token'])) {
    header("Location: admin.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Appel propre via functions/auth.php
    $result = api_login($email, $password);

    if ($result['status'] === 200) {
        // Le décodage JSON est déjà fait par api_core
        $result = $result['data'];
        if (isset($result['token'])) {
            $_SESSION['token'] = $result['token'];
            header("Location: admin.php");
            exit();
        }
    } else {
        $error = "Identifiants incorrects ou serveur indisponible.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body>
<?php include 'includes/header.php'; ?>
<main class="auth-page">
    <div class="auth-card">
        <h1>Connexion Admin</h1>
        <p class="muted">Accès réservé au personnel UpcycleConnect</p>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="POST" class="auth-form">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="admin@upcycleconnect.fr" required>
            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password" placeholder="••••••••" required>
            <button type="submit" class="btn-primary" style="margin-top: 10px;">Se connecter</button>
        </form>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>