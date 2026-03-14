<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/functions/prestations.php';

$msg_success = "";
$msg_error = "";

// --- ACTIONS (Create, Update, Delete) ---

// 1. Suppression
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $res = api_delete_prestation($_SESSION['token'], $_GET['id']);
    if ($res['status'] === 200 || $res['status'] === 204) {
        header("Location: prestations.php?msg=deleted");
        exit();
    } else {
        $msg_error = "Erreur lors de la suppression.";
    }
}

// 3. Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = $_POST['id'];
    $payload = [
        'titre' => $_POST['titre'] ?? '',
        'id_categorie' => (int)($_POST['id_categorie'] ?? 0),
        'description' => $_POST['description'] ?? '',
        'type' => $_POST['type'] ?? 'atelier',
        'prix' => (float)$_POST['prix'],
        'image' => $_POST['image'] ?? '',
        'is_active' => (($_POST['statut'] ?? 'actif') === 'actif') ? true : false
    ];
    $res = api_update_prestation($_SESSION['token'], $id, $payload);
    if ($res['status'] === 200 || $res['status'] === 204) {
        header("Location: prestations.php?msg=updated");
        exit();
    } else {
        $msg_error = "Erreur modification : " . ($res['data']['message'] ?? 'Inconnue');
    }
}

// --- RECUPERATION DONNEES ---
$prestations = api_get_prestations($_SESSION['token']);
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['categorie'] ?? '';

// Récupération des catégories via l'API
$categories = [];
$fetchedCategories = api_get_categories($_SESSION['token']);
foreach ($fetchedCategories as $c) {
    if (is_array($c) && isset($c['id'])) {
        $categories[$c['id']] = $c['nom'] ?? 'Inconnu';
    } else {
        $catName = is_array($c) ? ($c['nom'] ?? $c['titre'] ?? $c['name'] ?? $c['categorie'] ?? '') : $c;
        if (!empty($catName)) {
            $categories[$catName] = $catName;
        }
    }
}
ksort($categories);

// Filtrage
$filteredList = array_filter($prestations, function($p) use ($search, $categoryFilter) {
    $matchesSearch = true;
    if (!empty($search)) {
        $term = strtolower($search);
        $matchesSearch = strpos(strtolower($p['titre'] ?? ''), $term) !== false 
            || strpos(strtolower($p['description'] ?? ''), $term) !== false;
    }

    $matchesCategory = true;
    if (!empty($categoryFilter)) {
        if (($p['id_categorie'] ?? '') != $categoryFilter && ($p['categorie'] ?? '') != $categoryFilter) {
            $matchesCategory = false;
        }
    }

    return $matchesSearch && $matchesCategory;
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
            <h1>Gestion des Prestations</h1>
        </div>

        <!-- Feedback Messages -->
        <?php if ($msg_error): ?>
            <p class="error" style="text-align: center;"><?= htmlspecialchars($msg_error) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'updated'): ?><p class="pill pill-green" style="display:block; text-align:center;">Prestation mise à jour.</p><?php endif; ?>
            <?php if($_GET['msg'] == 'deleted'): ?><p class="pill pill-green" style="display:block; text-align:center;">Prestation supprimée.</p><?php endif; ?>
        <?php endif; ?>

        <!-- Search -->
        <div class="card-lite" style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 1;">
                    <label for="search">Rechercher</label>
                    <input type="text" name="search" class="input" placeholder="Titre, description..." value="<?= htmlspecialchars($search) ?>" style="width: 100%;">
                </div>
                
                <div class="form-group" style="width: 200px;">
                    <label for="categorie">Catégorie</label>
                    <select name="categorie" id="categorie" class="input" style="width: 100%;">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $catId => $catName): ?>
                            <option value="<?= htmlspecialchars($catId) ?>" <?= $categoryFilter == $catId ? 'selected' : '' ?>><?= htmlspecialchars($catName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="height: 42px;">Filtrer</button>
                <?php if($search || $categoryFilter): ?>
                    <a href="prestations.php" class="btn-outline" style="height: 42px; display: flex; align-items: center;">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <section class="admin-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Description</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredList)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px;">Aucune prestation trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredList as $p): ?>
                            <tr>
                                <?php 
                                    $catDisplay = 'Non catégorisé';
                                    if (!empty($p['id_categorie']) && isset($categories[$p['id_categorie']])) {
                                        $catDisplay = $categories[$p['id_categorie']];
                                    } elseif (!empty($p['categorie'])) {
                                        $catDisplay = $p['categorie'];
                                    }
                                    $pid = $p['id'] ?? $p['id_prestation'] ?? '';
                                ?>
                                <td>
                                    <a href="#" onclick="openViewModal(event, this)"
                                        data-titre="<?= htmlspecialchars($p['titre'] ?? '') ?>"
                                        data-categorie="<?= htmlspecialchars($catDisplay) ?>"
                                        data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                        data-prix="<?= $p['prix'] ?? 0 ?>"
                                        data-type="<?= htmlspecialchars($p['type'] ?? 'atelier') ?>"
                                        data-statut="<?= !empty($p['is_active']) ? 'Actif' : 'Inactif' ?>"
                                        data-image="<?= htmlspecialchars($p['image'] ?? '') ?>"
                                        style="color: #16a34a; text-decoration: none; font-size: 1.1em; transition: color 0.2s;">
                                        <strong><?= htmlspecialchars($p['titre'] ?? '') ?></strong>
                                    </a>
                                </td>
                                <td><span class="pill pill-gray"><?= htmlspecialchars($catDisplay) ?></span></td>
                                <td><span class="muted small"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 50)) ?>...</span></td>
                                <td><?= number_format($p['prix'] ?? 0, 2) ?> €</td>
                                <td>
                                <span class="pill <?= (!empty($p['is_active'])) ? 'pill-green' : 'pill-gray' ?>">
                                    <?= !empty($p['is_active']) ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn-outline" 
                                        onclick="openEditModal(this)"
                                        data-id="<?= htmlspecialchars($pid) ?>"
                                        data-titre="<?= htmlspecialchars($p['titre'] ?? '') ?>"
                                        data-categorie="<?= htmlspecialchars($p['id_categorie'] ?? '') ?>"
                                        data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                        data-prix="<?= $p['prix'] ?? 0 ?>"
                                        data-type="<?= htmlspecialchars($p['type'] ?? 'atelier') ?>"
                                        data-statut="<?= !empty($p['is_active']) ? 'actif' : 'inactif' ?>"
                                        data-image="<?= htmlspecialchars($p['image'] ?? '') ?>"
                                    >Éditer</button>
                                    <a href="prestations.php?action=delete&id=<?= urlencode($pid) ?>" class="btn-outline" style="border-color:#dc2626; color:#dc2626; margin-left: 5px;" onclick="return confirm('Supprimer cette prestation ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section>
