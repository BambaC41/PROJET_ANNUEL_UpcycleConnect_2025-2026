<?php
session_start();
require_once 'includes/functions/users.php';
require_once 'includes/functions/prestations.php';
require_once 'includes/functions/events.php';

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}

$users = [];
$api_error = '';

$fetched_users = api_get_users($_SESSION['token']);

if (!empty($fetched_users) && is_array($fetched_users)) {
    $users = array_slice($fetched_users, 0, 6); // Limit to 6 users for the dashboard view
} else {
    $api_error = "Impossible de charger les utilisateurs depuis l'API.";
}

$roles = [ 1 => 'ADMIN', 2 => 'STAFF', 3 => 'USER', 4 => 'PRO' ];

// Chargement des prestations
$prestations = [];
$prestationsMap = [];
$api_error_prest = '';
$fetched_prestations = api_get_prestations($_SESSION['token']);
if (!empty($fetched_prestations) && is_array($fetched_prestations)) {
    $prestations = array_slice($fetched_prestations, 0, 6);
    foreach ($fetched_prestations as $p) {
        if (isset($p['id'])) {
            $prestationsMap[$p['id']] = $p['titre'] ?? 'Inconnu';
        }
    }
} else {
    $api_error_prest = "Impossible de charger les prestations depuis l'API.";
}

// Chargement des catégories
$categories = [];
$fetchedCategories = api_get_categories($_SESSION['token']);
if (is_array($fetchedCategories)) {
    foreach ($fetchedCategories as $c) {
        if (is_array($c) && isset($c['id'])) {
            $categories[$c['id']] = $c['nom'] ?? 'Inconnu';
        }
    }
}

// Chargement des événements
$events = [];
$fetched_events = api_get_events($_SESSION['token']);
if (!empty($fetched_events) && is_array($fetched_events)) {
    $events = array_slice($fetched_events, 0, 6);
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body>
<main class="admin-layout">

<?php include 'includes/header.php'; ?>

    <?php include 'includes/sidebar.php'; ?>

    <section class="admin-content">
        <section id="dashboard" class="admin-section">
            <h1>Dashboard Global</h1>

            <div class="admin-kpis">
                <div class="admin-card">
                    <h3>Utilisateurs</h3>
                    <p><?= is_array($fetched_users) ? count($fetched_users) : '...' ?></p>
                </div>

                <div class="admin-card">
                    <h3>Professionnels</h3>
                    <p>
                        <?php
                        $pro_count = is_array($fetched_users) ? count(array_filter($fetched_users, function($u) {
                            return isset($u['id_role']) && $u['id_role'] == 4;
                        })) : '...';
                        echo $pro_count;
                        ?>
                    </p>
                </div>

                <div class="admin-card">
                    <h3>Événements</h3>
                    <p><?= is_array($fetched_events) ? count($fetched_events) : '...' ?></p>
                </div>

                <div class="admin-card">
                    <h3>Revenus mensuels</h3>
                    <p>8 420€</p>
                </div>
            </div>
        </section>

        <section id="users" class="admin-section">
            <h2>Gestion complète des utilisateurs</h2>
            <?php if (!empty($api_error)): ?>
                <p class="error"><?= htmlspecialchars($api_error) ?></p>
            <?php endif; ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Aucun utilisateur à afficher.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                <td><span class="pill pill-gray"><?= htmlspecialchars($roles[$user['id_role']] ?? 'Inconnu') ?></span></td>
                                <td><?= htmlspecialchars(ucfirst($user['statut'] ?? '')) ?></td>
                                <td><a href="users.php?search=<?= urlencode($user['email'] ?? '') ?>" class="btn-outline">Modifier</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="catalog" class="admin-section">
            <h2>Gestion des Préstations</h2>
            <?php if (!empty($api_error_prest)): ?>
                <p class="error"><?= htmlspecialchars($api_error_prest) ?></p>
            <?php endif; ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestations)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Aucune prestation à afficher.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestations as $p): ?>
                            <tr>
                                <?php 
                                    $catDisplay = 'Non catégorisé';
                                    if (!empty($p['id_categorie']) && isset($categories[$p['id_categorie']])) {
                                        $catDisplay = $categories[$p['id_categorie']];
                                    } elseif (!empty($p['categorie'])) {
                                        $catDisplay = $p['categorie'];
                                    }
                                ?>
                                <td><strong><?= htmlspecialchars($p['titre'] ?? '') ?></strong></td>
                                <td><span class="pill pill-gray"><?= htmlspecialchars($catDisplay) ?></span></td>
                                <td><?= number_format($p['prix'] ?? 0, 2) ?> €</td>
                                <td>
                                    <span class="pill <?= (!empty($p['is_active'])) ? 'pill-green' : 'pill-gray' ?>">
                                        <?= !empty($p['is_active']) ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td><a href="prestations.php?search=<?= urlencode($p['titre'] ?? '') ?>" class="btn-outline">Modifier</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="prestation_categories.php" class="btn-outline">Gérer les catégories</a>
            </div>
        </section>

        <section id="events" class="admin-section">
            <h2>Gestion des événements</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Lieu</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Aucun événement à afficher.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $e): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($prestationsMap[$e['id_prestation'] ?? ''] ?? 'Événement Inconnu') ?></strong></td>
                                <td><?= htmlspecialchars($e['lieu'] ?? '') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($e['date_debut'] ?? 'now')) ?></td>
                                <td>
                                    <span class="pill <?= ($e['statut'] == 'actif') ? 'pill-green' : 'pill-gray' ?>">
                                        <?= htmlspecialchars(ucfirst($e['statut'] ?? 'attente')) ?>
                                    </span>
                                </td>
                                <td><a href="events.php" class="btn-outline">Modifier</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

    </section>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>