<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$author = (int)($_GET['author'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$viewId = (int)($_GET['view'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_conseil_id'])) {
        $id = (int)$_POST['toggle_conseil_id'];
        $isActive = (int)($_POST['is_active'] ?? 0) === 1;
        $payload = [
            'titre' => trim((string)($_POST['titre'] ?? '')),
            'contenu' => trim((string)($_POST['contenu'] ?? '')),
            'categorie' => trim((string)($_POST['categorie'] ?? '')),
            'image_url' => trim((string)($_POST['image_url'] ?? '')),
            'is_active' => $isActive,
        ];
        $res = api_update_conseil($id, $payload);
        if (($res['status'] ?? 0) === 200) {
            $authorId = (int)($_POST['id_auteur'] ?? 0);
            if ($authorId > 0) {
                $msg = $isActive ? 'Votre conseil/news a été publié par l\'équipe.' : 'Votre conseil/news a été repassé en brouillon.';
                notif_create($authorId, 'conseil', $isActive ? 'Conseil publié' : 'Conseil en brouillon', $msg);
            }
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => $isActive ? 'Conseil publié.' : 'Conseil repassé en brouillon.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Échec de la mise à jour.'];
        }
        header('Location: admin_conseils.php?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page])));
        exit;
    }

    if (isset($_POST['delete_conseil_id'])) {
        $id = (int)$_POST['delete_conseil_id'];
        $res = api_delete_conseil($id);
        $_SESSION['flash_toast'] = (($res['status'] ?? 0) === 200) ? ['type' => 'success', 'message' => 'Conseil supprimé.'] : ['type' => 'error', 'message' => 'Suppression impossible.'];
        header('Location: admin_conseils.php?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page])));
        exit;
    }
}

$apiStatus = $status;
if ($status === 'active') $apiStatus = 'published';

$res = api_get_conseils_admin(array_filter(['q' => $q, 'status' => $apiStatus !== 'all' ? $apiStatus : '', 'author' => $author > 0 ? $author : '', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page, 'per_page' => $perPage]));
$all = conseils_admin_items_from_response($res);
$total = conseils_admin_total_from_response($res);
$totalPages = max(1, (int)ceil($total / $perPage));

$authors = [];
$authorRows = (array)db_safe_exec(function (PDO $pdo) {
    $st = $pdo->query('SELECT DISTINCT c.id_auteur, COALESCE(u.pseudo, CONCAT("User #", c.id_auteur)) AS pseudo FROM conseil c LEFT JOIN utilisateur u ON u.id_user = c.id_auteur WHERE c.id_auteur IS NOT NULL ORDER BY pseudo');
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}, []);
foreach ($authorRows as $row) {
    $aid = (int)($row['id_auteur'] ?? 0);
    if ($aid > 0) $authors[$aid] = trim((string)($row['pseudo'] ?? ''));
}
foreach ($all as $c) {
    $aid = (int)($c['id_auteur'] ?? 0);
    if ($aid > 0 && !isset($authors[$aid])) $authors[$aid] = trim((string)($c['auteur_pseudo'] ?? '')) ?: ('Utilisateur #' . $aid);
}
asort($authors);

$detail = null;
if ($viewId > 0) {
    foreach ($all as $c) {
        if ((int)($c['id_conseil'] ?? 0) === $viewId) { $detail = $c; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Conseils & News</title>
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
        <h1>💡 Conseils & News</h1>
        <p class="muted">Validez les contenus soumis par les salariés : publiez, repassez en brouillon ou supprimez.</p>

        <form method="GET" class="row-actions" style="flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            <input class="input" name="q" value="<?= e($q) ?>" placeholder="Recherche titre, contenu…">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Publiés</option>
            </select>
            <select class="input" name="author">
                <option value="0">Tous les auteurs</option>
                <?php foreach ($authors as $aid => $label): ?>
                    <option value="<?= (int)$aid ?>" <?= $author === (int)$aid ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>" title="Date début">
            <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>" title="Date fin">
            <button class="btn-outline" type="submit">Filtrer</button>
            <a class="btn-outline" href="admin_conseils.php">Réinitialiser</a>
        </form>

        <?php if ($detail): ?>
            <div class="pro-card" style="margin-bottom:16px;background:#f8fafc;">
                <h2>📄 Détail #<?= (int)($detail['id_conseil'] ?? 0) ?></h2>
                <p><strong>Titre :</strong> <?= e($detail['titre'] ?? '') ?></p>
                <p><strong>Catégorie :</strong> <?= e($detail['categorie'] ?? '') ?></p>
                <p><strong>Auteur :</strong> <?= e($detail['auteur_pseudo'] ?? ('#' . (int)($detail['id_auteur'] ?? 0))) ?></p>
                <p><strong>Statut :</strong> <span class="status-badge <?= !empty($detail['is_active']) ? 'status-ok' : 'status-warn' ?>"><?= !empty($detail['is_active']) ? 'Publié' : 'Brouillon' ?></span></p>
                <p><strong>Créé le :</strong> <?= e(formatDateFr($detail['created_at'] ?? '')) ?></p>
                <?php if (!empty($detail['image_url'])): ?>
                    <p><strong>Image :</strong> <a href="<?= e($detail['image_url']) ?>" target="_blank" rel="noopener">Voir</a></p>
                <?php endif; ?>
                <div style="margin-top:12px;padding:12px;background:white;border-radius:8px;"><?= nl2br(e($detail['contenu'] ?? '')) ?></div>
                <a class="btn-outline" href="admin_conseils.php?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'page' => $page]))) ?>" style="margin-top:12px;">← Fermer le détail</a>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Titre</th><th>Catégorie</th><th>Auteur</th><th>Statut</th><th>Créé le</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($all)): ?>
                    <tr><td colspan="7" style="text-align:center;">Aucun conseil ne correspond aux filtres.</td></tr>
                <?php else: foreach ($all as $c): ?>
                    <?php $isActive = !empty($c['is_active']); ?>
                    <tr>
                        <td><?= (int)($c['id_conseil'] ?? 0) ?></td>
                        <td><strong><?= e($c['titre'] ?? '') ?></strong></td>
                        <td><?= e($c['categorie'] ?? '') ?></td>
                        <td><?= e(conseil_author_label(isset($c['id_auteur']) ? (int)$c['id_auteur'] : null, [(int)($c['id_auteur'] ?? 0) => (string)($c['auteur_pseudo'] ?? '')])) ?></td>
                        <td><span class="status-badge <?= $isActive ? 'status-ok' : 'status-warn' ?>"><?= $isActive ? 'Publié' : 'Brouillon' ?></span></td>
                        <td><?= e(formatDateFr($c['created_at'] ?? '')) ?></td>
                        <td class="row-actions">
                            <a class="btn-outline" href="admin_conseils.php?<?= e(http_build_query(array_merge(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'page' => $page]), ['view' => (int)($c['id_conseil'] ?? 0)]))) ?>">Détails</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="toggle_conseil_id" value="<?= (int)($c['id_conseil'] ?? 0) ?>">
                                <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                                <input type="hidden" name="titre" value="<?= e($c['titre'] ?? '') ?>">
                                <input type="hidden" name="contenu" value="<?= e($c['contenu'] ?? '') ?>">
                                <input type="hidden" name="categorie" value="<?= e($c['categorie'] ?? '') ?>">
                                <input type="hidden" name="image_url" value="<?= e($c['image_url'] ?? '') ?>">
                                <input type="hidden" name="id_auteur" value="<?= (int)($c['id_auteur'] ?? 0) ?>">
                                                                <button class="<?= $isActive ? 'btn-outline' : 'btn-primary' ?>" type="submit">
                                    <?= $isActive ? '📝 Repasser brouillon' : '✅ Publier' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce conseil ?');">
                                <input type="hidden" name="delete_conseil_id" value="<?= (int)($c['id_conseil'] ?? 0) ?>">
                                <button class="btn-danger" type="submit">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="row-actions" style="margin-top:16px;justify-content:center;">
                <?php if ($page > 1): ?>
                    <a class="btn-outline" href="admin_conseils.php?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page - 1]))) ?>">← Précédent</a>
                <?php endif; ?>
                <span class="muted">Page <?= (int)$page ?> / <?= (int)$totalPages ?> (<?= (int)$total ?> résultats)</span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn-outline" href="admin_conseils.php?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page + 1]))) ?>">Suivant →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
