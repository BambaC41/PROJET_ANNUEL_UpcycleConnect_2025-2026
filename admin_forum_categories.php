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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Forum catégories</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    <section class="pro-card">
        <h1>📂 Forum — catégories</h1>
        <p class="muted"><a href="admin_forum.php">← Modération forum</a></p>

        <div class="pro-card" style="margin-bottom:24px; background:#f8fafc;">
            <h2><?= $edit ? '✏️ Modifier' : '➕ Nouvelle catégorie' ?></h2>
            <form method="POST" class="form-grid" style="grid-template-columns:repeat(auto-fit, minmax(200px,1fr));">
                <?php if ($edit): ?>
                    <input type="hidden" name="update_category_id" value="<?= (int)($edit['id'] ?? 0) ?>">
                <?php else: ?>
                    <input type="hidden" name="create_category" value="1">
                <?php endif; ?>
                <input class="input" name="name" placeholder="Nom" value="<?= e($edit['name'] ?? '') ?>" required>
                <input class="input" name="slug" placeholder="slug-url" value="<?= e($edit['slug'] ?? '') ?>" required>
                <input class="input" name="description" placeholder="Description" value="<?= e($edit['description'] ?? '') ?>">
                <input class="input" type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>" style="width:100px;">
                <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="is_active" value="1" <?= (!$edit || !empty($edit['is_active'])) ? 'checked' : '' ?>> Active</label>
                <div class="row-actions">
                    <button class="btn-primary" type="submit"><?= $edit ? '💾 Enregistrer' : '➕ Créer' ?></button>
                    <?php if ($edit): ?><a class="btn-outline" href="admin_forum_categories.php">❌ Annuler</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Nom</th><th>Slug</th><th>Ordre</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= (int)($c['id'] ?? 0) ?></td>
                        <td><strong><?= e($c['name'] ?? '') ?></strong></td>
                        <td><?= e($c['slug'] ?? '') ?></td>
                        <td><?= (int)($c['sort_order'] ?? 0) ?></td>
                        <td><?= !empty($c['is_active']) ? '<span class="status-badge status-ok">✅ Oui</span>' : '<span class="status-badge status-muted">❌ Non</span>' ?></td>
                        <td class="row-actions">
                            <a class="btn-outline" href="admin_forum_categories.php?edit=<?= (int)($c['id'] ?? 0) ?>">✏️ Modifier</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                <input type="hidden" name="delete_category_id" value="<?= (int)($c['id'] ?? 0) ?>">
                                <button class="btn-danger" type="submit">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>