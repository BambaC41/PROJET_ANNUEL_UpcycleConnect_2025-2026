<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/ui_helpers.php';

$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$catFilter = (int)($_GET['cat'] ?? 0);
$activeFilter = trim((string)($_GET['active'] ?? 'all'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $id = (int)($_POST['id_prestation'] ?? 0);
    $selfId = (int)($_SESSION['user_id'] ?? 0);

    if ($action === 'create') {
        $ok = (bool)db_safe_exec(static function (PDO $pdo): bool {
            $st = $pdo->prepare('INSERT INTO prestation (titre, description, type, prix, is_active, id_categorie) VALUES (?, ?, ?, ?, ?, ?)');
            return $st->execute([
                trim((string)$_POST['titre']),
                trim((string)$_POST['description']),
                trim((string)$_POST['type']),
                (float)($_POST['prix'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
                (int)$_POST['id_categorie'],
            ]);
        }, false);
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => '✅ Offre créée.'] : ['type' => 'error', 'message' => '❌ Création impossible.'];
    } elseif ($action === 'update' && $id > 0) {
        $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($id): bool {
            $st = $pdo->prepare('UPDATE prestation SET titre=?, description=?, type=?, prix=?, is_active=?, id_categorie=? WHERE id_prestation=?');
            return $st->execute([
                trim((string)$_POST['titre']),
                trim((string)$_POST['description']),
                trim((string)$_POST['type']),
                (float)($_POST['prix'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
                (int)$_POST['id_categorie'],
                $id,
            ]);
        }, false);
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => '✅ Offre mise à jour.'] : ['type' => 'error', 'message' => '❌ Mise à jour impossible.'];
    } elseif ($action === 'toggle' && $id > 0) {
        db_safe_exec(static function (PDO $pdo) use ($id): void {
            $pdo->prepare('UPDATE prestation SET is_active = NOT is_active WHERE id_prestation = ?')->execute([$id]);
        }, null);
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '🔄 Statut modifié.'];
    } elseif ($action === 'delete' && $id > 0) {
        $linked = (int)db_safe_exec(static function (PDO $pdo) use ($id): int {
            $st = $pdo->prepare('SELECT COUNT(*) FROM session WHERE id_prestation = ?');
            $st->execute([$id]);
            return (int)$st->fetchColumn();
        }, 0);
        if ($linked > 0) {
            db_safe_exec(static function (PDO $pdo) use ($id): void {
                $pdo->prepare('UPDATE prestation SET is_active = 0 WHERE id_prestation = ?')->execute([$id]);
            }, null);
            $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => '⚠️ Offre désactivée (sessions liées).'];
        } else {
            $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($id): bool {
                return $pdo->prepare('DELETE FROM prestation WHERE id_prestation = ?')->execute([$id]);
            }, false);
            $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => '🗑️ Offre supprimée.'] : ['type' => 'error', 'message' => '❌ Suppression impossible.'];
        }
    }
    if (!empty($_SESSION['flash_toast']['type']) && ($_SESSION['flash_toast']['type'] ?? '') === 'success') {
        db_safe_exec(static function (PDO $pdo) use ($selfId, $action, $id): void {
            $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "prestation", ?, ?, NOW())')
                ->execute([$selfId, strtoupper($action) . '_PRESTATION', $id, 'admin_catalog']);
        }, null);
    }
    header('Location: admin_catalog.php');
    exit;
}

