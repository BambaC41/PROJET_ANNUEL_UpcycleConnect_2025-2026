<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

$roleMap = [
    1 => 'Admin',
    2 => 'Particulier',
    3 => 'Professionnel',
    4 => 'Salarié',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    $action = (string)$_POST['action'];
    $selfId = (int)($_SESSION['user_id'] ?? 0);

    if ($targetId > 0) {
        $detail = api_get_user_by_id($targetId, $_SESSION['token']);
        $targetRow = (($detail['status'] ?? 0) === 200 && is_array($detail['data'] ?? null)) ? $detail['data'] : [];
        $oldRole = (int)($targetRow['id_role'] ?? 0);
        $auditOk = false;

        if ($action === 'approve_pro') {
            $res = api_approve_pro($_SESSION['token'], $targetId);
            $auditOk = (($res['status'] ?? 0) === 200);
            $_SESSION['flash_toast'] = $auditOk
                ? ['type' => 'success', 'message' => '✅ Compte pro approuvé (#' . $targetId . ').']
                : ['type' => 'error', 'message' => 'Impossible d\'approuver le compte pro.'];
            if ($auditOk) {
                notif_create($targetId, 'compte', 'Compte professionnel approuvé', 'Votre espace professionnel est accessible.');
            }
        } elseif ($action === 'ban') {
            if ($targetId === $selfId) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Vous ne pouvez pas vous bannir vous-même.'];
            } else {
                $res = api_ban_user($_SESSION['token'], $targetId, trim((string)($_POST['ban_reason'] ?? 'Bannissement admin')), date('Y-m-d H:i:s', strtotime('+7 days')));
                $auditOk = (($res['status'] ?? 0) === 200);
                $_SESSION['flash_toast'] = $auditOk
                    ? ['type' => 'success', 'message' => '✅ Utilisateur banni.']
                    : ['type' => 'error', 'message' => 'Bannissement impossible.'];
                if ($auditOk) {
                    notif_create($targetId, 'compte', 'Compte suspendu', 'Votre compte a été suspendu par l\'administration.');
                }
            }
        } elseif ($action === 'unban') {
            $res = api_unban_user($_SESSION['token'], $targetId);
            $auditOk = (($res['status'] ?? 0) === 200);
            $_SESSION['flash_toast'] = $auditOk
                ? ['type' => 'success', 'message' => '✅ Utilisateur débanni.']
                : ['type' => 'error', 'message' => 'Débannissement impossible.'];
            if ($auditOk) {
                notif_create($targetId, 'compte', 'Compte réactivé', 'Votre compte n\'est plus suspendu.');
            }
        } elseif ($action === 'change_role') {
            $newRole = (int)($_POST['new_role'] ?? 0);
            if ($targetId === $selfId && $oldRole === 1 && $newRole !== 1) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Vous ne pouvez pas vous retirer les droits administrateur.'];
            } else {
                $adminCount = (int)db_safe_exec(static fn(PDO $pdo) => (int)$pdo->query('SELECT COUNT(*) FROM utilisateur WHERE id_role = 1')->fetchColumn(), 0);
                if ($oldRole === 1 && $newRole !== 1 && $adminCount <= 1) {
                    $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Impossible de modifier le dernier administrateur.'];
                } else {
                    $res = api_update_user_role($_SESSION['token'], $targetId, $newRole);
                    $auditOk = (($res['status'] ?? 0) === 200);
                    if ($auditOk) {
                        $label = $roleMap[$newRole] ?? (string)$newRole;
                        notif_create($targetId, 'compte', 'Rôle modifié', 'Nouveau rôle : ' . $label . '.');
                        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '✅ Rôle mis à jour.'];
                        db_safe_exec(static function (PDO $pdo) use ($selfId, $targetId, $newRole): void {
                            $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "utilisateur", ?, ?, NOW())');
                            $audit->execute([$selfId, 'CHANGE_ROLE', $targetId, 'Nouveau role ' . $newRole]);
                        }, null);
                    } else {
                        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Changement de rôle impossible.'];
                    }
                }
            }
        }

        if ($auditOk && $action !== 'change_role') {
            db_safe_exec(static function (PDO $pdo) use ($targetId, $action, $selfId): void {
                $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "utilisateur", ?, ?, NOW())');
                $audit->execute([$selfId, strtoupper($action), $targetId, 'Action admin_users']);
            }, null);
        }
    }
    header('Location: admin_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_pro_id'])) {
    $approveId = (int)$_POST['approve_pro_id'];
    $res = api_approve_pro($_SESSION['token'], $approveId);
    $ok = (($res['status'] ?? 0) === 200);
    $_SESSION['flash_toast'] = $ok
        ? ['type' => 'success', 'message' => '✅ Compte pro approuvé (#' . $approveId . ').']
        : ['type' => 'error', 'message' => 'Impossible d\'approuver le compte pro (#' . $approveId . ').'];
    if ($ok) {
        notif_create($approveId, 'compte', 'Compte professionnel approuvé', 'Votre espace professionnel est accessible.');
        db_safe_exec(static function (PDO $pdo) use ($approveId): void {
            $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "utilisateur", ?, ?, NOW())');
            $audit->execute([(int)($_SESSION['user_id'] ?? 0), 'APPROVE_PRO', $approveId, 'Validation compte pro']);
        }, null);
    }
    header('Location: admin_users.php');
    exit;
}

$users = api_get_users($_SESSION['token']);
$pendingProsRes = api_get_pending_pros($_SESSION['token']);
$pendingPros = (($pendingProsRes['status'] ?? 0) === 200 && is_array($pendingProsRes['data'] ?? null)) ? $pendingProsRes['data'] : [];
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$roleFilter = (int)($_GET['role'] ?? 0);
$approvedFilter = trim((string)($_GET['approved'] ?? 'all'));
$bannedFilter = trim((string)($_GET['banned'] ?? 'all'));

