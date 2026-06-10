<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/functions/view_context.php';

if (empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$userId = session_ensure_user_id();
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$paymentType = trim((string)($_GET['payment_type'] ?? 'inscription'));
$amount = (float)($_GET['amount'] ?? 0);
$label = trim((string)($_GET['label'] ?? 'Paiement démonstration'));
$inscriptionId = isset($_GET['inscription_id']) ? (int)$_GET['inscription_id'] : null;
$relatedAnnonceId = isset($_GET['related_id']) ? (int)$_GET['related_id'] : null;

$roleId = (int)($_SESSION['role_id'] ?? 0);

if ($paymentType === 'inscription') {
    if ($roleId !== 2) {
        $_SESSION['flash_message'] = 'Checkout inscription réservé aux comptes particuliers.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
}
if (in_array($paymentType, ['abonnement_pro', 'campagne_publicitaire', 'achat_annonce'], true)) {
    if ($roleId !== 3) {
        $_SESSION['flash_message'] = 'Ce paiement est réservé aux comptes professionnels.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
}

$backHref = $roleId === 3 ? 'pro_billing.php' : 'particulier_planning.php';
if ($paymentType === 'achat_annonce') {
    $backHref = 'pro_annonces.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement démo — Checkout</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body class="auth-page">
<main class="auth-card" style="max-width:520px;margin:40px auto;">
    <h1>Paiement en mode démonstration</h1>
    <p class="muted">Aucune carte réelle ne sera débitée. Ceci simule un tunnel de paiement pour la soutenance.</p>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;margin:14px 0;font-size:14px;">
        <strong>Résumé</strong>
        <ul style="margin:8px 0 0 18px;padding:0;">
            <li>Type : <code><?= e($paymentType) ?></code></li>
            <li>Montant : <strong><?= e(number_format($amount, 2, ',', ' ')) ?> EUR</strong></li>
            <li>Référence libellé : <?= e($label) ?></li>
            <li>Utilisateur : #<?= e((string)$userId) ?></li>
            <?php if ($inscriptionId): ?><li>Inscription : #<?= e((string)$inscriptionId) ?></li><?php endif; ?>
            <?php if ($relatedAnnonceId): ?><li>Annonce liée : #<?= e((string)$relatedAnnonceId) ?></li><?php endif; ?>
        </ul>
    </div>

    <form method="POST" action="paiement_demo.php" style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
        <input type="hidden" name="payment_type" value="<?= e($paymentType) ?>">
        <input type="hidden" name="amount" value="<?= e((string)$amount) ?>">
        <input type="hidden" name="label" value="<?= e($label) ?>">
        <?php if ($inscriptionId): ?><input type="hidden" name="inscription_id" value="<?= e((string)$inscriptionId) ?>"><?php endif; ?>
        <?php if ($relatedAnnonceId): ?><input type="hidden" name="related_id" value="<?= e((string)$relatedAnnonceId) ?>"><?php endif; ?>

        <label for="demo_card_number">Numéro de carte (fictif)</label>
        <input class="input" id="demo_card_number" name="demo_card_number" autocomplete="off" placeholder="4242 4242 4242 4242" required>

        <label for="demo_card_exp">Expiration (MM/AA)</label>
        <input class="input" id="demo_card_exp" name="demo_card_exp" autocomplete="off" placeholder="12/34" required>

        <label for="demo_card_cvc">CVC</label>
        <input class="input" id="demo_card_cvc" name="demo_card_cvc" autocomplete="off" placeholder="123" required maxlength="4">

        <label for="demo_card_name">Nom sur la carte</label>
        <input class="input" id="demo_card_name" name="demo_card_name" autocomplete="off" placeholder="DEMO UTILISATEUR" required>

        <p class="muted" style="font-size:13px;margin:0;">Les données saisies ne sont pas stockées (aucun numéro de carte conservé).</p>

        <div class="row-actions" style="flex-wrap:wrap;">
            <button class="btn-primary" type="submit">Confirmer le paiement démo</button>
            <a class="btn-outline" href="<?= e($backHref) ?>">Retour</a>
            <a class="btn-outline" href="paiement_cancel.php?reason=checkout_abandon">Annuler</a>
        </div>
    </form>
</main>
</body>
</html>
