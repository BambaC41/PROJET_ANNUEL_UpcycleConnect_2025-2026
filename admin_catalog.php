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
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => 'Offre créée.'] : ['type' => 'error', 'message' => 'Création impossible.'];
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
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => 'Offre mise à jour.'] : ['type' => 'error', 'message' => 'Mise à jour impossible.'];
    } elseif ($action === 'toggle' && $id > 0) {
        db_safe_exec(static function (PDO $pdo) use ($id): void {
            $pdo->prepare('UPDATE prestation SET is_active = NOT is_active WHERE id_prestation = ?')->execute([$id]);
        }, null);
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Statut actif/inactif basculé.'];
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
            $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Offre désactivée (sessions liées).'];
        } else {
            $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($id): bool {
                return $pdo->prepare('DELETE FROM prestation WHERE id_prestation = ?')->execute([$id]);
            }, false);
            $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => 'Offre supprimée.'] : ['type' => 'error', 'message' => 'Suppression impossible.'];
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
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>🛍️ Catalogue des offres</h1>
        <p class="muted" style="margin-bottom:16px;">
            📌 <strong>Type</strong> = atelier / formation / service<br>
            📌 <strong>Catégorie</strong> = Atelier / Formation / Réparation (plus précis)<br>
            📌 <strong>Prix</strong> = prix de base pour les événements créés à partir de cette offre
        </p>
        
        <div class="pro-kpis" style="margin-bottom:24px;">
            <article class="pro-kpi"><h3>Total offres</h3><p><?= (int)$kpis['total'] ?></p></article>
            <article class="pro-kpi"><h3>Actives</h3><p><?= (int)$kpis['actives'] ?></p></article>
            <article class="pro-kpi"><h3>Formations</h3><p><?= (int)$kpis['formations'] ?></p></article>
            <article class="pro-kpi"><h3>Ateliers</h3><p><?= (int)$kpis['ateliers'] ?></p></article>
        </div>
        
        <button class="btn-primary" type="button" onclick="openModal('modal-create-prestation')">➕ Nouvelle offre</button>
        
        <form method="GET" class="row-actions" style="margin:16px 0; flex-wrap:wrap;">
            <input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Recherche" style="width:200px;">
            <select class="input" name="type" style="width:150px;">
                <option value="">📋 Type</option>
                <option value="atelier">atelier</option>
                <option value="formation">formation</option>
                <option value="service">service</option>
            </select>
            <select class="input" name="cat" style="width:180px;">
                <option value="0">📁 Catégorie</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id_categorie'] ?>" <?= $catFilter === (int)$c['id_categorie'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Type</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Sessions</th>
                        <th>Actif</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filtered as $p): ?>
                    <tr>
                        <td><?= (int)$p['id_prestation'] ?></td>
                        <td><strong><?= e($p['titre']) ?></strong></td>
                        <td><span class="status-badge status-info"><?= e($p['type']) ?></span></td>
                        <td><?= e($p['categorie_nom']) ?></td>
                        <td><?= formatPriceEur($p['prix'] ?? 0) ?></td>
                        <td><?= (int)$p['sessions_count'] ?></td>
                        <td><?= !empty($p['is_active']) ? '<span class="status-badge status-ok">✅ Actif</span>' : '<span class="status-badge status-muted">❌ Inactif</span>' ?></td>
                        <td class="row-actions">
                            <button class="btn-outline" type="button" onclick="openModal('modal-edit-<?= (int)$p['id_prestation'] ?>')">✏️ Modifier</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id_prestation" value="<?= (int)$p['id_prestation'] ?>">
                                <button class="btn-outline" type="submit">🔄 Activer/Désact.</button>
                            </form>
                        </div>
                     </tr>
                <?php endforeach; ?>
                </tbody>
              </div>
        </div>
    </section>
</main>

<!-- Modal création -->
<div id="modal-create-prestation" class="modal" aria-hidden="true"><div class="modal-backdrop"></div><div class="modal-content">
<h2>➕ Nouvelle offre</h2>
<form method="POST"><input type="hidden" name="action" value="create">
<input class="input" name="titre" required placeholder="Titre (ex: Atelier poterie)">
<textarea class="input" name="description" rows="3" required placeholder="Description de l'offre"></textarea>
<select class="input" name="type" required>
    <option value="">-- Choisir un type --</option>
    <option value="atelier">atelier</option>
    <option value="formation">formation</option>
    <option value="service">service</option>
</select>
<select class="input" name="id_categorie" required>
    <option value="">-- Choisir une catégorie --</option>
    <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id_categorie'] ?>"><?= e($c['nom']) ?></option>
    <?php endforeach; ?>
</select>
<div>
    <label>💰 Prix (en euros)</label>
    <input class="input" type="number" step="0.01" name="prix" value="0" placeholder="Ex: 29.99">
</div>
<label><input type="checkbox" name="is_active" value="1" checked> Active</label>
<div class="row-actions" style="margin-top:16px;">
    <button class="btn-primary" type="submit">💾 Enregistrer</button>
    <button class="btn-outline" type="button" data-close-modal="modal-create-prestation">❌ Annuler</button>
</div>
</form></div></div>

<?php foreach ($filtered as $p): ?>
<div id="modal-edit-<?= (int)$p['id_prestation'] ?>" class="modal" aria-hidden="true"><div class="modal-backdrop"></div><div class="modal-content">
<h2>✏️ Modifier offre #<?= (int)$p['id_prestation'] ?></h2>
<form method="POST"><input type="hidden" name="action" value="update"><input type="hidden" name="id_prestation" value="<?= (int)$p['id_prestation'] ?>">
<input class="input" name="titre" value="<?= e($p['titre']) ?>" required>
<textarea class="input" name="description" rows="3" required><?= e($p['description']) ?></textarea>
<select class="input" name="type">
    <option value="atelier" <?= ($p['type']??'')==='atelier'?'selected':'' ?>>atelier</option>
    <option value="formation" <?= ($p['type']??'')==='formation'?'selected':'' ?>>formation</option>
    <option value="service" <?= ($p['type']??'')==='service'?'selected':'' ?>>service</option>
</select>
<select class="input" name="id_categorie">
    <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id_categorie'] ?>" <?= (int)$p['id_categorie']===(int)$c['id_categorie']?'selected':'' ?>><?= e($c['nom']) ?></option>
    <?php endforeach; ?>
</select>
<input class="input" type="number" step="0.01" name="prix" value="<?= e((string)$p['prix']) ?>">
<label><input type="checkbox" name="is_active" value="1" <?= !empty($p['is_active'])?'checked':'' ?>> Active</label>
<div class="row-actions" style="margin-top:16px;">
    <button class="btn-primary" type="submit">💾 Enregistrer</button>
    <button class="btn-outline" type="button" onclick="closeModal('modal-edit-<?= (int)$p['id_prestation'] ?>')">❌ Fermer</button>
</div>
</form></div></div>
<?php endforeach; ?>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
});
window.onclick = function(e) {
    if (e.target.classList.contains('modal-backdrop')) e.target.parentElement.style.display = 'none';
}
</script>
</body>
</html>