// Filtrer les utilisateurs
$users = array_values(array_filter($users, function($u) use ($q, $roleFilter, $approvedFilter, $bannedFilter) {
    if ($roleFilter > 0 && (int)($u['id_role'] ?? 0) !== $roleFilter) return false;
    if ($approvedFilter !== 'all' && (int)($u['is_approved'] ?? 0) !== ($approvedFilter === '1' ? 1 : 0)) return false;
    if ($bannedFilter !== 'all' && (int)($u['is_banned'] ?? 0) !== ($bannedFilter === '1' ? 1 : 0)) return false;
    $needle = mb_strtolower((string)($u['email'] ?? '') . ' ' . (string)($u['pseudo'] ?? ''));
    if ($q !== '' && strpos($needle, $q) === false) return false;
    return true;
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestion utilisateurs</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <!-- Professionnels en attente -->
    <section class="pro-card">
        <h2>👔 Professionnels en attente de validation</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Photo</th><th>ID</th><th>Email</th><th>Nom</th><th>Créé le</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($pendingPros)): ?>
                    <tr><td colspan="6" style="text-align:center;">✅ Aucun professionnel en attente.</td></tr>
                <?php else: foreach ($pendingPros as $pro): ?>
                    <tr>
                        <td style="text-align:center;">
                            <?php if (!empty($pro['photo_profil'])): ?>
                                <img src="<?= e(vc_media_url($pro['photo_profil'])) ?>" alt="Photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>👤<?php endif; ?>
                        </td>
                        <td><?= e($pro['id_user'] ?? '—') ?></td>
                        <td><?= e($pro['email'] ?? '—') ?></td>
                        <td><?= e(trim(($pro['prenom'] ?? '') . ' ' . ($pro['nom'] ?? ''))) ?: '—' ?></td>
                        <td><?= formatDateFr($pro['created_at'] ?? '') ?></td>
                        <td class="row-actions">
                            <form method="POST">
                                <input type="hidden" name="approve_pro_id" value="<?= e($pro['id_user'] ?? 0) ?>">
                                <button type="submit" class="btn-success">✅ Valider le PRO</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Liste complète des utilisateurs -->
    <section class="pro-card">
        <h2>👥 Liste complète des utilisateurs</h2>
        <form method="GET" class="row-actions" style="margin-bottom:20px; flex-wrap:wrap;">
            <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Email ou pseudo" style="width:200px;">
            <select class="input" name="role" style="width:150px;">
                <option value="0">Tous rôles</option>
                <?php foreach($roleMap as $rid=>$rname): ?>
                    <option value="<?= $rid ?>" <?= $roleFilter===$rid?'selected':'' ?>><?= e($rname) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" name="approved" style="width:130px;">
                <option value="all">Approuvé: tous</option>
                <option value="1" <?= $approvedFilter==='1'?'selected':'' ?>>Oui</option>
                <option value="0" <?= $approvedFilter==='0'?'selected':'' ?>>Non</option>
            </select>
            <select class="input" name="banned" style="width:130px;">
                <option value="all">Banni: tous</option>
                <option value="1" <?= $bannedFilter==='1'?'selected':'' ?>>Oui</option>
                <option value="0" <?= $bannedFilter==='0'?'selected':'' ?>>Non</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Pseudo</th>
                        <th>Rôle</th>
                        <th>Approuvé</th>
                        <th>Banni</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $uid = (int)($u['id_user'] ?? 0);
                    $isSelf = $uid === (int)($_SESSION['user_id'] ?? 0);
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <?php if (!empty($u['photo_profil'])): ?>
                                <img src="<?= e(vc_media_url($u['photo_profil'])) ?>" alt="Photo" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>👤<?php endif; ?>
                        </td>
                        <td><?= e($uid) ?></td>
                        <td><?= e($u['email'] ?? '—') ?></td>
                        <td><?= e($u['pseudo'] ?? '—') ?></td>
                        <td><?= e($roleMap[(int)($u['id_role'] ?? 0)] ?? '—') ?></td>
                        <td><?= !empty($u['is_approved']) ? '<span class="status-badge status-ok">✅ Oui</span>' : '<span class="status-badge status-warn">⏳ Non</span>' ?></td>
                        <td><?= !empty($u['is_banned']) ? '<span class="status-badge status-danger">🚫 Oui</span>' : '<span class="status-badge status-ok">✅ Non</span>' ?></td>
                        <td><?= formatDateFr($u['created_at'] ?? '') ?></td>
                        <td class="row-actions">
                            <form method="POST" class="row-actions" style="gap:6px; flex-wrap:wrap;">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <select class="input" name="new_role" style="width:120px;">
                                    <?php foreach ($roleMap as $rid => $rname): ?>
                                        <option value="<?= $rid ?>" <?= (int)($u['id_role'] ?? 0) === $rid ? 'selected' : '' ?>><?= e($rname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-outline" name="action" value="change_role" type="submit">🔄 Changer</button>
                                <?php if (!$isSelf): ?>
                                    <?php if (!empty($u['is_banned'])): ?>
                                        <button class="btn-outline" name="action" value="unban" type="submit">🔓 Débannir</button>
                                    <?php else: ?>
                                        <button class="btn-danger" name="action" value="ban" type="submit">🚫 Bannir</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ((int)($u['id_role'] ?? 0) === 3 && empty($u['is_approved'])): ?>
                                    <button class="btn-success" name="action" value="approve_pro" type="submit">✅ Approuver</button>
                                <?php endif; ?>
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