<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/conteneur.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conteneur'])) {
    $payload = [
        'code' => trim((string)($_POST['code'] ?? '')),
        'adresse' => trim((string)($_POST['adresse'] ?? '')),
        'statut' => trim((string)($_POST['statut'] ?? 'actif')),
        'date_installation' => trim((string)($_POST['date_installation'] ?? '')),
        'derniere_maintenance' => trim((string)($_POST['derniere_maintenance'] ?? '')),
    ];
    $res = api_create_conteneur($payload);
    $pdoOk = false;
    if (($res['status'] ?? 0) !== 201) {
        $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($payload): bool {
            $stmt = $pdo->prepare('INSERT INTO conteneur (code, adresse, statut, date_installation, derniere_maintenance) VALUES (?, ?, ?, NULLIF(?, ""), NULLIF(?, ""))');
            return $stmt->execute([
                $payload['code'],
                $payload['adresse'],
                $payload['statut'],
                $payload['date_installation'],
                $payload['derniere_maintenance'],
            ]);
        }, false);
    }
    $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 201) || $pdoOk)
        ? ['type' => 'success', 'message' => 'Conteneur cree.']
        : ['type' => 'error', 'message' => 'Creation conteneur impossible.'];
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_conteneur_id'])) {
    $cid = (int)$_POST['update_conteneur_id'];
    $payload = [
        'code' => trim((string)($_POST['edit_code'] ?? '')),
        'adresse' => trim((string)($_POST['edit_adresse'] ?? '')),
        'statut' => trim((string)($_POST['edit_statut'] ?? 'actif')),
        'date_installation' => trim((string)($_POST['edit_date_installation'] ?? '')),
        'derniere_maintenance' => trim((string)($_POST['edit_derniere_maintenance'] ?? '')),
    ];
    $res = api_update_conteneur($cid, $payload);
    $pdoOk = false;
    if (($res['status'] ?? 0) !== 200) {
        $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($cid, $payload): bool {
            $stmt = $pdo->prepare('UPDATE conteneur SET code = ?, adresse = ?, statut = ?, date_installation = NULLIF(?, ""), derniere_maintenance = NULLIF(?, "") WHERE id_conteneur = ?');
            return $stmt->execute([
                $payload['code'],
                $payload['adresse'],
                $payload['statut'],
                $payload['date_installation'],
                $payload['derniere_maintenance'],
                $cid,
            ]);
        }, false);
    }
    $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 200) || $pdoOk)
        ? ['type' => 'success', 'message' => 'Conteneur mis a jour.']
        : ['type' => 'error', 'message' => 'Mise a jour impossible.'];
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conteneur_id'])) {
    $cid = (int)$_POST['delete_conteneur_id'];
    $linked = (int)db_safe_exec(function (PDO $pdo) use ($cid) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM demande_depot WHERE id_conteneur = ?');
        $stmt->execute([$cid]);
        return (int)$stmt->fetchColumn();
    }, 0);
    if ($linked > 0) {
        db_safe_exec(function (PDO $pdo) use ($cid): void {
            $stmt = $pdo->prepare('UPDATE conteneur SET statut = "inactif" WHERE id_conteneur = ?');
            $stmt->execute([$cid]);
        }, null);
        $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Conteneur desactive (demandes liees).'];
    } else {
        $res = api_delete_conteneur_admin($cid);
        $pdoOk = false;
        if (($res['status'] ?? 0) !== 200) {
            $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($cid): bool {
                $stmt = $pdo->prepare('DELETE FROM conteneur WHERE id_conteneur = ?');
                return $stmt->execute([$cid]);
            }, false);
        }
        $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 200) || $pdoOk)
            ? ['type' => 'success', 'message' => 'Conteneur supprime.']
            : ['type' => 'error', 'message' => 'Suppression impossible.'];
    }
    header('Location: admin_conteneurs.php');
    exit();
}

$conteneursRes = api_get_conteneurs();
$conteneurs = (($conteneursRes['status'] ?? 0) === 200 && is_array($conteneursRes['data'] ?? null)) ? $conteneursRes['data'] : [];
if ($conteneurs === []) {
    $conteneurs = (array)db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT * FROM conteneur ORDER BY id_conteneur DESC')->fetchAll(PDO::FETCH_ASSOC), []);
}

