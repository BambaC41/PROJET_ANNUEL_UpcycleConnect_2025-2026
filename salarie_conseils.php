<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_conseil'])) {
        $titre = trim((string)($_POST['titre'] ?? ''));
        $contenu = trim((string)($_POST['contenu'] ?? ''));
        $categorie = trim((string)($_POST['categorie'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));

        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file((string)$_FILES['image']['tmp_name'])) {
            $mime = @mime_content_type((string)$_FILES['image']['tmp_name']) ?: '';
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $size = (int)($_FILES['image']['size'] ?? 0);
            if (in_array($mime, $allowed, true) && $size > 0 && $size <= 3 * 1024 * 1024) {
                $dir = __DIR__ . '/uploads/conseils';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                $fn = 'c_' . $userId . '_' . date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $fn;
                if (@move_uploaded_file((string)$_FILES['image']['tmp_name'], $dest)) {
                    $imageUrl = 'uploads/conseils/' . $fn;
                }
            }
        }

        $payload = [
            'titre' => $titre,
            'contenu' => $contenu,
            'categorie' => $categorie,
            'image_url' => $imageUrl,
            'is_active' => false,
        ];
        $res = api_create_conseil($payload);
        if (($res['status'] ?? 0) === 201) {
            $nid = (int)($res['data']['id_conseil'] ?? 0);
            if ($nid > 0) {
                db_safe_exec(function (PDO $pdo) use ($nid, $userId) {
                    $st = $pdo->prepare('UPDATE conseil SET id_auteur = ? WHERE id_conseil = ? AND (id_auteur IS NULL OR id_auteur = ?)');
                    $st->execute([$userId, $nid, $userId]);
                });
            }
            notif_create($userId, 'conseil', 'Conseil créé', 'Votre conseil/news est en brouillon en attente de validation.');
            notif_notify_roles([1], 'conseil', 'Conseil à valider', 'Un salarié a soumis un conseil/news en brouillon.');
            toast_redirect('salarie_conseils.php', 'success', 'Conseil/news créé en brouillon (validation administrateur).');
        }

        $localId = (int)db_safe_exec(function (PDO $pdo) use ($titre, $contenu, $categorie, $imageUrl, $userId) {
            $st = $pdo->prepare('INSERT INTO conseil (titre, contenu, categorie, image_url, is_active, id_auteur) VALUES (?, ?, ?, ?, 0, ?)');
            $st->execute([$titre, $contenu, $categorie, $imageUrl, $userId]);
            return (int)$pdo->lastInsertId();
        }, 0);

        if ($localId > 0) {
            notif_create($userId, 'conseil', 'Conseil créé (local)', 'Conseil enregistré en brouillon (mode hors API).');
            notif_notify_roles([1], 'conseil', 'Conseil à valider', 'Un salarié a créé un conseil en brouillon (#local ' . $localId . ').');
            toast_redirect('salarie_conseils.php', 'success', 'Conseil créé en brouillon (enregistrement local).');
        }

        toast_redirect('salarie_conseils.php', 'error', 'Création impossible : ' . (string)($res['error'] ?? 'API et base locale indisponibles.'));
    }

    if (isset($_POST['update_conseil_id'])) {
        $id = (int)$_POST['update_conseil_id'];
        $payload = [
            'titre' => trim((string)($_POST['titre'] ?? '')),
            'contenu' => trim((string)($_POST['contenu'] ?? '')),
            'categorie' => trim((string)($_POST['categorie'] ?? '')),
            'image_url' => trim((string)($_POST['image_url'] ?? '')),
            'is_active' => false,
        ];
        $res = api_update_conseil($id, $payload);
        if (($res['status'] ?? 0) === 200) {
            toast_redirect('salarie_conseils.php', 'success', 'Conseil/news mis à jour (reste en attente).');
        }
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId, $payload) {
            $st = $pdo->prepare('UPDATE conseil SET titre = ?, contenu = ?, categorie = ?, image_url = ?, is_active = 0 WHERE id_conseil = ? AND id_auteur = ?');
            return $st->execute([$payload['titre'], $payload['contenu'], $payload['categorie'], $payload['image_url'], $id, $userId]);
        }, false);
        if ($ok) {
            toast_redirect('salarie_conseils.php', 'success', 'Conseil mis à jour (local).');
        }
        toast_redirect('salarie_conseils.php', 'error', 'Mise à jour impossible: ' . (string)($res['error'] ?? ''));
    }

    if (isset($_POST['delete_conseil_id'])) {
        $id = (int)$_POST['delete_conseil_id'];
        $delRes = api_delete_conseil($id);
        if (($delRes['status'] ?? 0) === 200) {
            toast_redirect('salarie_conseils.php', 'success', 'Brouillon supprimé.');
        }
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId) {
            $st = $pdo->prepare('DELETE FROM conseil WHERE id_conseil = ? AND id_auteur = ? AND is_active = 0');
            return $st->execute([$id, $userId]);
        }, false);
        if ($ok) {
            toast_redirect('salarie_conseils.php', 'success', 'Brouillon supprimé.');
        }
        toast_redirect('salarie_conseils.php', 'error', 'Suppression impossible (conseil introuvable ou déjà publié).');
    }
}

