<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';

$proId = (int)$_SESSION['user_id'];

// Récupérer l'historique des récupérations effectuées par ce professionnel
$history = db_safe_exec(function (PDO $pdo) use ($proId) {
    $sql = '
        SELECT r.id_retrait, r.collected_at, r.notes,
               o.titre AS objet_titre, c.code AS conteneur_code,
               d.id_demande, d.statut
        FROM retrait r
        JOIN code_barre cb ON cb.id_code_barre = r.id_code_barre
        JOIN demande_depot d ON d.id_demande = cb.id_demande
        JOIN objet o ON o.id_objet = d.id_objet
        JOIN conteneur c ON c.id_conteneur = d.id_conteneur
        WHERE r.id_user = ?
        ORDER BY r.collected_at DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$proId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);

$totalRecuperations = count($history);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des récupérations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #2e7d32, #4caf50);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-card h2 {
            margin: 0;
            font-size: 36px;
        }
        .stats-card p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1>📜 Historique des récupérations</h1>
        
        <div class="stats-card">
            <h2><?= $totalRecuperations ?></h2>
            <p>Objets récupérés</p>
        </div>
        
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Objet</th><th>Conteneur</th><th>Notes</th></tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="4" style="text-align:center;">Aucune recuperation effectuee pour le moment.<?php endif; ?>
                
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= formatDateFr($h['collected_at'] ?? '') ?></td>
                        <td><strong><?= htmlspecialchars($h['objet_titre'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($h['conteneur_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($h['notes'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="pro_conteneurs.php" class="btn-outline">← Retour aux conteneurs</a>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>