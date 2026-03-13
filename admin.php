<?php
session_start();
require_once 'includes/functions/users.php';

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
                    <p>1 284</p>
                </div>

                <div class="admin-card">
                    <h3>Professionnels</h3>
                    <p>146</p>
                </div>

                <div class="admin-card">
                    <h3>Événements actifs</h3>
                    <p>23</p>
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

        <section id="actors" class="admin-section">
            <h2>Gestion des acteurs</h2>
            <p>Particuliers • Professionnels • Salariés</p>
            <button class="btn-primary">Voir détails acteurs</button>
        </section>

        <section id="events" class="admin-section">
            <h2>Validation des événements</h2>
            <table class="admin-table">
                <tr>
                    <th>Titre</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td>Atelier Bois</td>
                    <td>12/03</td>
                    <td>En attente</td>
                    <td>
                        <button class="btn-primary">Valider</button>
                        <button class="btn-outline">Refuser</button>
                    </td>
                </tr>
            </table>
        </section>

        <section id="catalog" class="admin-section">
            <h2>Gestion du catalogue des offres</h2>
            <button class="btn-primary">Ajouter prestation</button>
            <button class="btn-outline">Gérer catégories</button>
        </section>

        <section id="notifications" class="admin-section">
            <h2>Notifications</h2>
            <button class="btn-primary">Envoyer notification</button>
            <p>Historique des notifications envoyées aux particuliers et professionnels</p>
        </section>

        <section id="containers" class="admin-section">
            <h2>Gestion des conteneurs / box</h2>
            <div class="admin-card">
                <h3>UC-PAR-12</h3>
                <p>Statut : Opérationnel</p>
                <button class="btn-outline">Voir détails</button>
            </div>
        </section>

        <section id="documents" class="admin-section">
            <h2>Documents & Codes</h2>
            <ul>
                <li>Contrat Pro - PDF</li>
                <li>Facture Mars - PDF</li>
                <li>Code retrait : 7H2K-19Q</li>
            </ul>
        </section>

        <section id="finance" class="admin-section">
            <h2>Gestion financière</h2>

            <div class="admin-finance">
                <div>
                    <h3>Revenus</h3>
                    <p>8 420€</p>
                </div>
                <div>
                    <h3>Charges</h3>
                    <p>1 660€</p>
                </div>
            </div>
        </section>
        <section id="planning" class="admin-section">
            <h2>Accès aux plannings</h2>
            <button class="btn-outline">Planning salariés</button>
            <button class="btn-outline">Planning événements</button>
        </section>

    </section>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>