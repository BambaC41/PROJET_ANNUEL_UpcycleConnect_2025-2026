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

$projects = (array)db_safe_exec(static function (PDO $pdo) use ($proId) {
    $st = $pdo->prepare('SELECT * FROM projet_upcycling WHERE id_pro = ? ORDER BY id_projet DESC');
    $st->execute([$proId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}, []);

$countStat = static fn(string $s) => count(array_filter($projects, static fn($p) => ($p['statut'] ?? '') === $s));
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
    <script src="scripts/modal.js" defer></script>
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<?php include 'includes/flash_toast.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1>Projets upcycling</h1>
        <div class="admin-kpi-grid">
            <div class="admin-card"><h3>Brouillon</h3><p><?= $countStat('brouillon') ?></p></div>
            <div class="admin-card"><h3>En cours</h3><p><?= $countStat('en_cours') ?></p></div>
            <div class="admin-card"><h3>Publiés</h3><p><?= $countStat('publie') ?></p></div>
            <div class="admin-card"><h3>Terminés</h3><p><?= $countStat('termine') ?></p></div>
        </div>
        <button class="btn-primary" type="button" onclick="openModal('modal-new-project')">+ Nouveau projet</button>
        <?php if (empty($projects)): ?>
            <?php render_empty_state('Aucun projet', 'Créez votre premier projet upcycling.', '+ Créer', '#'); ?>
        <?php else: ?>
        <div class="pro-grid" style="margin-top:18px;">
            <?php foreach ($projects as $p): ?>
                <article class="pro-card">
                    <h2><?= e($p['titre'] ?? '') ?></h2>
                    <p class="muted"><?= e($p['statut'] ?? '') ?> · <?= (int)($p['progression'] ?? 0) ?>%</p>
                    <div style="background:#e2e8f0;border-radius:6px;height:8px;margin:8px 0;"><div style="width:<?= (int)($p['progression']??0) ?>%;background:#16a34a;height:8px;border-radius:6px;"></div></div>
                    <p><?= e(mb_substr((string)($p['description'] ?? ''), 0, 120)) ?></p>
                    <button class="btn-outline" type="button" onclick="openModal('modal-proj-<?= (int)$p['id_projet'] ?>')">Modifier</button>
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
<?php foreach ($projects as $p): ?>
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
<?php  ?>
</body>
</html>