</main>

<!-- MODAL EDIT -->
<div id="editModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Modifier la prestation</h3>
            <button onclick="closeModal('editModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" class="form-block">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="grid-2">
                <div class="form-group"><label>Titre</label><input type="text" name="titre" id="edit_titre" class="input" required></div>
                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="id_categorie" id="edit_categorie" class="input" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php foreach ($categories as $catId => $catName): ?>
                            <option value="<?= htmlspecialchars($catId) ?>"><?= htmlspecialchars($catName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label>Prix (€)</label><input type="number" step="0.01" name="prix" id="edit_prix" class="input" required></div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut" id="edit_statut" class="input">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label>Type</label><input type="text" name="type" id="edit_type" class="input" required></div>
                <div class="form-group"><label>Image (URL)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="image" id="edit_image" class="input" style="flex: 1;">
                        <button type="button" class="btn-outline" onclick="previewEditImage()">Voir</button>
                    </div>
                </div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc" class="input textarea" required></textarea></div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VUE DÉTAILLÉE PRESTATION -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 600px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <div>
                <h3 style="margin:0; font-size: 22px;" id="view_titre"></h3>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                    <span class="pill pill-gray" id="view_categorie"></span>
                    <span class="pill pill-green" id="view_statut"></span>
                </div>
            </div>
            <button onclick="closeModal('viewModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color: #6b7280;">&times;</button>
        </div>
        
        <div id="view_image_container" style="width: 100%; height: 200px; background: #e5e7eb; border-radius: 12px; margin-bottom: 20px; display: none; overflow: hidden;">
            <img id="view_image" src="" alt="Image prestation" style="width: 100%; height: 100%; object-fit: cover;">
        </div>

        <div class="card-lite" style="margin-bottom: 15px; background: #f9fafb;">
            <div class="grid-2">
                <div>
                    <p class="muted small" style="margin: 0 0 4px 0;">Prix</p>
                    <p style="margin: 0; font-weight: 500;" id="view_prix"></p>
                </div>
                <div>
                    <p class="muted small" style="margin: 0 0 4px 0;">Type</p>
                    <p style="margin: 0; font-weight: 500;" id="view_type"></p>
                </div>
            </div>
        </div>

        <div class="card-lite" style="background: #f9fafb;">
            <p class="muted small" style="margin: 0 0 4px 0;">Description</p>
            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #374151;" id="view_desc"></p>
        </div>

        <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal('viewModal')">Fermer</button>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEditModal(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_titre').value = btn.dataset.titre;
    document.getElementById('edit_categorie').value = btn.dataset.categorie;
    document.getElementById('edit_prix').value = btn.dataset.prix;
    document.getElementById('edit_desc').value = btn.dataset.desc;
    document.getElementById('edit_type').value = btn.dataset.type;
    document.getElementById('edit_statut').value = btn.dataset.statut;
    document.getElementById('edit_image').value = btn.dataset.image;
    openModal('editModal');
}

function openViewModal(e, link) {
    e.preventDefault();
    document.getElementById('view_titre').innerText = link.dataset.titre;
    document.getElementById('view_categorie').innerText = link.dataset.categorie;
    document.getElementById('view_statut').innerText = link.dataset.statut;
    document.getElementById('view_prix').innerText = link.dataset.prix + ' €';
    document.getElementById('view_type').innerText = link.dataset.type;
    document.getElementById('view_desc').innerText = link.dataset.desc;

    const imgUrl = link.dataset.image;
    const imgContainer = document.getElementById('view_image_container');
    const imgEl = document.getElementById('view_image');

    if (imgUrl && imgUrl.trim() !== '') {
        imgEl.src = imgUrl;
        imgContainer.style.display = 'block';
    } else {
        imgContainer.style.display = 'none';
    }
    openModal('viewModal');
}

function previewEditImage() {
    const url = document.getElementById('edit_image').value;
    if (url && url.trim() !== '') {
        window.open(url, '_blank');
    } else {
        alert('Aucune URL d\'image renseignée.');
    }
}
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>