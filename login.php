<?php
session_start();

if (isset($_SESSION['token'])) {
    header("Location: admin.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $url = "http://localhost:8081/login";
    $data = json_encode([
        "email" => $email,
        "password" => $password
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $result = json_decode($response, true);
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