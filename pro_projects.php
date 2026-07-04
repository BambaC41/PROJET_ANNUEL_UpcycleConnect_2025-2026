<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

$proId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_project'])) {
        $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($proId): bool {
            $st = $pdo->prepare('INSERT INTO projet_upcycling (id_pro, titre, description, statut, progression, is_public, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            return $st->execute([
                $proId,
                trim((string)$_POST['titre']),
                trim((string)$_POST['description']),
                trim((string)($_POST['statut'] ?? 'brouillon')),
                max(0, min(100, (int)($_POST['progression'] ?? 0))),
                !empty($_POST['is_public']) ? 1 : 0,
            ]);
        }, false);
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => 'Projet créé.'] : ['type' => 'error', 'message' => 'Création impossible.'];
    } elseif (isset($_POST['update_project_id'])) {
        $pid = (int)$_POST['update_project_id'];
        $ok = (bool)db_safe_exec(static function (PDO $pdo) use ($proId, $pid): bool {
            $st = $pdo->prepare('UPDATE projet_upcycling SET titre=?, description=?, statut=?, progression=?, is_public=? WHERE id_projet=? AND id_pro=?');
            return $st->execute([
                trim((string)$_POST['titre']),
                trim((string)$_POST['description']),
                trim((string)$_POST['statut']),
                max(0, min(100, (int)($_POST['progression'] ?? 0))),
                !empty($_POST['is_public']) ? 1 : 0,
                $pid,
                $proId,
            ]);
        }, false);
        $_SESSION['flash_toast'] = $ok ? ['type' => 'success', 'message' => 'Projet mis à jour.'] : ['type' => 'error', 'message' => 'Mise à jour impossible.'];
        if ($ok && ($_POST['statut'] ?? '') === 'publie') {
            notif_create($proId, 'projet', 'Projet publié', 'Votre projet « ' . trim((string)$_POST['titre']) . ' » est visible.');
        }
    }
    header('Location: pro_projects.php');
    exit;
}

$myProjects = (array)db_safe_exec(static function (PDO $pdo) use ($proId) {
    $st = $pdo->prepare('SELECT * FROM projet_upcycling WHERE id_pro = ? ORDER BY id_projet DESC');
    $st->execute([$proId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}, []);

$publicProjects = (array)db_safe_exec(static function (PDO $pdo) use ($proId) {
    $st = $pdo->prepare('
        SELECT p.*, u.pseudo AS pro_pseudo, u.email AS pro_email
        FROM projet_upcycling p
        JOIN utilisateur u ON u.id_user = p.id_pro
        WHERE p.is_public = 1 AND p.statut = "publie" AND p.id_pro != ?
        ORDER BY p.created_at DESC
    ');
    $st->execute([$proId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}, []);

$countStat = static fn(string $s) => count(array_filter($myProjects, static fn($p) => ($p['statut'] ?? '') === $s));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Projets Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/ui-components.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <script src="scripts/modal.js" defer></script>
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<?php include 'includes/flash_toast.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1>📁 Mes projets upcycling</h1>
        <div class="admin-kpi-grid">
            <div class="admin-card"><h3>Brouillon</h3><p><?= $countStat('brouillon') ?></p></div>
            <div class="admin-card"><h3>En cours</h3><p><?= $countStat('en_cours') ?></p></div>
            <div class="admin-card"><h3>Publiés</h3><p><?= $countStat('publie') ?></p></div>
            <div class="admin-card"><h3>Terminés</h3><p><?= $countStat('termine') ?></p></div>
        </div>
        <button class="btn-primary" type="button" onclick="openModal('modal-new-project')">+ Nouveau projet</button>

        <?php if (empty($myProjects)): ?>
            <?php render_empty_state('Aucun projet personnel', 'Créez votre premier projet upcycling.', '+ Créer', '#'); ?>
        <?php else: ?>
        <div class="pro-grid" style="margin-top:18px;">
            <?php foreach ($myProjects as $p): ?>
                <article class="pro-card">
                    <h2><?= e($p['titre'] ?? '') ?></h2>
                    <p class="muted"><?= e($p['statut'] ?? '') ?> · <?= (int)($p['progression'] ?? 0) ?>%</p>
                    <div class="progression-bar"><div class="fill" style="width:<?= (int)($p['progression']??0) ?>%;"></div></div>
                    <p><?= e(mb_substr((string)($p['description'] ?? ''), 0, 120)) ?></p>
                    <button class="btn-outline" type="button" onclick="openModal('modal-proj-<?= (int)$p['id_projet'] ?>')">Modifier</button>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($publicProjects)): ?>
        <hr style="margin: 40px 0 20px;">
        <h2>🌍 Projets publics des autres professionnels</h2>
        <div class="pro-grid" style="margin-top:18px;">
            <?php foreach ($publicProjects as $p): ?>
                <article class="pro-card project-card" onclick='viewPublicProject(<?= htmlspecialchars(json_encode([
                    'id' => $p['id_projet'],
                    'titre' => $p['titre'],
                    'description' => $p['description'],
                    'statut' => $p['statut'],
                    'progression' => (int)$p['progression'],
                    'pro_pseudo' => $p['pro_pseudo'],
                    'pro_email' => $p['pro_email'],
                    'is_public' => (bool)$p['is_public'],
                    'created_at' => formatDateFr($p['created_at']),
                ]), JSON_HEX_TAG) ?>)'>
                    <h3><?= e($p['titre'] ?? '') ?></h3>
                    <p class="muted">Par <?= e($p['pro_pseudo'] ?? 'Inconnu') ?></p>
                    <div class="progression-bar"><div class="fill" style="width:<?= (int)($p['progression']??0) ?>%;"></div></div>
                    <p><?= e(mb_substr((string)($p['description'] ?? ''), 0, 100)) ?></p>
                    <span class="badge-statut badge-<?= e($p['statut'] ?? 'brouillon') ?>"><?= e($p['statut'] ?? '') ?></span>
                    <span style="font-size:12px;color:#999;margin-left:8px;">👁️ Cliquez pour voir plus</span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<div id="modal-new-project" class="modal" aria-hidden="true"><div class="modal-backdrop"></div><div class="modal-content">
