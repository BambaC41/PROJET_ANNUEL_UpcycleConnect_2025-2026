<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';
require_once 'includes/functions/paiements.php';

$sessionId = $_GET['session_id'] ?? '';
$ref = $sessionId;
$amount = null;
$ptype = '';
$metadata = [];

if ($sessionId) {
    $ch = curl_init('http://localhost:8080/verify-payment?session_id=' . urlencode($sessionId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (isset($_SESSION['token'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $_SESSION['token']]);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['paid'] ?? false) {
            $amount = $data['amount'] ?? null;
            $metadata = $data['metadata'] ?? [];
            $ptype = $metadata['item_name'] ?? 'paiement';
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $annonceId = $metadata['annonce_id'] ?? '';
            
            if (empty($annonceId) && preg_match('/\d+/', $ptype, $matches)) {
                $annonceId = $matches[0];
            }
            
            // Achat d'annonce + commission
            if (!empty($annonceId) && !str_contains($ptype, 'Commission')) {
                $annonce = db_safe_exec(function(PDO $pdo) use ($annonceId) {
                    $stmt = $pdo->prepare("
                        SELECT a.id_annonce, a.id_user as vendeur_id, a.prix, o.titre 
                        FROM annonce a 
                        JOIN objet o ON o.id_objet = a.id_objet 
                        WHERE a.id_annonce = ?
                    ");
                    $stmt->execute([$annonceId]);
                    return $stmt->fetch(PDO::FETCH_ASSOC);
                }, null);
                
                if ($annonce) {
                    $vendeurId = (int)$annonce['vendeur_id'];
                    $prix = (float)($annonce['prix'] ?? 0);
                    $commission = round($prix * 0.05, 2);
                    
                    db_safe_exec(function(PDO $pdo) use ($annonceId, $userId) {
                        $stmt = $pdo->prepare("UPDATE annonce SET id_acheteur = ?, date_achat = NOW(), statut = 'vendu' WHERE id_annonce = ?");
                        return $stmt->execute([$userId, $annonceId]);
                    }, false);
                    
                    if ($commission > 0) {
                        db_safe_exec(function(PDO $pdo) use ($annonceId, $vendeurId, $userId, $commission) {
                            $stmt = $pdo->prepare("
                                INSERT INTO commissions (annonce_id, vendeur_id, acheteur_id, montant, statut, created_at) 
                                VALUES (?, ?, ?, ?, 'due', NOW())
                            ");
                            return $stmt->execute([$annonceId, $vendeurId, $userId, $commission]);
                        }, false);
                        error_log("💰 Commission de $commission € enregistrée pour l'annonce $annonceId");
                    }
                    
                    db_safe_exec(function(PDO $pdo) use ($userId, $amount, $sessionId, $metadata) {
                        $stmt = $pdo->prepare("INSERT INTO paiement (user_id, montant, statut, provider, payment_ref, paid_at, metadata) VALUES (?, ?, 'paid', 'stripe', ?, NOW(), ?)");
                        return $stmt->execute([$userId, $amount, $sessionId, json_encode($metadata)]);
                    }, false);
                    
                    notif_create($userId, 'achat', '✅ Achat confirmé', "Achat effectué pour " . number_format($amount, 2, ',', ' ') . " €");
                }
            }
            
            // Paiement de commission
            if (!empty($annonceId) && str_contains($ptype, 'Commission')) {
                db_safe_exec(function(PDO $pdo) use ($annonceId, $amount, $userId, $sessionId, $metadata) {
                    $pdo->prepare("UPDATE commissions SET statut = 'paid', paid_at = NOW() WHERE annonce_id = ?")->execute([$annonceId]);
                    $pdo->prepare("UPDATE annonce SET commission_payee = 1, commission_payee_at = NOW() WHERE id_annonce = ?")->execute([$annonceId]);
                    
                    $stmt = $pdo->prepare("INSERT INTO paiement (user_id, montant, statut, provider, payment_ref, paid_at, metadata) VALUES (?, ?, 'paid', 'stripe', ?, NOW(), ?)");
                    $stmt->execute([$userId, $amount, $sessionId, json_encode($metadata)]);
                    return true;
                }, false);
                error_log("💰 Commission payée pour l'annonce $annonceId");
            }
            
            // Abonnement Premium
            if (str_contains($ptype, 'Abonnement') || ($metadata['type'] ?? '') === 'abonnement') {
                $formule = $metadata['formule'] ?? 'monthly';
                $formuleDb = $formule === 'monthly' ? 'premium_mensuel' : 'premium_annuel';
                $dateFin = $formule === 'monthly' ? date('Y-m-d', strtotime('+1 month')) : date('Y-m-d', strtotime('+1 year'));
                
                db_safe_exec(function(PDO $pdo) use ($userId, $formuleDb, $amount, $dateFin) {
                    $pdo->prepare("UPDATE abonnement_pro SET statut = 'expire' WHERE id_pro = ? AND statut = 'actif'")->execute([$userId]);
                    
                    $stmt = $pdo->prepare("SELECT id_abonnement FROM abonnement_pro WHERE id_pro = ? AND formule = ?");
                    $stmt->execute([$userId, $formuleDb]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $pdo->prepare("UPDATE abonnement_pro SET statut = 'actif', date_debut = CURDATE(), date_fin = ?, prix = ? WHERE id_pro = ? AND formule = ?")
                            ->execute([$dateFin, $amount, $userId, $formuleDb]);
                    } else {
                        $pdo->prepare("INSERT INTO abonnement_pro (id_pro, formule, statut, date_debut, date_fin, prix, created_at) VALUES (?, ?, 'actif', CURDATE(), ?, ?, NOW())")
                            ->execute([$userId, $formuleDb, $dateFin, $amount]);
                    }
                    return true;
                }, false);
                
                notif_create($userId, 'abonnement', '⭐ Abonnement Premium activé', "Votre abonnement a été activé !");
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
    <link rel="stylesheet" href="styles/admin_global.css">
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
            <p>Merci pour votre confiance.</p>
            <div class="payment-details">
                <p><strong>🔖 Référence :</strong> <?= htmlspecialchars(substr($ref, 0, 20) . '...', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>🏷️ Type :</strong> <?= htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>💰 Montant :</strong> <span class="amount"><?= number_format((float)$amount, 2, ',', ' ') ?> €</span></p>
            </div>
            <div class="btn-group">
                <a class="btn-primary" href="<?= $role === 3 ? 'pro_annonces.php' : ($role === 2 ? 'particulier.php' : 'index.php') ?>">Retour</a>
                <a class="btn-outline" href="pro_documents.php">📄 Documents</a>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>