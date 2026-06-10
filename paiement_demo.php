<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/functions/demo_payments.php';
require_once __DIR__ . '/includes/functions/local_db.php';

if (empty($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$userId = session_ensure_user_id();
if ($userId <= 0) {
    $_SESSION['flash_message'] = 'Session invalide : impossible de résoudre votre utilisateur.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = 'Merci d’utiliser la page checkout démo.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

$cardNum = preg_replace('/\s+/', '', (string)($_POST['demo_card_number'] ?? ''));
$cardExp = trim((string)($_POST['demo_card_exp'] ?? ''));
$cardCvc = trim((string)($_POST['demo_card_cvc'] ?? ''));
$cardName = trim((string)($_POST['demo_card_name'] ?? ''));

if ($cardNum === '' || $cardExp === '' || $cardCvc === '' || $cardName === '') {
    $_SESSION['flash_message'] = 'Formulaire de carte fictive incomplet — repassez par le checkout démo.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

if ($cardNum !== '4242424242424242') {
    $_SESSION['flash_message'] = 'Pour la démo, utilisez le numéro 4242 4242 4242 4242.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

if ($cardExp !== '12/34') {
    $_SESSION['flash_message'] = 'Pour la démo, utilisez la date d’expiration 12/34.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

if ($cardCvc !== '123') {
    $_SESSION['flash_message'] = 'Pour la démo, utilisez le CVC 123.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

$paymentType = trim((string)($_POST['payment_type'] ?? 'inscription'));
$amount = (float)($_POST['amount'] ?? 0);
$label = trim((string)($_POST['label'] ?? 'Paiement demonstration'));
$inscriptionId = !empty($_POST['inscription_id']) ? (int)$_POST['inscription_id'] : null;
$relatedAnnonceId = !empty($_POST['related_id']) ? (int)$_POST['related_id'] : null;

$roleId = (int)($_SESSION['role_id'] ?? 0);

if ($paymentType === 'inscription') {
    if ($roleId !== 2) {
        $_SESSION['flash_message'] = 'Paiement inscription réservé à l’espace particulier.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
    if ($inscriptionId !== null) {
        $isOwner = (bool)db_safe_exec(function (PDO $pdo) use ($inscriptionId, $userId) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscription WHERE id_inscription = ? AND id_user = ?');
            $stmt->execute([$inscriptionId, $userId]);
            return (int)$stmt->fetchColumn() > 0;
        }, false);
        if (!$isOwner) {
            $_SESSION['flash_message'] = 'Inscription invalide pour ce compte.';
            $_SESSION['flash_type'] = 'error';
            header('Location: paiement_cancel.php');
            exit;
        }
        $alreadyPaid = (bool)db_safe_exec(function (PDO $pdo) use ($inscriptionId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM paiement WHERE id_inscription = ? AND (status = 'paid' OR statut = 'paid')");
            $stmt->execute([$inscriptionId]);
            return (int)$stmt->fetchColumn() > 0;
        }, false);
        if ($alreadyPaid) {
            $_SESSION['flash_message'] = 'Paiement déjà enregistré pour cette inscription.';
            $_SESSION['flash_type'] = 'success';
            header('Location: paiement_success.php');
            exit;
        }
    }
    if ($amount <= 0) {
        $_SESSION['flash_message'] = 'Inscription gratuite : aucun paiement nécessaire.';
        $_SESSION['flash_type'] = 'success';
        header('Location: particulier_planning.php');
        exit;
    }
}

if (in_array($paymentType, ['abonnement_pro', 'campagne_publicitaire', 'achat_annonce'], true)) {
    if ($roleId !== 3) {
        $_SESSION['flash_message'] = 'Ce type de paiement est réservé aux comptes professionnels.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
    if ($amount <= 0) {
        $_SESSION['flash_message'] = 'Montant invalide pour ce paiement professionnel.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
    if ($paymentType === 'achat_annonce' && ($relatedAnnonceId === null || $relatedAnnonceId <= 0)) {
        $_SESSION['flash_message'] = 'Annonce cible manquante pour l’achat.';
        $_SESSION['flash_type'] = 'error';
        header('Location: paiement_cancel.php');
        exit;
    }
}

if ($paymentType !== 'inscription' && !in_array($paymentType, ['abonnement_pro', 'campagne_publicitaire', 'achat_annonce'], true)) {
    $_SESSION['flash_message'] = 'Type de paiement non pris en charge.';
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

$result = demo_payment_create($userId, $amount, $label, $inscriptionId, $paymentType, $relatedAnnonceId);

if (empty($result['ok'])) {
    $_SESSION['flash_message'] = 'Paiement démo impossible : ' . (string)($result['error'] ?? 'erreur inconnue');
    $_SESSION['flash_type'] = 'error';
    header('Location: paiement_cancel.php');
    exit;
}

$okMsg = 'Paiement démo confirmé. Référence : ' . (string)($result['payment_ref'] ?? '');
$_SESSION['flash_message'] = $okMsg;
$_SESSION['flash_type'] = 'success';
$_SESSION['flash_toast'] = ['type' => 'success', 'message' => $okMsg];
$_SESSION['payment_ref'] = (string)($result['payment_ref'] ?? '');
$_SESSION['payment_amount'] = (float)($result['amount'] ?? $amount);
$_SESSION['payment_type'] = $paymentType;
header('Location: paiement_success.php');
exit;
