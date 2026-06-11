<?php
session_start();

if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

// 🔥 Récupérer l'inscription_id depuis l'URL
$inscriptionId = isset($_GET['inscription_id']) ? $_GET['inscription_id'] : '';
$amount = isset($_GET['amount']) ? intval($_GET['amount']) : 2999;
$itemName = isset($_GET['item']) ? $_GET['item'] : 'Paiement UpcycleConnect';
$prestationId = isset($_GET['prestation_id']) ? $_GET['prestation_id'] : '';
$annonceId = isset($_GET['annonce_id']) ? $_GET['annonce_id'] : '';
$factureId = isset($_GET['facture_id']) ? $_GET['facture_id'] : '';

// 🔥 LOG pour debug
error_log("💰 Stripe: Inscription ID = " . $inscriptionId);
error_log("💰 Stripe: Amount = " . $amount);

$payload = [
    'amount' => $amount,
    'item_name' => $itemName,
    'inscription_id' => $inscriptionId,
    'prestation_id' => $prestationId,
    'annonce_id' => $annonceId,
    'facture_id' => $factureId
];

$ch = curl_init('http://localhost:8080/create-checkout-session');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $_SESSION['token'],
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['url'])) {
        header('Location: ' . $data['url']);
        exit;
    }
    echo "Erreur: " . ($data['error'] ?? 'Impossible de créer la session');
} else {
    echo "Erreur HTTP: " . $httpCode . "<br>" . htmlspecialchars($response);
}
?>