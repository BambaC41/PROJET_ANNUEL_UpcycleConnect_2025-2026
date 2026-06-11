<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/documents.php';
require_once 'includes/functions/local_db.php';

$docs = document_list_for_user((int)($_SESSION['user_id'] ?? 0));

// Récupérer les factures impayées
$unpaidInvoices = db_safe_exec(function(PDO $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM facture WHERE id_user = ? AND statut = 'impayee'");
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetchAll();
}, []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Documents particulier - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>Mes documents</h1>
        <table class="table">
            <thead>
                <tr><th>Type</th><th>Titre</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($docs)): ?>
                <tr><td colspan="4">Aucun document disponible.</td></tr>
            <?php else: foreach ($docs as $d): ?>
                <tr>
                    <td><?= e($d['type'] ?? '') ?></td>
                    <td><?= e($d['titre'] ?? '') ?></td>
                    <td><?= e(formatDateFr($d['created_at'] ?? '')) ?></td>
                    <td><a class="btn-outline" href="document_download.php?id=<?= (int)($d['id_document'] ?? 0) ?>">Voir / Télécharger</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($unpaidInvoices)): ?>
        <h2 style="margin-top:20px;">Factures impayées</h2>
        <table class="table">
            <thead>
                <tr><th>N° Facture</th><th>Montant</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($unpaidInvoices as $inv): ?>
                <tr>
                    <td><?= e($inv['numero'] ?? '') ?></td>
                    <td><?= e(formatPriceEur($inv['montant_ttc'] ?? 0)) ?></td>
                    <td><?= e(formatDateFr($inv['created_at'] ?? '')) ?></td>
                    <td>
                        <a class="btn-primary" href="paiement_stripe.php?amount=<?= ($inv['montant_ttc'] ?? 0) * 100 ?>&item=Facture+<?= urlencode($inv['numero'] ?? '') ?>&facture_id=<?= $inv['id_facture'] ?>">
                            Payer cette facture
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>