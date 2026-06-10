<?php
declare(strict_types=1);

require_once __DIR__ . '/local_db.php';
require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/../notifications.php';

/**
 * @return array{ok:bool, payment_ref?:string, payment_id?:int, amount?:float, error?:string}
 */
function demo_payment_create(
    int $userId,
    float $amount,
    string $label,
    ?int $inscriptionId,
    string $paymentType = 'inscription',
    ?int $relatedAnnonceId = null,
): array {
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Utilisateur invalide (user_id).'];
    }

    $allowed = ['inscription', 'abonnement_pro', 'campagne_publicitaire', 'achat_annonce'];
    if (!in_array($paymentType, $allowed, true)) {
        return ['ok' => false, 'error' => 'Type de paiement inconnu: ' . $paymentType];
    }

    $pdo = null;
    try {
        $pdo = db_pdo();
        $pdo->beginTransaction();

        $ref = 'PAY-DEMO-' . date('YmdHis') . '-' . random_int(100, 999);
        $sql = 'INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, created_at, id_inscription, payment_provider, amount, currency, status, user_id)
                VALUES ("demo", ?, ?, "EUR", "paid", NOW(), NOW(), ?, "demo", ?, "EUR", "paid", ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ref, $amount, $inscriptionId, $amount, $userId]);
        $paymentId = (int)$pdo->lastInsertId();

        $invoiceNo = 'FAC-' . date('Y') . '-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
        $inv = $pdo->prepare('INSERT INTO facture (numero, id_user, id_paiement, montant_ht, montant_ttc, statut, created_at) VALUES (?, ?, ?, ?, ?, "generee", NOW())');
        $inv->execute([$invoiceNo, $userId, $paymentId, round($amount / 1.2, 2), $amount]);

        if ($paymentType === 'abonnement_pro') {
            $ab = $pdo->prepare('INSERT INTO abonnement_pro (id_pro, formule, date_debut, date_fin, prix, statut) VALUES (?, "premium", CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, "actif")');
            $ab->execute([$userId, $amount]);
        }

        if ($paymentType === 'campagne_publicitaire') {
            $campTitle = trim($label) !== '' ? $label : 'Campagne publicitaire';
            $camp = $pdo->prepare('INSERT INTO campagne_publicitaire (id_pro, titre, budget, statut, date_debut) VALUES (?, ?, ?, "payee", CURDATE())');
            $camp->execute([$userId, $campTitle, $amount]);
        }

        if ($paymentType === 'achat_annonce') {
            if ($relatedAnnonceId === null || $relatedAnnonceId <= 0) {
                throw new RuntimeException('Annonce cible manquante.');
            }
            $u = $pdo->prepare('UPDATE annonce SET id_acheteur = ?, date_achat = NOW() WHERE id_annonce = ? AND mode = "vente" AND statut = "validee" AND id_acheteur IS NULL AND id_user <> ?');
            $u->execute([$userId, $relatedAnnonceId, $userId]);
            if ($u->rowCount() === 0) {
                throw new RuntimeException('Annonce indisponible, déjà vendue ou non valide.');
            }
        }

        $html = '<h1>UpcycleConnect - Recu de paiement</h1>'
            . '<p><strong>Reference:</strong> ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Type:</strong> ' . htmlspecialchars($paymentType, ENT_QUOTES, 'UTF-8') . ' — ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Montant:</strong> ' . number_format($amount, 2, ',', ' ') . ' EUR</p>'
            . '<p><strong>Date:</strong> ' . date('d/m/Y H:i') . '</p>'
            . '<p>Document genere automatiquement (demonstration).</p>';

        try {
            document_create_html($userId, 'recu_paiement', 'Recu ' . $label, $html, $paymentId, null, $inscriptionId, [
                'Recu de paiement — UpcycleConnect (demonstration)',
                'Reference : ' . $ref,
                'Type : ' . $paymentType . ' — ' . $label,
                'Montant TTC : ' . number_format($amount, 2, ',', ' ') . ' EUR',
                'Date : ' . date('d/m/Y H:i'),
            ]);
        } catch (Throwable) {
            @file_put_contents(
                sys_get_temp_dir() . '/upcycle_doc_fail.log',
                date('c') . " document_create_html failed for payment $paymentId\n",
                FILE_APPEND
            );
        }

        $pdo->commit();

        notif_create($userId, 'paiement', 'Paiement confirme', 'Votre paiement demo a ete confirme (' . $paymentType . '): ' . $label . '.');

        if ($paymentType === 'abonnement_pro' || $paymentType === 'campagne_publicitaire') {
            notif_notify_roles([1], 'paiement', 'Paiement professionnel', 'Un pro a payé en démo : ' . $label . ' (' . number_format($amount, 2, ',', ' ') . ' EUR).');
        }

        return ['ok' => true, 'payment_ref' => $ref, 'payment_id' => $paymentId, 'amount' => $amount];
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
