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
        'description' => $_POST['description'] ?? '',
        'prix' => (float)$_POST['prix'],
        'image' => $_POST['image'] ?? '',
        'statut' => $_POST['statut'] ?? 'actif'
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

// Filtrage
$filteredList = array_filter($prestations, function($p) use ($search) {
    if (empty($search)) return true;
    $term = strtolower($search);
    return strpos(strtolower($p['titre'] ?? ''), $term) !== false 
        || strpos(strtolower($p['description'] ?? ''), $term) !== false;
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
                <button type="submit" class="btn-primary" style="height: 42px;">Filtrer</button>
                <?php if($search): ?>
                    <a href="prestations.php" class="btn-outline" style="height: 42px; display: flex; align-items: center;">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <section class="admin-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Img</th>
                        <th>Titre</th>
                        <th>Description</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredList)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px;">Aucune prestation trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredList as $p): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($p['image'])): ?>
                                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="img" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #eee; border-radius: 6px;"></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['titre'] ?? '') ?></strong></td>
                                <td><span class="muted small"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 50)) ?>...</span></td>
                                <td><?= number_format($p['prix'] ?? 0, 2) ?> €</td>
                                <td>
                                    <span class="pill <?= ($p['statut'] == 'actif') ? 'pill-green' : 'pill-gray' ?>">
                                        <?= htmlspecialchars(ucfirst($p['statut'] ?? 'actif')) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn-outline" 
                                        onclick="openEditModal(this)"
                                        data-id="<?= $p['id'] ?>"
                                        data-titre="<?= htmlspecialchars($p['titre'] ?? '') ?>"
                                        data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                        data-prix="<?= $p['prix'] ?? 0 ?>"
                                        data-image="<?= htmlspecialchars($p['image'] ?? '') ?>"
                                        data-statut="<?= $p['statut'] ?? 'actif' ?>"
                                    >Éditer</button>
                                    <a href="prestations.php?action=delete&id=<?= $p['id'] ?>" class="btn-outline" style="border-color:#dc2626; color:#dc2626; margin-left: 5px;" onclick="return confirm('Supprimer cette prestation ?')">Supprimer</a>
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
            
            <div class="form-group"><label>Titre</label><input type="text" name="titre" id="edit_titre" class="input" required></div>
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
            <div class="form-group"><label>Image (URL)</label><input type="text" name="image" id="edit_image" class="input"></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc" class="input textarea" required></textarea></div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openEditModal(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_titre').value = btn.dataset.titre;
    document.getElementById('edit_prix').value = btn.dataset.prix;
    document.getElementById('edit_desc').value = btn.dataset.desc;
    document.getElementById('edit_image').value = btn.dataset.image;
    document.getElementById('edit_statut').value = btn.dataset.statut;
    openModal('editModal');
}
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>