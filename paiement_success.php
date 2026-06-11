<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';
require_once 'includes/functions/paiements.php';

$sessionId = $_GET['session_id'] ?? '';
$msg = 'Paiement confirmé.';
$ref = $sessionId;
$amount = null;
$ptype = '';

if ($sessionId) {
    $ch = curl_init('http://localhost:8080/verify-payment?session_id=' . urlencode($sessionId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (isset($_SESSION['token'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $_SESSION['token']]);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("🔍 VerifyPayment response: " . $response);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['paid'] ?? false) {
            $amount = $data['amount'] ?? null;
            $metadata = $data['metadata'] ?? [];
            $ptype = $metadata['item_name'] ?? 'paiement';
            $userId = (int)($_SESSION['user_id'] ?? 0);
            
            error_log("💰 Metadata reçues: " . print_r($metadata, true));
            error_log("💰 Inscription ID dans metadata: " . ($metadata['inscription_id'] ?? 'NON TROUVE'));
            
            db_safe_exec(function(PDO $pdo) use ($userId, $amount, $sessionId, $metadata) {
                $stmt = $pdo->prepare("INSERT INTO paiement (user_id, montant, statut, provider, payment_ref, paid_at, metadata) VALUES (?, ?, 'paid', 'stripe', ?, NOW(), ?)");
                $stmt->execute([$userId, $amount, $sessionId, json_encode($metadata)]);
                return true;
            }, false);
            
            // Mettre à jour le statut de l'inscription
            if (!empty($metadata['inscription_id'])) {
                error_log("✅ Mise à jour inscription ID: " . $metadata['inscription_id']);
                db_safe_exec(function(PDO $pdo) use ($metadata) {
                    $stmt = $pdo->prepare("UPDATE inscription SET statut = 'confirmee' WHERE id_inscription = ?");
                    $stmt->execute([$metadata['inscription_id']]);
                    return true;
                }, false);
            } else {
                error_log("⚠️ Aucun inscription_id dans les métadonnées !");
            }
            
            notif_create($userId, 'paiement_stripe', '✅ Paiement confirmé', "Votre paiement de {$amount}€ a été reçu.");
        }
    }
}

$role = (int)($_SESSION['role_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement confirmé - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .success-card {
            max-width: 600px;
            margin: 80px auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
        }
        .success-header {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            padding: 32px;
            color: white;
        }
        .success-header h1 {
            margin: 0;
            font-size: 28px;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        .success-body {
            padding: 32px;
        }
        .payment-details {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .payment-details p {
            margin: 8px 0;
        }
        .payment-details strong {
            color: #2e7d32;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .btn-primary {
            background: #4caf50;
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #2e7d32;
            transform: translateY(-2px);
        }
        .btn-outline {
            border: 2px solid #4caf50;
            color: #4caf50;
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-outline:hover {
            background: #4caf50;
            color: white;
        }
        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #2e7d32;
        }
    </style>
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body style="background: #f0f2f5; min-height: 100vh;">
<main>
    <div class="success-card">
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1>Paiement confirmé !</h1>
        </div>
        <div class="success-body">
            <p>Merci pour votre confiance. Votre transaction a été réalisée avec succès.</p>
            
            <div class="payment-details">
                <p><strong>🔖 Référence :</strong> <?= htmlspecialchars(substr($ref, 0, 20) . '...', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>🏷️ Type :</strong> <?= htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>💰 Montant :</strong> <span class="amount"><?= htmlspecialchars(number_format((float)$amount, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</span></p>
            </div>
            
            <p>📄 Le reçu / facture est disponible dans l'espace <strong>Documents</strong>.</p>
            
            <div class="btn-group">
                <?php if ($role === 3): ?>
                    <a class="btn-primary" href="pro.php">🏠 Dashboard pro</a>
                    <a class="btn-outline" href="pro_billing.php">💰 Facturation</a>
                    <a class="btn-outline" href="pro_documents.php">📄 Documents</a>
                <?php elseif ($role === 2): ?>
                    <a class="btn-primary" href="particulier_planning.php">🗓️ Retour planning</a>
                    <a class="btn-outline" href="particulier_documents.php">📄 Documents</a>
                    <a class="btn-outline" href="particulier.php">🏠 Dashboard</a>
                <?php else: ?>
                    <a class="btn-primary" href="index.php">🏠 Accueil</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>