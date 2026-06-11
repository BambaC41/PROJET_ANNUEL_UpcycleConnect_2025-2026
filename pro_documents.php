<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/documents.php';
$docs = document_list_for_user((int)($_SESSION['user_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Documents pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>Documents professionnels</h1>
        <table class="table">
            <thead><tr><th>Type</th><th>Titre</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($docs)): ?>
                <tr><td colspan="4">Aucun document disponible.</td></tr>
            <?php else: foreach ($docs as $d): ?>
                <tr>
                    <td><?= e($d['type'] ?? '') ?></td>
                    <td><?= e($d['titre'] ?? '') ?></td>
                    <td><?= e(formatDateFr($d['created_at'] ?? '')) ?></td>
                    <td><a class="btn-outline" href="document_download.php?id=<?= (int)($d['id_document'] ?? 0) ?>">Voir / Telecharger</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
