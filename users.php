<?php
session_start();
require_once 'includes/functions/users.php';

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}

// Inclusion de la fonction d'update
require_once 'includes/functions/user_actions.php';

// 1. Récupération des utilisateurs via l'API
$users = api_get_users($_SESSION['token']);
if (!is_array($users)) {
    $users = [];
    $error_msg = "Erreur lors du chargement des utilisateurs.";
}

// 2. Logique de filtrage (Recherche & Rôle)
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

$rolesMap = [ 1 => 'ADMIN', 2 => 'STAFF', 3 => 'USER', 4 => 'PRO' ];

// 3. Logique d'édition (Formulaire & Traitement)
$currentUser = [];
$msg_success = "";
$msg_error = "";

// --- GESTION DES ACTIONS (BAN / DELETE) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $act = $_GET['action'];
    $uid = $_GET['id'];

    // 1. Suppression
    if ($act === 'delete') {
        api_delete_user($_SESSION['token'], $uid);
        // Rechargement propre pour actualiser la liste
        header("Location: users.php?msg=deleted");
        exit();
    }

    // 2. Bannissement (Modification du statut)
    if ($act === 'ban') {
        // On récupère l'utilisateur actuel pour garder ses infos
        $target = null;
        foreach ($users as $u) { if ($u['id'] == $uid) { $target = $u; break; } }

        if ($target) {
            $payload = [
                'prenom' => $target['prenom'], 'nom' => $target['nom'], 'email' => $target['email'],
                'id_role' => (int)$target['id_role'], 'statut' => 'banni'
            ];
            api_update_user($_SESSION['token'], $uid, $payload);
            header("Location: users.php?msg=banned");
            exit();
        }
    }
}
// -----------------------------------------

// A. Création d'un nouvel utilisateur (ADMIN / STAFF uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $role_id = (int)($_POST['role_id'] ?? 0);
    // Sécurité : ne permettre que ADMIN (1) et STAFF (2)
    if (in_array($role_id, [1, 2], true)) {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        if ($password !== '' && $password === $password_confirm) {
            $payload = [
                'email' => $_POST['email'] ?? '',
                'password' => $password,
                'pseudo' => $_POST['pseudo'] ?? '',
                'prenom' => $_POST['prenom'] ?? '',
                'nom' => $_POST['nom'] ?? '',
                'photo_profil' => $_POST['photo_profil'] ?? '',
                'bio' => $_POST['bio'] ?? '',
                'role_id' => $role_id,
            ];
            $resCreate = api_admin_create_user($_SESSION['token'], $payload);
            if ($resCreate['status'] === 200 || $resCreate['status'] === 201) {
                header('Location: users.php');
                exit();
            }
        }
    }
}

// B. Traitement de la soumission du formulaire d'édition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id_user = $_POST['id_user'];
    $payload = [
        // On respecte la structure exacte de la doc PUT /users/{id}
        'email' => $_POST['email'] ?? '',
        'pseudo' => $_POST['pseudo'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'nom' => $_POST['nom'] ?? '',
        'telephone' => $_POST['telephone'] ?? '',
        'adresse_rue' => $_POST['adresse_rue'] ?? '',
        'adresse_ville' => $_POST['adresse_ville'] ?? '',
        'adresse_code_postal' => $_POST['adresse_code_postal'] ?? '',
        'adresse_pays' => $_POST['adresse_pays'] ?? '',
        'photo_profil' => $_POST['photo_profil'] ?? '',
        'bio' => $_POST['bio'] ?? '',
        'statut' => $_POST['statut'],
        'id_role' => (int)$_POST['id_role']
    ];

    $res = api_update_user($_SESSION['token'], $id_user, $payload);

    // UX demandée : pas de messages visibles
    if ($res['status'] === 200 || $res['status'] === 204) {
        $users = api_get_users($_SESSION['token']);
    }
}

