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

// A. Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id_user = $_POST['id_user'];
    $payload = [
        'pseudo' => $_POST['pseudo'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'nom' => $_POST['nom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'telephone' => $_POST['telephone'] ?? '',
        'adresse_rue' => $_POST['adresse_rue'] ?? '',
        'adresse_ville' => $_POST['adresse_ville'] ?? '',
        'adresse_code_postal' => $_POST['adresse_code_postal'] ?? '',
        'adresse_pays' => $_POST['adresse_pays'] ?? '',
        'photo_profil' => $_POST['photo_profil'] ?? '',
        'bio' => $_POST['bio'] ?? '',
        'id_role' => (int)$_POST['id_role'],
        'statut' => $_POST['statut']
    ];

    $res = api_update_user($_SESSION['token'], $id_user, $payload);

    if ($res['status'] === 200 || $res['status'] === 204) {
        $msg_success = "Utilisateur modifié avec succès.";
        // Recharger la liste pour voir les changements
        $users = api_get_users($_SESSION['token']);
    } else {
        $details = '';
        if (isset($res['data']) && !empty($res['data'])) {
            if (is_array($res['data']) && isset($res['data']['message'])) {
                $details = ' : ' . htmlspecialchars($res['data']['message']);
            } else {
                $details = ' : ' . htmlspecialchars(json_encode($res['data']));
            }
        }
        $msg_error = "Erreur lors de la modification (code " . ($res['status'] ?? 'inconnu') . ")" . $details . ".";
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
            <a href="#" class="btn-primary">Ajouter un utilisateur</a>
        </div>

        <?php if ($msg_success): ?>
            <p class="pill pill-green" style="display:block; text-align:center; margin-bottom:15px;"><?= htmlspecialchars($msg_success) ?></p>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <p class="error" style="text-align:center; margin-bottom:15px;"><?= htmlspecialchars($msg_error) ?></p>
        <?php endif; ?>
        
        <!-- Messages flash via GET -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?> <p class="pill pill-green" style="display:block; text-align:center; margin-bottom:15px;">Utilisateur supprimé.</p> <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'banned'): ?> <p class="pill pill-green" style="display:block; text-align:center; margin-bottom:15px;">Utilisateur banni.</p> <?php endif; ?>


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
                                        data-id="<?= $user['id'] ?? '' ?>"
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
                                            <a href="users.php?action=ban&id=<?= $user['id'] ?? 0 ?>" class="dropdown-item dropdown-item-warning">Bannir</a>
                                            <a href="users.php?action=delete&id=<?= $user['id'] ?? 0 ?>" class="dropdown-item dropdown-item-danger" onclick="return confirm('Confirmer la suppression de cet utilisateur ?');">Supprimer</a>
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

<!-- MODAL RESET MOT DE PASSE -->
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

        <div style="margin-top: 20px; display:flex; justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closePasswordModal()">Fermer</button>
        </div>
    </div>
</div>

<script>
let autoSaveTimeout = null;

function openEditModal(btn) {
    // Récupération des données depuis les attributs data- du bouton
    const id = btn.dataset.id;
    const pseudo = btn.dataset.pseudo;
    const prenom = btn.dataset.prenom;
    const nom = btn.dataset.nom;
    const email = btn.dataset.email;
    const role = btn.dataset.role;
    const statut = btn.dataset.statut;
    const photoProfil = btn.dataset.photo_profil;
    const bio = btn.dataset.bio;

    // Remplissage du formulaire
    document.getElementById('modal_id_user').value = id;
    document.getElementById('modal_pseudo').value = pseudo || '';
    document.getElementById('modal_prenom').value = prenom;
    document.getElementById('modal_nom').value = nom;
    document.getElementById('modal_email').value = email;
    document.getElementById('modal_role').value = role;
    document.getElementById('modal_statut').value = statut;
    document.getElementById('modal_photo_profil').value = photoProfil || '';
    document.getElementById('modal_bio').value = bio || '';

    // Affichage
    document.getElementById('editModal').classList.add('open');

    // Attache l'auto-save sur les champs du formulaire (une seule fois)
    const form = document.querySelector('#editModal form');
    if (form && !form.dataset.autosaveAttached) {
        const handler = function() {
            scheduleAutoSave();
        };
        form.addEventListener('input', handler);
        form.addEventListener('change', handler);
        form.dataset.autosaveAttached = '1';
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function openPasswordModal() {
    document.getElementById('passwordModal').classList.add('open');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('open');
}

function resetPasswordChoice(mode) {
    const userId = document.getElementById('modal_id_user').value;
    if (!userId) {
        return;
    }
    // Redirige vers une page dédiée de reset (à implémenter côté backend)
    window.location.href = 'reset_password.php?mode=' + encodeURIComponent(mode) + '&id=' + encodeURIComponent(userId);
}

function toggleDropdown(btn) {
    // Ferme tous les autres dropdowns
    document.querySelectorAll('.dropdown').forEach(d => {
        if (d !== btn.parentElement) d.classList.remove('active');
    });
    // Bascule celui-ci
    btn.parentElement.classList.toggle('active');
}

// Fermer les dropdowns si on clique en dehors des menus
window.addEventListener('click', function(event) {
    // Si le clic n'est pas à l'intérieur d'un .dropdown, on ferme tout
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(function(d) {
            d.classList.remove('active');
    });
    }

function scheduleAutoSave() {
    if (autoSaveTimeout) {
        clearTimeout(autoSaveTimeout);
    }
    autoSaveTimeout = setTimeout(runAutoSave, 500);
}

function runAutoSave() {
    const form = document.querySelector('#editModal form');
    if (!form) return;

    const formData = new FormData(form);

    fetch('users.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }).catch(function(e) {
        console.error('Erreur lors de la sauvegarde automatique', e);
    });
}
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>