$apiMine = [];
$resMine = api_get_my_conseils();
if (($resMine['status'] ?? 0) === 200) {
    $apiMine = is_array($resMine['data']) ? $resMine['data'] : [];
}
$mine = salarie_conseils_merge_local($apiMine, $userId);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));

$filtered = [];
foreach ($mine as $c) {
    $isActive = !empty($c['is_active']);
    if ($status === 'active' && !$isActive) {
        continue;
    }
    if ($status === 'draft' && $isActive) {
        continue;
    }
    $hay = strtolower(($c['titre'] ?? '') . ' ' . ($c['categorie'] ?? '') . ' ' . ($c['contenu'] ?? ''));
    if ($q !== '' && !str_contains($hay, strtolower($q))) {
        continue;
    }
    $filtered[] = $c;
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    foreach ($mine as $c) {
        if ((int)($c['id_conseil'] ?? 0) === $editId) {
            if (empty($c['is_active'])) {
                $edit = $c;
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Salarie - Conseils & news</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/public.css">
    <link rel="stylesheet" href="styles/employee.css">
    <style>
        textarea.input{ min-height: 130px; }
        .badge{ display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-weight:700;font-size:12px; }
        .badge-ok{ background:rgba(22,163,74,.12); color:#166534; }
        .badge-wait{ background:rgba(234,179,8,.18); color:#92400e; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="container" style="max-width:1100px;margin:20px auto;padding:0 16px;">
    <section class="hero-block soft" style="margin-top:18px;">
        <h1>📰 Conseils & news</h1>
        <p class="muted">Rédigez un brouillon, ajoutez une image (fichier ou URL). Validation par l'administrateur.</p>
    </section>

    <section class="emp-card" style="margin-top:14px;">
        <h3><?= $edit ? '✏️ Modifier un conseil/news' : '➕ Créer un conseil/news' ?></h3>
        <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <?php if ($edit): ?>
                <input type="hidden" name="update_conseil_id" value="<?= e((int)($edit['id_conseil'] ?? 0)) ?>">
            <?php else: ?>
                <input type="hidden" name="create_conseil" value="1">
            <?php endif; ?>

            <input class="input" name="titre" placeholder="Titre" value="<?= e($edit['titre'] ?? '') ?>" required style="grid-column:1 / -1;">
            <select class="input" name="categorie" required>
                <option value="">— Catégorie —</option>
                <?php foreach (conseil_categories_list() as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= ($edit['categorie'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" name="image_url" placeholder="URL image (optionnel si fichier ci-dessous)" value="<?= e($edit['image_url'] ?? '') ?>">
            <label class="input" style="display:flex;flex-direction:column;gap:4px;grid-column:1 / -1;">
                <span class="muted">Image locale (jpg, png, webp — max 3 Mo)</span>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            </label>
            <textarea class="input" name="contenu" placeholder="Contenu" required style="grid-column:1 / -1;"><?= e($edit['contenu'] ?? '') ?></textarea>

            <div style="grid-column:1 / -1;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button class="btn-primary" type="submit"><?= $edit ? 'Enregistrer' : 'Créer' ?></button>
                <?php if ($edit): ?>
                    <a class="btn-outline" href="salarie_conseils.php">Annuler</a>
                <?php endif; ?>
                <span class="muted">Brouillon tant que non validé.</span>
            </div>
        </form>
    </section>

    <section class="emp-card" style="margin-top:14px;">
        <h3>📋 Mes conseils & news</h3>
        <form method="GET" class="row-actions" style="flex-wrap:wrap;">
            <input class="input" name="q" value="<?= e($q) ?>" placeholder="Recherche">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Publiés</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>

        <table class="table" style="margin-top:10px;">
            <thead>
            <tr>
                <th>Titre</th>
                <th>Catégorie</th>
                <th>Statut</th>
                <th>Créé le</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($filtered as $c): ?>
                <?php $isActive = !empty($c['is_active']); ?>
                <tr>
                    <td><strong><?= e($c['titre'] ?? '') ?></strong></td>
                    <td><?= e($c['categorie'] ?? '') ?></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="badge badge-ok">✅ Publié</span>
                        <?php else: ?>
                            <span class="badge badge-wait">🕒 Brouillon</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(formatDateFr($c['created_at'] ?? '')) ?></td>
                    <td>
                        <?php if (!$isActive): ?>
                            <a class="btn-outline" href="salarie_conseils.php?edit=<?= e((int)($c['id_conseil'] ?? 0)) ?>">Modifier</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce brouillon ?');">
                                <input type="hidden" name="delete_conseil_id" value="<?= (int)($c['id_conseil'] ?? 0) ?>">
                                <button class="btn-outline" type="submit">Supprimer</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Publié</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($filtered) === 0): ?>
                <tr><td colspan="5" class="muted">Aucun contenu pour ce filtre.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
