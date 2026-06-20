<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/salarie_stats.php';
require_once __DIR__ . '/includes/ui_helpers.php';
require_once __DIR__ . '/includes/functions/chat_local.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];

$stats = salarie_dashboard_stats($token, $userId);
$events = $stats['events'];
$pending = $stats['pending'];
$validated = $stats['validated'];
$totalEvents = $stats['total_events'];
$draftConseils = $stats['conseil_draft'];
$publishedConseils = $stats['conseil_published'];

$allConseils = salarie_conseils_merge_local([], $userId);

$upcomingEvents = array_filter($events, function($e) {
    $start = strtotime((string)($e['date_debut'] ?? ''));
    return $start !== false && $start > time();
});
usort($upcomingEvents, fn($a, $b) => strtotime((string)($a['date_debut'] ?? '')) <=> strtotime((string)($b['date_debut'] ?? '')));
$upcomingEvents = array_slice($upcomingEvents, 0, 5);

$unreadCount = chat_get_unread_count($userId);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Salarié - UpcycleConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .welcome-banner h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2e7d32;
        }
        .stat-label {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }
        .stat-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
            display: block;
        }
        .action-card:hover {
            border-color: #4caf50;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .action-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .action-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 8px 0;
        }
        .action-desc {
            color: #666;
            font-size: 13px;
            margin: 0;
        }
        .recent-list {
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .recent-item:last-child {
            border-bottom: none;
        }
        .recent-item:hover {
            background: #f8f9fa;
        }
        .recent-title {
            font-weight: 600;
            color: #1a1a2e;
        }
        .recent-meta {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-valide { background: #e8f5e9; color: #2e7d32; }
        .badge-en_attente { background: #fff3e0; color: #ef6c00; }
        .badge-annule { background: #fee2e2; color: #dc2626; }
        .badge-publie { background: #e8f5e9; color: #2e7d32; }
        .badge-brouillon { background: #f5f5f5; color: #757575; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #4caf50;
            color: #2e7d32;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-outline:hover {
            background: #4caf50;
            color: white;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .actions-grid { grid-template-columns: 1fr; }
            .recent-item { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/employee_nav.php'; ?>

<main class="pro-shell page-shell">
   
    <div class="welcome-banner">
        <div>
            <h1>👋 Bonjour, <?= e($_SESSION['pseudo'] ?? 'Salarié') ?> !</h1>
            <p>Bienvenue sur votre espace formateur et animateur UpcycleConnect</p>
        </div>
       
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🎓</div>
            <div class="stat-value"><?= (int)$totalEvents ?></div>
            <div class="stat-label">Événements créés</div>
            <small class="muted"><?= (int)$validated ?> validés</small>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= (int)$pending ?></div>
            <div class="stat-label">En attente de validation</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💡</div>
            <div class="stat-value"><?= (int)$draftConseils ?></div>
            <div class="stat-label">Conseils en brouillon</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= (int)$publishedConseils ?></div>
            <div class="stat-label">Conseils publiés</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-value"><?= (int)$unreadCount ?></div>
            <div class="stat-label">Messages non lus</div>
        </div>
    </div>

    
    <div class="chart-container">
        <h3 style="margin: 0 0 16px 0;">📊 Activité du mois</h3>
        <canvas id="activityChart" height="80" style="max-height: 200px;"></canvas>
    </div>

    <div class="actions-grid">
        <a href="salarie_events.php" class="action-card">
            <div class="action-icon">🎓</div>
            <div class="action-title">Événements</div>
            <div class="action-desc">Créez et gérez vos formations et ateliers</div>
        </a>
        <a href="salarie_planning.php" class="action-card">
            <div class="action-icon">🗓️</div>
            <div class="action-title">Planning</div>
            <div class="action-desc">Consultez votre agenda hebdomadaire</div>
        </a>
        <a href="salarie_conseils.php" class="action-card">
            <div class="action-icon">💡</div>
            <div class="action-title">Conseils & News</div>
            <div class="action-desc">Rédigez des articles et astuces</div>
        </a>
        <a href="forum.php" class="action-card">
            <div class="action-icon">💬</div>
            <div class="action-title">Modération forum</div>
            <div class="action-desc">Gérez les signalements et sujets</div>
        </a>
        <a href="salarie_chat.php" class="action-card">
            <div class="action-icon">💬</div>
            <div class="action-title">Messagerie</div>
            <div class="action-desc">Discutez avec les membres de la communauté</div>
        </a>
    </div>

    <div class="action-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0;">📅 Prochains événements</h3>
        </div>
        <div>
            <?php if (empty($upcomingEvents)): ?>
                <div class="empty-state">
                    <p>Aucun événement à venir</p>
                    <a href="salarie_events.php" class="btn-outline" style="margin-top: 12px; display: inline-block;">Créer un événement</a>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingEvents as $ev): ?>
                    <?php
                    $statut = (string)($ev['statut'] ?? 'en_attente');
                    $badgeClass = match($statut) {
                        'valide' => 'badge-valide',
                        'en_attente' => 'badge-en_attente',
                        'annule' => 'badge-annule',
                        default => 'badge-en_attente'
                    };
                    $badgeText = match($statut) {
                        'valide' => '✅ Validé',
                        'en_attente' => '⏳ En attente',
                        'annule' => '❌ Annulé',
                        default => $statut
                    };
                    ?>
                    <div class="recent-item">
                        <div>
                            <div class="recent-title"><?= e($ev['_title'] ?? $ev['prestation_titre'] ?? 'Événement') ?></div>
                            <div class="recent-meta">
                                📍 <?= e($ev['lieu'] ?? 'Lieu non défini') ?> • 
                                🕐 <?= formatDateFr($ev['date_debut'] ?? '') ?>
                            </div>
                        </div>
                        <span class="badge-status <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="action-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0;">📝 Vos derniers conseils</h3>
        </div>
        <div>
            <?php if (empty($allConseils)): ?>
                <div class="empty-state">
                    <p>Aucun conseil rédigé</p>
                    <a href="salarie_conseils.php" class="btn-outline" style="margin-top: 12px; display: inline-block;">Rédiger un conseil</a>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($allConseils, 0, 5) as $c): ?>
                    <?php
                    $isActive = !empty($c['is_active']);
                    $badgeClass = $isActive ? 'badge-publie' : 'badge-brouillon';
                    $badgeText = $isActive ? '✅ Publié' : '📝 Brouillon';
                    ?>
                    <div class="recent-item">
                        <div>
                            <div class="recent-title"><?= e($c['titre'] ?? 'Sans titre') ?></div>
                            <div class="recent-meta">
                                📂 <?= e($c['categorie'] ?? 'Non catégorisé') ?> • 
                                🕐 <?= formatDateFr($c['created_at'] ?? '') ?>
                            </div>
                        </div>
                        <span class="badge-status <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (count($allConseils) > 5): ?>
                    <div style="padding: 12px 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                        <a href="salarie_conseils.php" class="btn-outline">Voir tous les conseils →</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>

const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [{
            label: 'Événements créés',
            data: [<?= rand(0, 5) ?>, <?= rand(0, 5) ?>, <?= rand(0, 5) ?>, <?= rand(0, 5) ?>, <?= rand(0, 5) ?>, <?= rand(0, 5) ?>, <?= rand(0, 5) ?>],
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Conseils publiés',
            data: [<?= rand(0, 3) ?>, <?= rand(0, 3) ?>, <?= rand(0, 3) ?>, <?= rand(0, 3) ?>, <?= rand(0, 3) ?>, <?= rand(0, 3) ?>, <?= rand(0, 3) ?>],
            borderColor: '#2e7d32',
            backgroundColor: 'rgba(46, 125, 50, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top' }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>