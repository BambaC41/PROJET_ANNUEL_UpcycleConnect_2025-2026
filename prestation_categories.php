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
    $res = api_delete_category($_SESSION['token'], $_GET['id']);
    if ($res['status'] === 200 || $res['status'] === 204) {
        header("Location: prestation_categories.php?msg=deleted");
        exit();
    } else {
        $msg_error = "Erreur lors de la suppression de la catégorie.";
    }
}

// 2. Création et Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'nom' => $_POST['nom'] ?? '',
        'description' => $_POST['description'] ?? ''
    ];

    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $res = api_create_category($_SESSION['token'], $payload);
        if ($res['status'] === 200 || $res['status'] === 201) {
            header("Location: prestation_categories.php?msg=created");
            exit();
        } else {
            $msg_error = "Erreur lors de la création de la catégorie.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $res = api_update_category($_SESSION['token'], $id, $payload);
        if ($res['status'] === 200 || $res['status'] === 204) {
            header("Location: prestation_categories.php?msg=updated");
            exit();
        } else {
            $msg_error = "Erreur lors de la modification de la catégorie.";
        }
    }
}

// --- RECUPERATION DONNEES ---
$categories = api_get_categories($_SESSION['token']);
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
            <h1>Gestion des Prestation Categories</h1>
            <button type="button" class="btn-primary" onclick="openCreateModal()">Ajouter une catégorie</button>
        </div>

        <!-- Feedback Messages -->
        <?php if ($msg_error): ?><p class="error" style="text-align: center;"><?= htmlspecialchars($msg_error) ?></p><?php endif; ?>
        <?php if (isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'created'): ?><p class="pill pill-green" style="display:block; text-align:center;">Catégorie de prestation créée.</p><?php endif; ?>
            <?php if($_GET['msg'] == 'updated'): ?><p class="pill pill-green" style="display:block; text-align:center;">Catégorie de prestation mise à jour.</p><?php endif; ?>
            <?php if($_GET['msg'] == 'deleted'): ?><p class="pill pill-green" style="display:block; text-align:center;">Catégorie de prestation supprimée.</p><?php endif; ?>
        <?php endif; ?>

        <!-- Table -->
        <section class="admin-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Nom de la catégorie</th>
                        <th>Description</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px;">Aucune catégorie trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><span class="muted small">#<?= htmlspecialchars($c['id'] ?? '') ?></span></td>
                                <td>
                                    <a href="#" onclick="openViewModal(event, this)"
                                        data-id="<?= htmlspecialchars($c['id'] ?? '') ?>"
                                        data-nom="<?= htmlspecialchars($c['nom'] ?? '') ?>"
                                        data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>"
                                        style="color: #16a34a; text-decoration: none; font-size: 1.1em; transition: color 0.2s;">
                                        <strong><?= htmlspecialchars($c['nom'] ?? '') ?></strong>
                                    </a>
                                </td>
                                <td><span class="muted small"><?= htmlspecialchars($c['description'] ?? '') ?></span></td>
                                <td style="text-align: right;">
                                    <button class="btn-outline" 
                                        onclick="openEditModal(this)"
                                        data-id="<?= htmlspecialchars($c['id'] ?? '') ?>"
                                        data-nom="<?= htmlspecialchars($c['nom'] ?? '') ?>"
                                        data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>"
                                    >Éditer</button>
                                    <a href="prestation_categories.php?action=delete&id=<?= urlencode($c['id'] ?? '') ?>" class="btn-outline" style="border-color:#dc2626; color:#dc2626; margin-left: 5px;" onclick="return confirm('Attention: la suppression d\'une catégorie peut affecter les prestations liées. Continuer ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section>
</main>

<!-- MODAL CREATE / EDIT -->
<div id="categoryModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTitle">Catégorie de Prestation</h3>
            <button onclick="closeModal('categoryModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" class="form-block">
            <input type="hidden" name="action" id="modal_action" value="create">
            <input type="hidden" name="id" id="modal_id">
            
            <div class="form-group"><label>Nom de la catégorie</label><input type="text" name="nom" id="modal_nom" class="input" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="modal_desc" class="input textarea"></textarea></div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeModal('categoryModal')">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VUE DÉTAILLÉE -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <div>
                <h3 style="margin:0; font-size: 22px;" id="view_nom"></h3>
                <div style="margin-top: 4px;">
                    <span class="pill pill-gray" id="view_id_badge"></span>
                </div>
            </div>
            <button onclick="closeModal('viewModal')" style="background:none; border:none; font-size:24px; cursor:pointer; color: #6b7280;">&times;</button>
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

<script src="scripts/categories.js" defer></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
