<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/local_db.php';

$userId = (int)$_SESSION['user_id'];

// Récupérer le score via API si disponible
$score = api_get_my_score()['data'] ?? null;

// Fallback: calculer le score localement
if (!$score) {
    $score = db_safe_exec(function(PDO $pdo) use ($userId) {
        $data = [
            'total_score' => 0,
            'annonces_validees' => 0,
            'depots_realises' => 0,
            'inscriptions' => 0,
            'poids_total_kg' => 0,
        ];

        // Annonces validées
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt FROM annonce 
            WHERE id_user = ? AND statut = "validee"
        ');
        $stmt->execute([$userId]);
        $data['annonces_validees'] = (int)($stmt->fetch()['cnt'] ?? 0);

        // Dépôts réalisés
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt FROM demande_depot 
            WHERE id_user = ? AND statut = "deposited"
        ');
        $stmt->execute([$userId]);
        $data['depots_realises'] = (int)($stmt->fetch()['cnt'] ?? 0);

        // Inscriptions à des événements
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt FROM inscription 
            WHERE id_user = ? AND statut = "confirmee"
        ');
        $stmt->execute([$userId]);
        $data['inscriptions'] = (int)($stmt->fetch()['cnt'] ?? 0);

        // Poids total déposé
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(o.poids), 0) as poids FROM demande_depot d
            LEFT JOIN objet o ON d.id_objet = o.id_objet
            WHERE d.id_user = ? AND d.statut = "deposited"
        ');
        $stmt->execute([$userId]);
        $data['poids_total_kg'] = (float)($stmt->fetch()['poids'] ?? 0);

        // Calcul du score: 10 pts par annonce + 15 pts par dépôt + 20 pts par inscription
        $data['total_score'] = ($data['annonces_validees'] * 10) + 
                               ($data['depots_realises'] * 15) + 
                               ($data['inscriptions'] * 20);

        return $data;
    }, [
        'total_score' => 0,
        'annonces_validees' => 0,
        'depots_realises' => 0,
        'inscriptions' => 0,
        'poids_total_kg' => 0,
    ]);
}

// Déterminer le badge
$badge = 'Débutant';
$badgeColor = '#6c757d';
if (($score['total_score'] ?? 0) >= 200) {
    $badge = 'Ambassadeur';
    $badgeColor = '#ffc107';
} elseif (($score['total_score'] ?? 0) >= 100) {
    $badge = 'Recycleur actif';
    $badgeColor = '#28a745';
}

