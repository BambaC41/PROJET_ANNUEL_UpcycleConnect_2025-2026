<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/local_db.php';

$userId = (int)$_SESSION['user_id'];

// Récupérer le score via API
$score = api_get_my_score()['data'] ?? [];

// Si l'API ne renvoie pas de score, calcul local
if (empty($score) || !isset($score['score_global'])) {
    $score = db_safe_exec(function(PDO $pdo) use ($userId) {
        $data = [
            'total_score' => 0,
            'annonces_validees' => 0,
            'depots_realises' => 0,
            'inscriptions' => 0,
            'poids_total_kg' => 0,
        ];

        // Annonces validées
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM annonce WHERE id_user = ? AND statut = "validee"');
        $stmt->execute([$userId]);
        $data['annonces_validees'] = (int)$stmt->fetchColumn();

        // Dépôts réalisés
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM demande_depot WHERE id_user = ? AND statut = "deposee"');
        $stmt->execute([$userId]);
        $data['depots_realises'] = (int)$stmt->fetchColumn();

        // Inscriptions
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscription WHERE id_user = ? AND statut = "confirmee"');
        $stmt->execute([$userId]);
        $data['inscriptions'] = (int)$stmt->fetchColumn();

        // Poids total
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(o.poids), 0) FROM demande_depot d LEFT JOIN objet o ON d.id_objet = o.id_objet WHERE d.id_user = ? AND d.statut = "deposee"');
        $stmt->execute([$userId]);
        $data['poids_total_kg'] = (float)$stmt->fetchColumn();

        // Calcul du score
        $data['total_score'] = ($data['annonces_validees'] * 10) + ($data['depots_realises'] * 15) + ($data['inscriptions'] * 20);
        $data['score_global'] = $data['total_score'];

        return $data;
    }, ['score_global' => 0, 'annonces_validees' => 0, 'depots_realises' => 0, 'inscriptions' => 0, 'poids_total_kg' => 0, 'total_score' => 0]);
}

$totalScore = (int)($score['score_global'] ?? $score['total_score'] ?? 0);

// Badge
if ($totalScore >= 200) {
    $badge = 'Ambassadeur';
    $badgeColor = '#ffc107';
} elseif ($totalScore >= 100) {
    $badge = 'Recycleur actif';
    $badgeColor = '#28a745';
} else {
    $badge = 'Débutant';
    $badgeColor = '#6c757d';
}

$co2Estimate = (float)($score['poids_total_kg'] ?? 0) * 2;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Upcycling Score - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; border-radius: 12px; text-align: center; margin-bottom: 30px;">
            <h1 style="margin: 0 0 10px 0;">♻️ Upcycling Score</h1>
            <div style="font-size: 56px; font-weight: 700; margin: 10px 0;"><?= e($totalScore) ?></div>
            <div style="display: inline-block; padding: 12px 24px; background: <?= e($badgeColor) ?>; border-radius: 20px; font-size: 16px; font-weight: 600; margin-top: 16px; border: 2px solid rgba(255,255,255,0.4);">
                🏆 <?= e($badge) ?>
            </div>
        </div>

        <h2>📊 Vos contributions</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px;">
            <div class="pro-card" style="text-align:center;">
                <div style="font-size: 32px;">📦</div>
                <div style="font-size: 24px; font-weight: 700; color: #667eea;"><?= (int)($score['annonces_validees'] ?? 0) ?></div>
                <div style="font-size: 13px; color: #999;">Annonces validées</div>
            </div>
            <div class="pro-card" style="text-align:center;">
                <div style="font-size: 32px;">📍</div>
                <div style="font-size: 24px; font-weight: 700; color: #667eea;"><?= (int)($score['depots_realises'] ?? 0) ?></div>
                <div style="font-size: 13px; color: #999;">Dépôts réalisés</div>
            </div>
            <div class="pro-card" style="text-align:center;">
                <div style="font-size: 32px;">📚</div>
                <div style="font-size: 24px; font-weight: 700; color: #667eea;"><?= (int)($score['inscriptions'] ?? 0) ?></div>
                <div style="font-size: 13px; color: #999;">Formations/Ateliers</div>
            </div>
            <div class="pro-card" style="text-align:center;">
                <div style="font-size: 32px;">⚖️</div>
                <div style="font-size: 24px; font-weight: 700; color: #667eea;"><?= round((float)($score['poids_total_kg'] ?? 0), 1) ?> kg</div>
                <div style="font-size: 13px; color: #999;">Poids détourné</div>
            </div>
        </div>

        <h2>🧮 Calcul du score</h2>
        <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #ddd;">
                <span>Annonces validées</span>
                <span><strong><?= (int)($score['annonces_validees'] ?? 0) ?> × 10 pts = <?= (int)($score['annonces_validees'] ?? 0) * 10 ?> pts</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #ddd;">
                <span>Dépôts réalisés</span>
                <span><strong><?= (int)($score['depots_realises'] ?? 0) ?> × 15 pts = <?= (int)($score['depots_realises'] ?? 0) * 15 ?> pts</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0;">
                <span>Formations/Ateliers</span>
                <span><strong><?= (int)($score['inscriptions'] ?? 0) ?> × 20 pts = <?= (int)($score['inscriptions'] ?? 0) * 20 ?> pts</strong></span>
            </div>
        </div>

        <h2>🌍 Impact écologique estimé</h2>
        <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #ddd;">
                <span>Poids détourné des déchets</span>
                <span><strong><?= round((float)($score['poids_total_kg'] ?? 0), 2) ?> kg</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #ddd;">
                <span>CO₂ non émis (estimation)</span>
                <span><strong><?= round($co2Estimate, 2) ?> kg</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 12px 0;">
                <span>Équivalent à</span>
                <span><strong><?= round($co2Estimate / 4.6, 1) ?> km en voiture évités</strong></span>
            </div>
        </div>

        <div style="background: #e7f3ff; border-left: 4px solid #2196f3; border-radius: 6px; padding: 16px; margin-top: 20px;">
            <strong>💡 Comment augmenter votre score ?</strong><br>
            • Créez et proposez des annonces de dons/ventes (10 pts)<br>
            • Déposez vos objets dans nos conteneurs (15 pts)<br>
            • Participez à des formations et ateliers (20 pts)<br>
            • Publiez des projets d'upcycling (25 pts)
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>