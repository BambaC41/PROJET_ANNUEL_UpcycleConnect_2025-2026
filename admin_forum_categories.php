<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_category'])) {
        $res = api_forum_post('categories', [
            'name' => trim((string)($_POST['name'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']),
        ]);
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 201)
            ? ['type' => 'success', 'message' => 'Catégorie créée.']
            : ['type' => 'error', 'message' => forum_api_error_message($res)];
    }
    if (isset($_POST['update_category_id'])) {
        $id = (int)$_POST['update_category_id'];
        $res = api_forum_put('categories/' . $id, [
            'name' => trim((string)($_POST['name'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']),
        ]);
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
            ? ['type' => 'success', 'message' => 'Catégorie mise à jour.']
            : ['type' => 'error', 'message' => forum_api_error_message($res)];
    }
    if (isset($_POST['delete_category_id'])) {
        $id = (int)$_POST['delete_category_id'];
        $res = api_forum_delete('categories/' . $id);
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200)
            ? ['type' => 'success', 'message' => 'Catégorie supprimée.']
            : ['type' => 'error', 'message' => forum_api_error_message($res)];
    }
    header('Location: admin_forum_categories.php');
    exit;
}

$res = api_forum_get('categories');
$categories = (($res['status'] ?? 0) === 200 && is_array($res['data'])) ? $res['data'] : [];
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
foreach ($categories as $c) {
    if ((int)($c['id'] ?? 0) === $editId) {
        $edit = $c;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
    <?php include 'includes/flash_toast.php'; ?>
    <section class="admin-section">
        <h1>Forum — catégories</h1>
        <p class="muted"><a href="admin_forum.php">← Modération forum</a></p>

        <div class="admin-card" style="padding:16px;margin:16px 0;border:1px solid #e2e8f0;border-radius:8px;">
            <h2><?= $edit ? 'Modifier' : 'Nouvelle catégorie' ?></h2>
            <form method="POST" class="row-actions" style="flex-wrap:wrap;">
                <?php if ($edit): ?>
                    <input type="hidden" name="update_category_id" value="<?= (int)($edit['id'] ?? 0) ?>">
                <?php else: ?>
                    <input type="hidden" name="create_category" value="1">
                <?php endif; ?>
                <input class="input" name="name" placeholder="Nom" value="<?= e($edit['name'] ?? '') ?>" required>
                <input class="input" name="slug" placeholder="slug-url" value="<?= e($edit['slug'] ?? '') ?>" required>
                <input class="input" name="description" placeholder="Description" value="<?= e($edit['description'] ?? '') ?>">
                <input class="input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>" style="width:100px;">
                <label><input type="checkbox" name="is_active" value="1" <?= (!$edit || !empty($edit['is_active'])) ? 'checked' : '' ?>> Active</label>
                <button class="btn-primary" type="submit"><?= $edit ? 'Enregistrer' : 'Créer' ?></button>
                <?php if ($edit): ?><a class="btn-outline" href="admin_forum_categories.php">Annuler</a><?php endif; ?>
            </form>
        </div>

        <table class="admin-table">
            <thead><tr><th>ID</th><th>Nom</th><th>Slug</th><th>Ordre</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= (int)($c['id'] ?? 0) ?></td>
                    <td><?= e($c['name'] ?? '') ?></td>
                    <td><?= e($c['slug'] ?? '') ?></td>
                    <td><?= (int)($c['sort_order'] ?? 0) ?></td>
                    <td><?= !empty($c['is_active']) ? 'Oui' : 'Non' ?></td>
                    <td>
                        <a class="btn-outline" href="admin_forum_categories.php?edit=<?= (int)($c['id'] ?? 0) ?>">Modifier</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ?');">
                            <input type="hidden" name="delete_category_id" value="<?= (int)($c['id'] ?? 0) ?>">
                            <button class="btn-danger" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
</main>
</body>
</html>