<h2>Nouveau projet</h2>
<form method="POST"><input type="hidden" name="create_project" value="1">
<input class="input" name="titre" required placeholder="Titre">
<textarea class="input" name="description" rows="3" required></textarea>
<select class="input" name="statut"><option value="brouillon">brouillon</option><option value="en_cours">en_cours</option><option value="publie">publie</option></select>
<input class="input" type="number" name="progression" min="0" max="100" value="0">
<label><input type="checkbox" name="is_public" value="1"> Public</label>
<button class="btn-primary" type="submit">Enregistrer</button>
</form></div></div>

<?php foreach ($myProjects as $p): ?>
<div id="modal-proj-<?= (int)$p['id_projet'] ?>" class="modal" aria-hidden="true"><div class="modal-backdrop"></div><div class="modal-content">
<h2>Modifier projet</h2>
<form method="POST"><input type="hidden" name="update_project_id" value="<?= (int)$p['id_projet'] ?>">
<input class="input" name="titre" value="<?= e($p['titre']) ?>" required>
<textarea class="input" name="description" rows="3" required><?= e($p['description']) ?></textarea>
<select class="input" name="statut"><?php foreach (['brouillon','en_cours','publie','termine','archive'] as $s): ?><option value="<?= $s ?>" <?= ($p['statut']??'')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
<input class="input" type="number" name="progression" min="0" max="100" value="<?= (int)($p['progression']??0) ?>">
<label><input type="checkbox" name="is_public" value="1" <?= !empty($p['is_public'])?'checked':'' ?>> Public</label>
<button class="btn-primary" type="submit">Enregistrer</button>
</form></div></div>
<?php endforeach; ?>

<div id="modal-public-project" class="modal" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-project-content">
        <span class="modal-close-btn" onclick="closeModal('modal-public-project')">&times;</span>
        <div class="modal-project-header">
            <h2 id="publicProjectTitle"></h2>
            <div class="subtitle">Par <span id="publicProjectAuthor"></span></div>
        </div>
        <div class="modal-project-body">
            <div class="detail-row">
                <div class="detail-label">📌 Statut</div>
                <div class="detail-value"><span id="publicProjectStatut" class="badge-statut"></span></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">📊 Progression</div>
                <div class="detail-value">
                    <span id="publicProjectProgress">0%</span>
                    <div class="progression-bar"><div id="publicProjectProgressFill" class="fill" style="width:0%;"></div></div>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">👤 Auteur</div>
                <div class="detail-value"><span id="publicProjectAuthorEmail"></span></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">📅 Créé le</div>
                <div class="detail-value"><span id="publicProjectDate"></span></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <div class="detail-label">📝 Description</div>
                <div class="detail-value">
                    <div class="description-box" id="publicProjectDescription"></div>
                </div>
            </div>
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee; display: flex; justify-content: flex-end;">
                <button class="btn-secondary" onclick="closeModal('modal-public-project')">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewPublicProject(project) {
    document.getElementById('publicProjectTitle').textContent = project.titre || 'Sans titre';
    document.getElementById('publicProjectAuthor').textContent = project.pro_pseudo || 'Inconnu';
    document.getElementById('publicProjectAuthorEmail').textContent = project.pro_email || 'Email non renseigné';
    document.getElementById('publicProjectDate').textContent = project.created_at || 'Date inconnue';
    document.getElementById('publicProjectDescription').textContent = project.description || 'Aucune description.';
    
    const progress = project.progression || 0;
    document.getElementById('publicProjectProgress').textContent = progress + '%';
    document.getElementById('publicProjectProgressFill').style.width = progress + '%';
    
    const statut = project.statut || 'brouillon';
    const statutBadge = document.getElementById('publicProjectStatut');
    statutBadge.textContent = statut;
    statutBadge.className = 'badge-statut badge-' + statut;
    
    openModal('modal-public-project');
}
</script>
<?php  ?>
</body>
</html>