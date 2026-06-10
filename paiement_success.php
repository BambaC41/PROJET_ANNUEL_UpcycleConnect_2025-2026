<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$msg = $_SESSION['flash_message'] ?? 'Paiement confirme.';
$ref = $_SESSION['payment_ref'] ?? '';
$amount = $_SESSION['payment_amount'] ?? null;
$ptype = $_SESSION['payment_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$role = (int)($_SESSION['role_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirme</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-card">
        <h1>Paiement confirme</h1>
        <p><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($ref !== ''): ?><p><strong>Référence:</strong> <?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($ptype !== ''): ?><p><strong>Type:</strong> <?= htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($amount !== null && $amount !== ''): ?><p><strong>Montant:</strong> <?= htmlspecialchars(number_format((float)$amount, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> EUR</p><?php endif; ?>
        <p>Le reçu / facture est disponible dans vos documents.</p>
        <div class="row-actions" style="flex-wrap:wrap;">
            <?php if ($role === 3): ?>
                <a class="btn-primary" href="pro.php">Retour dashboard pro</a>
                <a class="btn-outline" href="pro_billing.php">Facturation</a>
                <a class="btn-outline" href="pro_documents.php">Documents</a>
            <?php elseif ($role === 2): ?>
                <a class="btn-primary" href="particulier_planning.php">Retour planning</a>
                <a class="btn-outline" href="particulier_documents.php">Documents</a>
                <a class="btn-outline" href="particulier.php">Dashboard</a>
            <?php else: ?>
                <a class="btn-primary" href="index.php">Accueil</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
