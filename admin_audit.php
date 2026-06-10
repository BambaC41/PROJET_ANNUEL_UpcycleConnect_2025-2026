<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
$rows = db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT a.*, u.email FROM audit_log a JOIN utilisateur u ON u.id_user=a.id_user ORDER BY a.id_audit DESC LIMIT 300')->fetchAll(), []);
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
        <h1>Journal d'audit</h1>
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(formatDateFr($r['created_at'] ?? '')) ?></td>
                    <td><?= e($r['email'] ?? '') ?></td>
                    <td><?= e($r['action'] ?? '') ?></td>
                    <td><?= e(($r['cible_type'] ?? '') . ' #' . ($r['cible_id'] ?? '')) ?></td>
                    <td><?= e($r['details'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
</main>
</body>
</html>
