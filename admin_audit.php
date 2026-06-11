<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';

$rows = db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT a.*, u.email FROM audit_log a JOIN utilisateur u ON u.id_user=a.id_user ORDER BY a.id_audit DESC LIMIT 300')->fetchAll(), []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Journal d'audit</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>📋 Journal d'audit</h1>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>Details</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(formatDateFr($r['created_at'] ?? '')) ?></td>
                        <td><?= e($r['email'] ?? '') ?></td>
                        <td><span class="status-badge status-info"><?= e($r['action'] ?? '') ?></span></td>
                        <td><?= e(($r['cible_type'] ?? '') . ' #' . ($r['cible_id'] ?? '')) ?></td>
                        <td><?= e($r['details'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>