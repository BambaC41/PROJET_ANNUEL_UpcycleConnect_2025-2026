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
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 85vh;
            overflow-y: auto;
            padding: 28px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #4caf50;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 22px;
            color: #2e7d32;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #555;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }
        .form-group textarea { resize: vertical; }
        .form-row { display: flex; gap: 16px; }
        .form-row .form-group { flex: 1; }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        .btn-cancel {
            background: #f0f0f0;
            color: #666;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0;
        }
        .checkbox-group input {
            width: auto;
        }
        .badge-active { background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-inactive { background: #f5f5f5; color: #999; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; }
        .offer-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 20px;
            transition: all 0.2s;
        }
        .offer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .offer-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: #1a1a2e;
        }
        .offer-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .type-atelier { background: #e3f2fd; color: #1565c0; }
        .type-formation { background: #e8f5e9; color: #2e7d32; }
        .type-service { background: #fff3e0; color: #ef6c00; }
        .offer-price {
            font-size: 20px;
            font-weight: 700;
            color: #2e7d32;
            margin: 12px 0;
        }
        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .offer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 20px;
            cursor: pointer;
        }
        .admin-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px;
        }
        .pro-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .pro-kpi {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }
        .pro-kpi h3 {
            font-size: 13px;
            color: #666;
            margin: 0 0 8px 0;
            font-weight: 500;
        }
        .pro-kpi p {
            font-size: 28px;
            font-weight: 700;
            color: #2e7d32;
            margin: 0;
        }
        .row-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            display: inline-block;
        }
        .btn-primary {
            background: #4caf50;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
        }
        .input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
    </style>
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