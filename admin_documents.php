<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/documents.php';
$docs = document_list_all();
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <section class="admin-section">
        <h1>Documents generes</h1>
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Utilisateur</th><th>Type</th><th>Titre</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($docs)): ?>
                <tr><td colspan="6">Aucun document genere.</td></tr>
            <?php else: foreach ($docs as $d): ?>
                <tr>
                    <td><?= e($d['id_document'] ?? '') ?></td>
                    <td><?= e($d['email'] ?? '') ?></td>
                    <td><?= e($d['type'] ?? '') ?></td>
                    <td><?= e($d['titre'] ?? '') ?></td>
                    <td><?= e(formatDateFr($d['created_at'] ?? '')) ?></td>
                    <td><a class="btn-outline" href="document_download.php?id=<?= (int)($d['id_document'] ?? 0) ?>">Voir</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>
</section>
</main>
</body>
</html>