// Estimation CO2 (grossièrement: 1kg de déchet détourné ≈ 2kg de CO2 not produced)
$co2Estimate = ($score['poids_total_kg'] ?? 0) * 2;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Upcycling Score</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        .score-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 30px;
        }
        .score-main {
            font-size: 56px;
            font-weight: 700;
            margin: 10px 0;
        }
        .badge-display {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 16px;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }
        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .score-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .score-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        .score-card-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .score-card-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        .score-card-label {
            font-size: 13px;
            color: #999;
            margin-top: 6px;
        }
        .impact-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .impact-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        .impact-item:last-child {
            border-bottom: none;
        }
        .impact-label {
            font-weight: 500;
            color: #333;
        }
        .impact-value {
            font-weight: 700;
            color: #667eea;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            border-radius: 6px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 24px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 600;
            transition: width 0.3s;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <div class="score-header">
            <h1 style="margin: 0 0 10px 0;">Upcycling Score</h1>
            <div class="score-main"><?= (int)($score['total_score'] ?? 0) ?></div>
            <div class="badge-display" style="background-color: <?= e($badgeColor) ?>; border-color: rgba(255,255,255,0.3);">
                🏆 <?= e($badge) ?>
            </div>
            <p style="margin: 16px 0 0 0; opacity: 0.9; font-size: 14px;">
                Score estimatif basé sur vos contributions à l'upcycling
            </p>
        </div>

        <h2 style="margin-top: 30px;">Vos contributions</h2>
        <div class="score-grid">
            <div class="score-card">
                <div class="score-card-icon">📦</div>
                <div class="score-card-value"><?= (int)($score['annonces_validees'] ?? 0) ?></div>
                <div class="score-card-label">Annonces validées</div>
            </div>
            <div class="score-card">
                <div class="score-card-icon">📍</div>
                <div class="score-card-value"><?= (int)($score['depots_realises'] ?? 0) ?></div>
                <div class="score-card-label">Dépôts réalisés</div>
            </div>
            <div class="score-card">
                <div class="score-card-icon">📚</div>
                <div class="score-card-value"><?= (int)($score['inscriptions'] ?? 0) ?></div>
                <div class="score-card-label">Formations/Ateliers</div>
            </div>
            <div class="score-card">
                <div class="score-card-icon">⚖️</div>
                <div class="score-card-value"><?= round((float)($score['poids_total_kg'] ?? 0), 1) ?> kg</div>
                <div class="score-card-label">Poids détourné</div>
            </div>
        </div>

        <h2>Calcul du score</h2>
        <div class="impact-section">
            <div class="impact-item">
                <span class="impact-label">Annonces validées</span>
                <span class="impact-value"><?= (int)($score['annonces_validees'] ?? 0) ?> × 10 pts = <?= (int)($score['annonces_validees'] ?? 0) * 10 ?> pts</span>
            </div>
            <div class="impact-item">
                <span class="impact-label">Dépôts réalisés</span>
                <span class="impact-value"><?= (int)($score['depots_realises'] ?? 0) ?> × 15 pts = <?= (int)($score['depots_realises'] ?? 0) * 15 ?> pts</span>
            </div>
            <div class="impact-item">
                <span class="impact-label">Formations/Ateliers</span>
                <span class="impact-value"><?= (int)($score['inscriptions'] ?? 0) ?> × 20 pts = <?= (int)($score['inscriptions'] ?? 0) * 20 ?> pts</span>
            </div>
        </div>

        <h2>Impact écologique estimé</h2>
        <div class="impact-section">
            <div class="impact-item">
                <span class="impact-label">Poids détourné des déchets</span>
                <span class="impact-value"><?= round((float)($score['poids_total_kg'] ?? 0), 2) ?> kg</span>
            </div>
            <div class="impact-item">
                <span class="impact-label">CO₂ not produced (estimation)</span>
                <span class="impact-value"><?= round($co2Estimate, 2) ?> kg</span>
            </div>
            <div class="impact-item">
                <span class="impact-label">Équivalent à</span>
                <span class="impact-value"><?= round($co2Estimate / 4.6, 1) ?> km en voiture évités</span>
            </div>
        </div>

        <h2>Progression vers le prochain badge</h2>
        <?php if (($score['total_score'] ?? 0) < 100): ?>
            <p style="font-size: 13px; color: #666;">Vous êtes <strong>Débutant</strong>. Atteindrez 100 pts pour devenir <strong>Recycleur actif</strong>.</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, (($score['total_score'] ?? 0) / 100) * 100) ?>%;">
                    <?= (int)min(100, (($score['total_score'] ?? 0) / 100) * 100) ?>%
                </div>
            </div>
        <?php elseif (($score['total_score'] ?? 0) < 200): ?>
            <p style="font-size: 13px; color: #666;">Vous êtes <strong>Recycleur actif</strong>. Atteindrez 200 pts pour devenir <strong>Ambassadeur</strong>.</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, ((($score['total_score'] ?? 0) - 100) / 100) * 100) ?>%;">
                    <?= (int)min(100, ((($score['total_score'] ?? 0) - 100) / 100) * 100) ?>%
                </div>
            </div>
        <?php else: ?>
            <p style="font-size: 13px; color: #666;">🌟 Vous êtes <strong>Ambassadeur</strong> ! Continuez à inspirer la communauté.</p>
        <?php endif; ?>

        <div class="info-box">
            <strong>💡 Comment augmenter votre score ?</strong><br>
            • Créez et proposez des annonces de dons/ventes<br>
            • Déposez vos objets dans nos conteneurs<br>
            • Participez à des formations et ateliers<br>
            • Engagez-vous auprès de la communauté
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>
