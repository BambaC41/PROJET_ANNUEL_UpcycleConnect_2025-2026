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

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================
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
        
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $payload) {
            $st = $pdo->prepare('UPDATE conseil SET titre = ?, contenu = ?, categorie = ?, image_url = ?, is_active = ? WHERE id_conseil = ?');
            return $st->execute([$payload['titre'], $payload['contenu'], $payload['categorie'], $payload['image_url'], $payload['is_active'] ? 1 : 0, $id]);
        }, false);
        
        if ($ok) {
            $authorId = (int)($_POST['id_auteur'] ?? 0);
            if ($authorId > 0) {
                $msg = $isActive ? 'Votre conseil/news a été publié par l\'équipe.' : 'Votre conseil/news a été repassé en brouillon.';
                notif_create($authorId, 'conseil', $isActive ? 'Conseil publié' : 'Conseil en brouillon', $msg);
            }
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => $isActive ? '✅ Conseil publié.' : '📝 Conseil repassé en brouillon.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Échec de la mise à jour.'];
        }
        header('Location: admin_conseils.php?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page])));
        exit;
    }

    if (isset($_POST['delete_conseil_id'])) {
        $id = (int)$_POST['delete_conseil_id'];
        
        $imageToDelete = '';
        db_safe_exec(function (PDO $pdo) use ($id, &$imageToDelete) {
            $st = $pdo->prepare('SELECT image_url FROM conseil WHERE id_conseil = ?');
            $st->execute([$id]);
            $imageToDelete = (string)$st->fetchColumn();
            return true;
        }, false);
        
        $res = api_delete_conseil($id);
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id) {
            $st = $pdo->prepare('DELETE FROM conseil WHERE id_conseil = ?');
            return $st->execute([$id]);
        }, false);
        
        if ($ok) {
            if (!empty($imageToDelete) && file_exists(__DIR__ . '/' . $imageToDelete)) {
                unlink(__DIR__ . '/' . $imageToDelete);
            }
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '🗑️ Conseil supprimé.'];
        } else {
            $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Suppression impossible.'];
        }
        header('Location: admin_conseils.php?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page])));
        exit;
    }
}