$categories = (array)db_safe_exec(static fn(PDO $pdo) => $pdo->query('SELECT id_categorie, nom FROM categorie_prestation ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC), []);
$prestations = (array)db_safe_exec(static function (PDO $pdo): array {
    return $pdo->query('SELECT p.*, c.nom AS categorie_nom,
        (SELECT COUNT(*) FROM session s WHERE s.id_prestation = p.id_prestation) AS sessions_count
        FROM prestation p JOIN categorie_prestation c ON c.id_categorie = p.id_categorie
        ORDER BY p.id_prestation DESC')->fetchAll(PDO::FETCH_ASSOC);
}, []);

$filtered = array_values(array_filter($prestations, static function ($p) use ($q, $typeFilter, $catFilter, $activeFilter) {
    $hay = mb_strtolower(($p['titre'] ?? '') . ' ' . ($p['description'] ?? ''));
    if ($q !== '' && !str_contains($hay, $q)) return false;
    if ($typeFilter !== '' && ($p['type'] ?? '') !== $typeFilter) return false;
    if ($catFilter > 0 && (int)$p['id_categorie'] !== $catFilter) return false;
    if ($activeFilter === '1' && empty($p['is_active'])) return false;
    if ($activeFilter === '0' && !empty($p['is_active'])) return false;
    return true;
}));

$kpis = [
    'total' => count($prestations),
    'actives' => count(array_filter($prestations, static fn($p) => !empty($p['is_active']))),
    'formations' => count(array_filter($prestations, static fn($p) => ($p['type'] ?? '') === 'formation')),
    'ateliers' => count(array_filter($prestations, static fn($p) => ($p['type'] ?? '') === 'atelier')),
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Catalogue des offres</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <section class="admin-section">
        <?php include 'includes/flash_toast.php'; ?>
        
        <h1>🛍️ Catalogue des offres</h1>
        
        <div class="pro-kpis">
            <article class="pro-kpi"><h3>Total offres</h3><p><?= (int)$kpis['total'] ?></p></article>
            <article class="pro-kpi"><h3>Actives</h3><p><?= (int)$kpis['actives'] ?></p></article>
            <article class="pro-kpi"><h3>Formations</h3><p><?= (int)$kpis['formations'] ?></p></article>
            <article class="pro-kpi"><h3>Ateliers</h3><p><?= (int)$kpis['ateliers'] ?></p></article>
        </div>
        
        <button class="btn-primary" onclick="openModal('createModal')">➕ Nouvelle offre</button>
        
        <form method="GET" class="row-actions" style="margin:20px 0;">
            <input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Recherche" style="width:200px;">
            <select class="input" name="type" style="width:150px;">
                <option value="">📋 Tous types</option>
                <option value="atelier" <?= $typeFilter === 'atelier' ? 'selected' : '' ?>>Atelier</option>
                <option value="formation" <?= $typeFilter === 'formation' ? 'selected' : '' ?>>Formation</option>
                <option value="service" <?= $typeFilter === 'service' ? 'selected' : '' ?>>Service</option>
            </select>
            <select class="input" name="cat" style="width:180px;">
                <option value="0">📁 Toutes catégories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id_categorie'] ?>" <?= $catFilter === (int)$c['id_categorie'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" name="active" style="width:130px;">
                <option value="all" <?= $activeFilter === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Actifs</option>
                <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Inactifs</option>
            </select>
            <button class="btn-outline" type="submit">🔍 Filtrer</button>
            <a href="admin_catalog.php" class="btn-outline">⟳ Réinitialiser</a>
        </form>
        
        <div class="offers-grid">
            <?php foreach ($filtered as $p): 
                $isActive = !empty($p['is_active']);
                $typeClass = 'type-' . ($p['type'] ?? 'service');
            ?>
                <div class="offer-card">
                    <div class="offer-title"><?= e($p['titre']) ?></div>
                    <span class="offer-type <?= $typeClass ?>"><?= e($p['type']) ?></span>
                    <div class="offer-price"><?= formatPriceEur($p['prix'] ?? 0) ?></div>
                    <div class="offer-actions">
                        <button class="btn-outline btn-sm" onclick='openEditModal(<?= htmlspecialchars(json_encode([
                            'id' => $p['id_prestation'],
                            'titre' => $p['titre'],
                            'description' => $p['description'],
                            'type' => $p['type'],
                            'categorie' => $p['id_categorie'],
                            'prix' => $p['prix'],
                            'is_active' => $isActive
                        ])) ?>)'>✏️ Modifier</button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id_prestation" value="<?= (int)$p['id_prestation'] ?>">
                            <button class="btn-outline btn-sm" type="submit">🔄 <?= $isActive ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($filtered)): ?>
            <div class="empty-state">📭 Aucune offre trouvée.</div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal Création -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Nouvelle offre</h2>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Titre *</label>
                <input class="input" name="titre" required>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea class="input" name="description" rows="3" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Type *</label>
                    <select class="input" name="type" required>
                        <option value="atelier">📦 Atelier</option>
                        <option value="formation">🎓 Formation</option>
                        <option value="service">🔧 Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Catégorie *</label>
                    <select class="input" name="id_categorie" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id_categorie'] ?>"><?= e($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Prix (€)</label>
                <input class="input" type="number" step="0.01" name="prix" value="0">
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="is_active" value="1" checked> Active
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Annuler</button>
                <button class="btn-primary" type="submit">💾 Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Édition -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Modifier l'offre</h2>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_prestation" id="edit_id">
            <div class="form-group">
                <label>Titre *</label>
                <input class="input" name="titre" id="edit_titre" required>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea class="input" name="description" id="edit_description" rows="3" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Type *</label>
                    <select class="input" name="type" id="edit_type">
                        <option value="atelier">📦 Atelier</option>
                        <option value="formation">🎓 Formation</option>
                        <option value="service">🔧 Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Catégorie *</label>
                    <select class="input" name="id_categorie" id="edit_categorie">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id_categorie'] ?>"><?= e($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Prix (€)</label>
                <input class="input" type="number" step="0.01" name="prix" id="edit_prix">
            </div>
            <div class="checkbox-group">
                <input type="checkbox" name="is_active" value="1" id="edit_active"> Active
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Annuler</button>
                <button class="btn-primary" type="submit">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
function openEditModal(offer) {
    document.getElementById('edit_id').value = offer.id;
    document.getElementById('edit_titre').value = offer.titre;
    document.getElementById('edit_description').value = offer.description;
    document.getElementById('edit_type').value = offer.type;
    document.getElementById('edit_categorie').value = offer.categorie;
    document.getElementById('edit_prix').value = offer.prix;
    document.getElementById('edit_active').checked = offer.is_active;
    openModal('editModal');
}
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
}
</script>
</body>
</html>