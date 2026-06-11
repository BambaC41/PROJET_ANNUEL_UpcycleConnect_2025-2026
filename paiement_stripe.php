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

// 🔥 Stocker l'inscription_id en session pour le récupérer après paiement
if (!empty($inscriptionId)) {
    $_SESSION['pending_inscription_id'] = $inscriptionId;
    error_log("💰 Stocké inscription_id en session: " . $inscriptionId);
}

$payload = [
    'amount' => $amount,
    'item_name' => $itemName,
    'inscription_id' => $inscriptionId,
    'prestation_id' => $prestationId,
    'annonce_id' => $annonceId,
    'facture_id' => $factureId,
    'session_id' => session_id()
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
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("❌ Curl error: " . $curlError);
    echo "Erreur de connexion à l'API.";
    exit;
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['url'])) {
        header('Location: ' . $data['url']);
        exit;
    }
    echo "Erreur: " . ($data['error'] ?? 'Impossible de créer la session');
} else {
    error_log("❌ HTTP Error: " . $httpCode . " - " . $response);
    echo "Erreur HTTP: " . $httpCode . "<br>" . htmlspecialchars($response);
}
?>