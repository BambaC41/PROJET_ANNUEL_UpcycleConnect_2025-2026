<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
$status = trim((string)($_GET['status'] ?? 'all'));
$paiements = api_get_my_paiements()['data'] ?? [];
$filtered = array_values(array_filter($paiements, fn($p) => $status === 'all' || (($p['statut'] ?? '') === $status)));
$total = array_reduce($filtered, function($c, $p){ return $c + (float)($p['montant'] ?? 0); }, 0);
$abonnements = db_safe_exec(function(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM abonnement_pro WHERE id_pro = ? ORDER BY id_abonnement DESC');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetchAll();
}, []);
$campagnes = db_safe_exec(function(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM campagne_publicitaire WHERE id_pro = ? ORDER BY id_campagne DESC');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetchAll();
}, []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facturation Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-kpis">
        <article class="pro-kpi"><h3>Total facturé</h3><p><?= e(formatPriceEur($total)) ?></p></article>
        <article class="pro-kpi"><h3>Paiements</h3><p><?= e(count($paiements)) ?></p></article>
    </section>
    <section class="pro-card">
        <h1>Contrats, abonnements et publicités</h1>
        <div class="row-actions page-actions" style="flex-wrap:wrap;gap:12px;">
            <a class="btn-primary" href="paiement_checkout_demo.php?<?= e(http_build_query([
                'payment_type' => 'abonnement_pro',
                'amount' => 49.0,
                'label' => 'Abonnement premium',
            ])) ?>">Payer abonnement premium (démo)</a>
            <a class="btn-outline" href="paiement_checkout_demo.php?<?= e(http_build_query([
                'payment_type' => 'campagne_publicitaire',
                'amount' => 120.0,
                'label' => 'Campagne publicitaire',
            ])) ?>">Payer campagne publicitaire (démo)</a>
        </div>
        <h2 style="margin-top:16px;">Abonnements</h2>
        <ul><?php foreach ($abonnements as $a): ?><li><?= e(($a['formule'] ?? '')) ?> - <?= e(($a['statut'] ?? '')) ?> (<?= e($a['date_debut'] ?? '') ?> au <?= e($a['date_fin'] ?? '') ?>)</li><?php endforeach; ?></ul>
        <h2>Campagnes</h2>
        <ul><?php foreach ($campagnes as $c): ?><li><?= e($c['titre'] ?? '') ?> - <?= e($c['statut'] ?? '') ?> - Budget <?= e(formatPriceEur($c['budget'] ?? 0)) ?></li><?php endforeach; ?></ul>
        <form method="GET" class="row-actions">
            <select class="input" name="status">
                <option value="all" <?= $status==='all'?'selected':'' ?>>Tous statuts</option>
                <option value="paid" <?= $status==='paid'?'selected':'' ?>>Payé</option>
                <option value="pending" <?= $status==='pending'?'selected':'' ?>>En attente</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        <table class="table">
            <thead><tr><th>Référence</th><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($filtered as $p): ?>
                <tr>
                    <td><?= e($p['payment_ref'] ?? '') ?></td>
                    <td><?= e(formatDateFr($p['paid_at'] ?? $p['created_at'] ?? '')) ?></td>
                    <td><?= e(formatPriceEur($p['montant'] ?? 0)) ?></td>
                    <td><?= e($p['statut'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
