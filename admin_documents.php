<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/documents.php';
$docs = document_list_all();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Documents générés</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>📄 Documents générés</h1>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Utilisateur</th><th>Type</th><th>Titre</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($docs)): ?>
                    <tr><td colspan="6" style="text-align:center;">Aucun document généré.</td></tr>
                <?php else: foreach ($docs as $d): ?>
                    <tr>
                        <td><?= e($d['id_document'] ?? '') ?></td>
                        <td><?= e($d['email'] ?? '') ?></td>
                        <td><?= e($d['type'] ?? '') ?></td>
                        <td><?= e($d['titre'] ?? '') ?></td>
                        <td><?= e(formatDateFr($d['created_at'] ?? '')) ?></td>
                        <td class="row-actions"><a class="btn-outline" href="document_download.php?id=<?= (int)($d['id_document'] ?? 0) ?>">📄 Voir</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>