// ============================================
// RÉCUPÉRATION DES CONSEILS
// ============================================
$conseils = (array)db_safe_exec(function (PDO $pdo) use ($q, $status, $author, $dateFrom, $dateTo, $page, $perPage) {
    $sql = "SELECT c.*, COALESCE(u.pseudo, CONCAT('User #', c.id_auteur)) AS auteur_pseudo, u.email 
            FROM conseil c
            LEFT JOIN utilisateur u ON u.id_user = c.id_auteur
            WHERE 1=1";
    $params = [];
    
    if ($q !== '') {
        $sql .= " AND (c.titre LIKE ? OR c.contenu LIKE ? OR c.categorie LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($status === 'draft') {
        $sql .= " AND c.is_active = 0";
    } elseif ($status === 'active') {
        $sql .= " AND c.is_active = 1";
    }
    
    if ($author > 0) {
        $sql .= " AND c.id_auteur = ?";
        $params[] = $author;
    }
    
    if ($dateFrom !== '') {
        $sql .= " AND DATE(c.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== '') {
        $sql .= " AND DATE(c.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " ORDER BY c.id_conseil DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);

$total = (int)db_safe_exec(function (PDO $pdo) use ($q, $status, $author, $dateFrom, $dateTo) {
    $sql = "SELECT COUNT(*) FROM conseil c WHERE 1=1";
    $params = [];
    
    if ($q !== '') {
        $sql .= " AND (c.titre LIKE ? OR c.contenu LIKE ? OR c.categorie LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($status === 'draft') {
        $sql .= " AND c.is_active = 0";
    } elseif ($status === 'active') {
        $sql .= " AND c.is_active = 1";
    }
    
    if ($author > 0) {
        $sql .= " AND c.id_auteur = ?";
        $params[] = $author;
    }
    
    if ($dateFrom !== '') {
        $sql .= " AND DATE(c.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo !== '') {
        $sql .= " AND DATE(c.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}, 0);

$totalPages = max(1, (int)ceil($total / $perPage));

$authors = [];
$authorRows = (array)db_safe_exec(function (PDO $pdo) {
    $st = $pdo->query('SELECT DISTINCT c.id_auteur, COALESCE(u.pseudo, CONCAT("User #", c.id_auteur)) AS pseudo 
                        FROM conseil c 
                        LEFT JOIN utilisateur u ON u.id_user = c.id_auteur 
                        WHERE c.id_auteur IS NOT NULL 
                        ORDER BY pseudo');
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}, []);
foreach ($authorRows as $row) {
    $aid = (int)($row['id_auteur'] ?? 0);
    if ($aid > 0) $authors[$aid] = trim((string)($row['pseudo'] ?? ''));
}

$detail = null;
if ($viewId > 0) {
    foreach ($conseils as $c) {
        if ((int)($c['id_conseil'] ?? 0) === $viewId) { 
            $detail = $c; 
            break; 
        }
    }
}

function adminConseilImage($c) {
    if (!empty($c['image_url'])) {
        return '/upcycle/' . $c['image_url'];
    }
    return null;
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
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>💡 Conseils & News</h1>
        <p class="muted">Gérez les conseils soumis par les salariés : publiez, repassez en brouillon ou supprimez.</p>

        <form method="GET" class="row-actions" style="flex-wrap:wrap;gap:8px;margin-bottom:20px;">
            <input class="input" name="q" value="<?= e($q) ?>" placeholder="Recherche titre, contenu…" style="width:200px;">
            <select class="input" name="status" style="width:130px;">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Publiés</option>
            </select>
            <select class="input" name="author" style="width:150px;">
                <option value="0">Tous les auteurs</option>
                <?php foreach ($authors as $aid => $label): ?>
                    <option value="<?= (int)$aid ?>" <?= $author === (int)$aid ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>" style="width:130px;">
            <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>" style="width:130px;">
            <button class="btn-outline" type="submit">Filtrer</button>
            <a class="btn-outline" href="admin_conseils.php">Réinitialiser</a>
        </form>

        <?php if ($detail): ?>
            <div class="detail-card">
                <h2>📄 Détail du conseil #<?= (int)($detail['id_conseil'] ?? 0) ?></h2>
                <p><strong>Titre :</strong> <?= e($detail['titre'] ?? '') ?></p>
                <p><strong>Catégorie :</strong> <?= e($detail['categorie'] ?? '') ?></p>
                <p><strong>Auteur :</strong> <?= e($detail['auteur_pseudo'] ?? ('#' . (int)($detail['id_auteur'] ?? 0))) ?></p>
                <p><strong>Statut :</strong> 
                    <span class="status-badge <?= !empty($detail['is_active']) ? 'status-ok' : 'status-warn' ?>">
                        <?= !empty($detail['is_active']) ? '✅ Publié' : '🕒 Brouillon' ?>
                    </span>
                </p>
                <p><strong>Créé le :</strong> <?= e(formatDateFr($detail['created_at'] ?? '')) ?></p>
                <?php $imgDetail = adminConseilImage($detail); ?>
                <?php if ($imgDetail && file_exists(__DIR__ . '/' . $detail['image_url'])): ?>
                    <p><strong>Image :</strong></p>
                    <img src="<?= e($imgDetail) ?>" alt="Image du conseil" class="detail-image">
                <?php endif; ?>
                <div class="detail-content">
                    <strong>Contenu :</strong><br>
                    <?= nl2br(e($detail['contenu'] ?? '')) ?>
                </div>
                <a class="btn-outline" href="admin_conseils.php?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'page' => $page]))) ?>" style="margin-top:16px;display:inline-block;">← Retour à la liste</a>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Auteur</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($conseils)): ?>
                    <tr><td colspan="8" class="empty-table">📭 Aucun conseil trouvé.</td></tr>
                <?php endif; ?>
                <?php foreach ($conseils as $c): ?>
                    <?php $isActive = !empty($c['is_active']); ?>
                    <?php $imgPath = adminConseilImage($c); ?>
                    <tr>
                        <td><?= (int)($c['id_conseil'] ?? 0) ?></td>
                        <td>
                            <?php if ($imgPath && file_exists(__DIR__ . '/' . $c['image_url'])): ?>
                                <img src="<?= e($imgPath) ?>" class="conseil-preview-img" alt="Image">
                            <?php else: ?>
                                <span class="muted" style="font-size:20px;">📷</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e(mb_substr($c['titre'] ?? '', 0, 50)) ?></strong></td>
                        <td><?= e($c['categorie'] ?? '—') ?></td>
                        <td><?= e($c['auteur_pseudo'] ?? ('#' . (int)($c['id_auteur'] ?? 0))) ?></td>
                        <td>
                            <span class="status-badge <?= $isActive ? 'status-ok' : 'status-warn' ?>">
                                <?= $isActive ? '✅ Publié' : '🕒 Brouillon' ?>
                            </span>
                        </td>
                        <td><?= e(formatDateFr($c['created_at'] ?? '')) ?></td>
                        <td class="row-actions">
                            <a class="btn-outline" href="admin_conseils.php?view=<?= (int)($c['id_conseil'] ?? 0) ?>&<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'author' => $author ?: null, 'page' => $page]))) ?>">🔍 Détails</a>
                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="toggle_conseil_id" value="<?= (int)($c['id_conseil'] ?? 0) ?>">
                                <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                                <input type="hidden" name="titre" value="<?= e($c['titre'] ?? '') ?>">
                                <input type="hidden" name="contenu" value="<?= e($c['contenu'] ?? '') ?>">
                                <input type="hidden" name="categorie" value="<?= e($c['categorie'] ?? '') ?>">
                                <input type="hidden" name="image_url" value="<?= e($c['image_url'] ?? '') ?>">
                                <input type="hidden" name="id_auteur" value="<?= (int)($c['id_auteur'] ?? 0) ?>">
                                <button class="<?= $isActive ? 'btn-outline' : 'btn-success' ?>" type="submit">
                                    <?= $isActive ? '📝 Repasser brouillon' : '✅ Publier' ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Supprimer définitivement ce conseil ? Cette action est irréversible.');">
                                <input type="hidden" name="delete_conseil_id" value="<?= (int)($c['id_conseil'] ?? 0) ?>">
                                <button class="btn-danger" type="submit">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="row-actions" style="margin-top:20px;justify-content:center;">
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
<?php  ?>
</body>
</html>