$filteredUsers = array_filter($users, function($u) use ($search, $roleFilter) {
    // Filtre par texte (Nom, Prénom, Email)
    $matchesSearch = true;
    if (!empty($search)) {
        $fullName = strtolower(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
        $email = strtolower($u['email'] ?? '');
        $term = strtolower($search);
        
        if (strpos($fullName, $term) === false && strpos($email, $term) === false) {
            $matchesSearch = false;
        }
    }

    // Filtre par rôle
    $matchesRole = true;
    if (!empty($roleFilter) && isset($u['id_role'])) {
        if ($u['id_role'] != $roleFilter) {
            $matchesRole = false;
        }
    }

    return $matchesSearch && $matchesRole;
});
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body>
<main class="admin-layout">

    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <section class="admin-content">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Gestion des Utilisateurs</h1>
            <button type="button" class="btn-primary" onclick="openCreateUserModal()">Ajouter un utilisateur</button>
        </div>

        <!-- Messages supprimés (UX clean). -->


        <!-- BARRE DE RECHERCHE ET FILTRES -->
        <div class="card-lite" style="margin-bottom: 20px;">
            <form method="GET" action="" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 1;">
                    <label for="search" style="font-size: 14px; font-weight: 600;">Rechercher</label>
                    <input type="text" name="search" id="search" class="input" placeholder="Nom, email..." value="<?= htmlspecialchars($search) ?>" style="width: 100%;">
                </div>
                
                <div class="form-group" style="width: 200px;">
                    <label for="role" style="font-size: 14px; font-weight: 600;">Rôle</label>
                    <select name="role" id="role" class="input" style="width: 100%;">
                        <option value="">Tous les rôles</option>
                        <?php foreach ($rolesMap as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $roleFilter == $id ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="height: 42px;">Filtrer</button>
                <?php if($search || $roleFilter): ?>
                    <a href="users.php" class="btn-outline" style="height: 42px; display: flex; align-items: center;">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- TABLEAU DES RESULTATS -->
        <section class="admin-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Identité</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredUsers)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Aucun utilisateur trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredUsers as $user): ?>
                            <tr>
                                <?php
                                    // L'API peut renvoyer des noms de clé différents pour l'identifiant
                                    $uid = $user['id']
                                        ?? $user['id_user']
                                        ?? $user['user_id']
                                        ?? $user['id_utilisateur']
                                        ?? $user['utilisateur_id']
                                        ?? '';
                                ?>
                                <td>
                                    <strong><?= htmlspecialchars($user['pseudo'] ?? '') ?></strong><br>
                                    <span class="muted" style="font-size: 0.9em;"><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></span>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'pill-gray';
                                        if (($user['id_role'] ?? 0) == 1) $badgeClass = 'pill-green'; // Admin en vert
                                    ?>
                                    <span class="pill <?= $badgeClass ?>"><?= htmlspecialchars($rolesMap[$user['id_role']] ?? 'Inconnu') ?></span>
                                </td>
                                <td><?= htmlspecialchars(ucfirst($user['statut'] ?? 'actif')) ?></td>
                                <td style="text-align: right;">
                                    <button class="btn-outline" 
                                        onclick="openEditModal(this)"
                                        data-id="<?= htmlspecialchars((string)$uid) ?>"
                                        data-pseudo="<?= htmlspecialchars($user['pseudo'] ?? '') ?>"
                                        data-prenom="<?= htmlspecialchars($user['prenom'] ?? '') ?>"
                                        data-nom="<?= htmlspecialchars($user['nom'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                        data-telephone="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                                        data-adresse_rue="<?= htmlspecialchars($user['adresse_rue'] ?? '') ?>"
                                        data-adresse_ville="<?= htmlspecialchars($user['adresse_ville'] ?? '') ?>"
                                        data-adresse_code_postal="<?= htmlspecialchars($user['adresse_code_postal'] ?? '') ?>"
                                        data-adresse_pays="<?= htmlspecialchars($user['adresse_pays'] ?? '') ?>"
                                        data-role="<?= $user['id_role'] ?? 1 ?>"
                                        data-statut="<?= $user['statut'] ?? 'actif' ?>"
                                        data-photo_profil="<?= htmlspecialchars($user['photo_profil'] ?? '') ?>"
                                        data-bio="<?= htmlspecialchars($user['bio'] ?? '') ?>">Éditer</button>
                                    
                                    <div class="dropdown" style="display: inline-block; margin-left: 5px;">
                                        <button class="btn-outline" onclick="toggleDropdown(this)">Options ▾</button>
                                        <div class="dropdown-content" style="min-width: 160px; right: 0;">
                                            <a href="users.php?action=ban&id=<?= urlencode((string)$uid) ?>" class="dropdown-item dropdown-item-warning">Bannir</a>
                                            <a href="users.php?action=delete&id=<?= urlencode((string)$uid) ?>" class="dropdown-item dropdown-item-danger" onclick="return confirm('Confirmer la suppression de cet utilisateur ?');">Supprimer</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="muted small" style="margin-top: 10px;">Affichage de <?= count($filteredUsers) ?> utilisateur(s)</p>
        </section>

    </section>
</main>

<!-- MODAL EDITION -->
<div id="editModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Modifier l'utilisateur</h3>
            <button onclick="closeEditModal()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST" action="users.php" class="form-block">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id_user" id="modal_id_user">

            <div class="form-group">
                <label>Pseudo</label>
                <input type="text" name="pseudo" id="modal_pseudo" class="input" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" id="modal_prenom" class="input" required>
                </div>
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" id="modal_nom" class="input" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="modal_email" class="input" required>
            </div>

            <div class="form-group">
                <label>Photo de profil (URL)</label>
                <input type="text" name="photo_profil" id="modal_photo_profil" class="input">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" id="modal_bio" class="input textarea"></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="id_role" id="modal_role" class="input">
                        <?php foreach ($rolesMap as $id => $name): ?>
                            <option value="<?= $id ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut" id="modal_statut" class="input">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                        <option value="banni">Banni</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 10px;">
                <button type="button" class="btn-outline" onclick="openPasswordModal()">Réinitialiser le mot de passe</button>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CRÉATION UTILISATEUR (ADMIN / STAFF) -->
<div id="createUserModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Ajouter un utilisateur</h3>
            <button onclick="closeCreateUserModal()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <form method="POST" action="users.php" class="form-block">
            <input type="hidden" name="action" value="create_user">

            <div class="form-group">
                <label>Pseudo</label>
                <input type="text" name="pseudo" class="input" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" class="input" required>
                </div>
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" class="input" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="input" required>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" class="input" required>
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="password_confirm" class="input" required>
                </div>
            </div>

            <div class="form-group">
                <label>Photo de profil (URL)</label>
                <input type="text" name="photo_profil" class="input">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" class="input textarea"></textarea>
            </div>

            <div class="form-group">
                <label>Rôle (seulement ADMIN ou STAFF)</label>
                <select name="role_id" class="input" required>
                    <option value="">Choisir un rôle</option>
                    <option value="1">ADMIN</option>
                    <option value="2">STAFF</option>
                </select>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-secondary" onclick="closeCreateUserModal()">Annuler</button>
                <button type="submit" class="btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<div id="passwordModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Réinitialiser le mot de passe</h3>
            <button onclick="closePasswordModal()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <p class="muted" style="margin-bottom: 16px;">
            Choisissez la méthode de réinitialisation pour cet utilisateur.
        </p>

        <div style="display:flex; flex-direction:column; gap:10px;">
            <button type="button" class="btn-primary" onclick="resetPasswordChoice('email')">
                Réinitialiser par e-mail
            </button>
            <button type="button" class="btn-outline" onclick="resetPasswordChoice('form')">
                Réinitialiser via un formulaire
            </button>
        </div>

        <!-- Zone de formulaire pour reset direct -->
        <div id="password-form" style="margin-top: 20px; display:none;">
            <form method="POST" action="reset_password.php" class="form-block">
                <input type="hidden" name="mode" value="form">
                <input type="hidden" name="id_user" id="password_form_id_user">

                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" class="input" required>
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="password_confirm" class="input" required>
                </div>

                <div style="margin-top: 15px; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-secondary" onclick="hidePasswordForm()">Annuler</button>
                    <button type="submit" class="btn-primary">Valider</button>
                </div>
            </form>
        </div>

        <div style="margin-top: 20px; display:flex; justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closePasswordModal()">Fermer</button>
        </div>
    </div>
</div>

<script src="scripts/users.js" defer></script>

<?php include 'includes/footer.php'; ?>
</body>
</html>