$cSort = trim((string)($_GET['c_sort'] ?? 'code'));
usort($conteneurs, static function ($a, $b) use ($cSort) {
    if ($cSort === 'statut') return strcmp((string)($a['statut'] ?? ''), (string)($b['statut'] ?? ''));
    return strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''));
});

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($conteneurs as $c) {
        if ((int)($c['id_conteneur'] ?? 0) === $editId) { $editRow = $c; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Conteneurs</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .status-info { background: #e3f2fd; color: #1976d2; }
        .error-box { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .nav-links {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .nav-link {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            background: #f0f0f0;
            color: #333;
        }
        .nav-link.active {
            background: #4caf50;
            color: white;
        }
        .nav-link:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <!-- Navigation entre les deux pages -->
    <div class="nav-links">
        <a href="admin_conteneurs.php" class="nav-link active">📦 Conteneurs</a>
        <a href="admin_demandes_depot.php" class="nav-link">📋 Demandes de depot</a>
    </div>
    
    <section class="pro-card">
        <h1>📦 Gestion des conteneurs</h1>
        
        <!-- Formulaire de création -->
        <div style="margin-bottom: 30px;">
            <h3>➕ Nouveau conteneur</h3>
            <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <input type="hidden" name="create_conteneur" value="1">
                <input class="input" name="code" placeholder="Code conteneur" required>
                <input class="input" name="adresse" placeholder="Adresse" required>
                <select class="input" name="statut">
                    <option value="actif">✅ Actif</option>
                    <option value="inactif">❌ Inactif</option>
                    <option value="maintenance">🔧 Maintenance</option>
                </select>
                <input class="input" type="date" name="date_installation" placeholder="Date installation">
                <input class="input" type="date" name="derniere_maintenance" placeholder="Dernière maintenance">
                <button class="btn-primary" type="submit">➕ Creer</button>
            </form>
        </div>
        
        <!-- Liste des conteneurs -->
        <h3>🗑️ Liste des conteneurs</h3>
        <form method="GET" class="row-actions" style="margin-bottom: 15px;">
            <select class="input" name="c_sort" style="width: auto;">
                <option value="code" <?= $cSort === 'code' ? 'selected' : '' ?>>Trier par code</option>
                <option value="statut" <?= $cSort === 'statut' ? 'selected' : '' ?>>Trier par statut</option>
            </select>
            <button class="btn-outline" type="submit">Appliquer</button>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Code</th><th>Adresse</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($conteneurs as $c): ?>
                    <tr>
                        <td><?= e($c['id_conteneur'] ?? '') ?></td>
                        <td><strong><?= e($c['code'] ?? '') ?></strong></td>
                        <td><?= e($c['adresse'] ?? '') ?></td>
                        <td><span class="status-badge status-info"><?= e($c['statut'] ?? '') ?></span></td>
                        <td class="row-actions">
                            <a class="btn-outline" href="admin_conteneurs.php?edit=<?= (int)($c['id_conteneur'] ?? 0) ?>">✏️ Modifier</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmer suppression / desactivation ?');">
                                <input type="hidden" name="delete_conteneur_id" value="<?= e($c['id_conteneur'] ?? 0) ?>">
                                <button class="btn-danger" type="submit">🗑️ Supprimer</button>
                            </form>
                         </span>
                     </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Formulaire d'edition (si un conteneur est en cours d'edition) -->
        <?php if ($editRow !== null): ?>
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h3>✏️ Modifier conteneur #<?= e((string)$editId) ?></h3>
                <form method="POST" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <input type="hidden" name="update_conteneur_id" value="<?= e((string)$editId) ?>">
                    <input class="input" name="edit_code" value="<?= e((string)($editRow['code'] ?? '')) ?>" required>
                    <input class="input" name="edit_adresse" value="<?= e((string)($editRow['adresse'] ?? '')) ?>" required>
                    <select class="input" name="edit_statut">
                        <?php foreach (['actif', 'inactif', 'maintenance'] as $st): ?>
                            <option value="<?= e($st) ?>" <?= (($editRow['statut'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="input" type="date" name="edit_date_installation" value="<?= e(substr((string)($editRow['date_installation'] ?? ''), 0, 10)) ?>">
                    <input class="input" type="date" name="edit_derniere_maintenance" value="<?= e(substr((string)($editRow['derniere_maintenance'] ?? ''), 0, 10)) ?>">
                    <div class="row-actions">
                        <button class="btn-primary" type="submit">💾 Enregistrer</button>
                        <a class="btn-outline" href="admin_conteneurs.php">❌ Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php  ?>
</body>
</html>