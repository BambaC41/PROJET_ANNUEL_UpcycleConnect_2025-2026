<?php
require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/salarie_stats.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$token = $_SESSION['token'];
$userId = (int)$_SESSION['user_id'];

$stats = salarie_dashboard_stats($token, $userId);
$events = $stats['events'];
$pending = $stats['pending'];
$validated = $stats['validated'];
$totalEvents = $stats['total_events'];
$draftConseils = $stats['conseil_draft'];
$publishedConseils = $stats['conseil_published'];

$upcomingEvents = array_filter($events, function($e) {
    $start = strtotime((string)($e['date_debut'] ?? ''));
    return $start !== false && $start > time();
});
usort($upcomingEvents, fn($a, $b) => strtotime((string)($a['date_debut'] ?? '')) <=> strtotime((string)($b['date_debut'] ?? '')));
$upcomingEvents = array_slice($upcomingEvents, 0, 3);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord - Salarié</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/employee.css">
</head>
<body class="employee-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="employee-shell">
    <!-- Welcome Section -->
    <section class="emp-card" style="background:linear-gradient(135deg,#2196f3 0%,#1976d2 100%);color:white;margin-bottom:24px;">
        <h1 style="margin:0 0 8px 0;font-size:24px;">👩‍🏫 Bienvenue dans l'espace salarié</h1>
        <p style="margin:0;opacity:0.95;">Créez des événements, proposez des conseils et suivez vos validations.</p>
    </section>

    <!-- KPIs -->
    <section class="emp-kpis">
        <div class="emp-kpi">
            <div class="k">Événements soumis</div>
            <div class="v"><?= (int)$totalEvents ?></div>
        </div>
        <div class="emp-kpi">
            <div class="k">En attente</div>
            <div class="v"><?= (int)$pending ?></div>
        </div>
        <div class="emp-kpi">
            <div class="k">Validés</div>
            <div class="v"><?= (int)$validated ?></div>
        </div>
        <div class="emp-kpi">
            <div class="k">Conseils brouillon</div>
            <div class="v"><?= (int)$draftConseils ?></div>
        </div>
    </section>

    <!-- Accès rapide -->
    <section class="emp-grid" style="margin-bottom:24px;">
        <div class="emp-card">
            <h3>🎓 Événements</h3>
            <p>Créez et gérez vos formations, ateliers et événements en attente de validation.</p>
            <a class="btn-primary" href="salarie_events.php" style="text-decoration:none;display:inline-block;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Gérer les événements</a>
        </div>
        <div class="emp-card">
            <h3>🗓️ Planning</h3>
            <p>Consultez votre planning hebdomadaire avec tous vos événements programmés.</p>
            <a class="btn-primary" href="salarie_planning.php" style="text-decoration:none;display:inline-block;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Voir le planning</a>
        </div>
        <div class="emp-card">
            <h3>💡 Conseils & News</h3>
            <p>Rédigez des conseils et des news en brouillon, puis soumettez à validation.</p>
            <a class="btn-primary" href="salarie_conseils.php" style="text-decoration:none;display:inline-block;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Gérer les conseils</a>
        </div>
        <div class="emp-card">
            <h3>💬 Forum</h3>
            <p>Consultez et modérez les signalements du forum communautaire.</p>
            <a class="btn-primary" href="salarie_forum.php" style="text-decoration:none;display:inline-block;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Accéder au forum</a>
        </div>
    </section>

    <!-- Prochains événements -->
    <?php if (!empty($upcomingEvents)): ?>
        <section class="emp-card" style="margin-bottom:24px;">
            <h2 style="margin:0 0 16px 0;font-size:16px;border-bottom:2px solid #2196f3;padding-bottom:12px;">📅 Vos 3 prochains événements</h2>
            <div class="employee-items">
                <?php foreach ($upcomingEvents as $ev): ?>
                    <?php
                    $statut = (string)($ev['statut'] ?? 'en_attente');
                    $badgeClass = $statut === 'valide' ? 'badge-validated' : 'badge-pending';
                    $badgeText = $statut === 'valide' ? 'Validé' : 'En attente';
                    $dateDebut = formatDateFr($ev['date_debut'] ?? '');
                    ?>
                    <div class="employee-item">
                        <div class="employee-item-content">
                            <div class="employee-item-title"><?= e($ev['_title'] ?? $ev['prestation_titre'] ?? 'Événement') ?></div>
                            <div class="employee-item-meta">
                                📍 <?= e($ev['lieu'] ?? 'Lieu TBD') ?> | 🕐 <?= e($dateDebut) ?>
                            </div>
                        </div>
                        <span class="badge-status <?= e($badgeClass) ?>" style="margin-left:12px;"><?= e($badgeText) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="emp-card" style="margin-bottom:24px;text-align:center;padding:30px;">
            <p style="margin:0;color:#999;">📭 Aucun événement prévu pour les prochains jours</p>
            <a class="btn-primary" href="salarie_events.php" style="display:inline-block;margin-top:12px;text-decoration:none;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Créer un événement</a>
        </section>
    <?php endif; ?>

    <!-- Derniers conseils -->
    <?php if (!empty($allConseils)): ?>
        <section class="emp-card">
            <h2 style="margin:0 0 16px 0;font-size:16px;border-bottom:2px solid #2196f3;padding-bottom:12px;">📝 Vos derniers conseils/news</h2>
            <div class="employee-items">
                <?php foreach (array_slice($allConseils, 0, 3) as $c): ?>
                    <?php
                    $isActive = !empty($c['is_active']);
                    $badgeClass = $isActive ? 'badge-published' : 'badge-draft';
                    $badgeText = $isActive ? 'Publié' : 'Brouillon';
                    ?>
                    <div class="employee-item">
                        <div class="employee-item-content">
                            <div class="employee-item-title"><?= e($c['titre'] ?? 'Sans titre') ?></div>
                            <div class="employee-item-meta">
                                📂 <?= e($c['categorie'] ?? 'Catégorie') ?>
                            </div>
                        </div>
                        <span class="badge-status <?= e($badgeClass) ?>" style="margin-left:12px;"><?= e($badgeText) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="btn-primary" href="salarie_conseils.php" style="display:inline-block;margin-top:12px;text-decoration:none;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;font-size:13px;">Gérer tous les conseils →</a>
        </section>
    <?php else: ?>
        <section class="emp-card" style="margin-bottom:24px;text-align:center;padding:30px;">
            <p style="margin:0;color:#999;">Aucun conseil pour le moment</p>
            <a class="btn-primary" href="salarie_conseils.php" style="display:inline-block;margin-top:12px;text-decoration:none;padding:8px 16px;background:#2196f3;color:white;border-radius:6px;">Rédiger un conseil</a>
        </section>
    <?php endif; ?>

    <section class="emp-card emp-forum-preview">
        <h2>Forum salarié</h2>
        <p class="muted">Échangez avec les autres animateurs / formateurs : sujets, réponses et signalements.</p>
        <a class="btn-outline" href="salarie_forum.php">Accéder au forum →</a>
    </section>
</main>
</body>
</html>

