<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$reason = trim((string)($_GET['reason'] ?? ''));
$msg = $_SESSION['flash_message'] ?? 'Paiement annule.';
if ($reason === 'checkout_abandon') {
    $msg = 'Paiement abandonné depuis la page de checkout démo.';
} elseif ($reason !== '') {
    $msg = 'Opération annulée : ' . $reason . '.';
}
$ftype = $_SESSION['flash_type'] ?? 'error';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$role = (int)($_SESSION['role_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement annule</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-card">
        <h1><?= $ftype === 'error' ? 'Paiement impossible' : 'Information' ?></h1>
        <p><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="row-actions" style="flex-wrap:wrap;">
            <a class="btn-outline" href="<?= $role === 3 ? 'pro.php' : ($role === 4 ? 'salarie.php' : ($role === 1 ? 'admin.php' : 'particulier.php')) ?>">Mon espace</a>
            <?php if ($role === 2): ?>
                <a class="btn-outline" href="particulier_planning.php">Planning</a>
                <a class="btn-outline" href="particulier_catalogue.php">Catalogue</a>
            <?php elseif ($role === 3): ?>
                <a class="btn-outline" href="pro_billing.php">Facturation</a>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
