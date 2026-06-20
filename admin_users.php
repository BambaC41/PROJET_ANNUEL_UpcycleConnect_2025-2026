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
        } elseif ($action === 'reject_pro') {
            $res = api_ban_user($_SESSION['token'], $targetId, 'Demande professionnelle rejetée par l\'administrateur.', date('Y-m-d H:i:s', strtotime('+365 days')));
            $auditOk = (($res['status'] ?? 0) === 200);
            $_SESSION['flash_toast'] = $auditOk
                ? ['type' => 'success', 'message' => '✅ Demande professionnelle rejetée.']
                : ['type' => 'error', 'message' => 'Impossible de rejeter la demande.'];
            if ($auditOk) {
                notif_create($targetId, 'compte', 'Demande professionnelle rejetée', 'Votre demande de compte professionnel a été rejetée. Contactez l\'administration.');
            }
        } elseif ($action === 'ban') {
            if ($targetId === $selfId) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Vous ne pouvez pas vous bannir vous-même.'];
            } else {
                $reason = trim((string)($_POST['ban_reason'] ?? 'Bannissement admin'));
                $res = api_ban_user($_SESSION['token'], $targetId, $reason, date('Y-m-d H:i:s', strtotime('+7 days')));
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
        } elseif ($action === 'change_password') {
            $newPassword = trim((string)($_POST['new_password'] ?? ''));
            if (strlen($newPassword) < 12) {
                $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Le mot de passe doit contenir au moins 12 caractères.'];
            } else {
                $res = api_update_user_password($_SESSION['token'], $targetId, $newPassword);
                $auditOk = (($res['status'] ?? 0) === 200);
                if ($auditOk) {
                    notif_create($targetId, 'compte', 'Mot de passe modifié', 'Votre mot de passe a été modifié par un administrateur.');
                    $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '✅ Mot de passe modifié.'];
                    db_safe_exec(static function (PDO $pdo) use ($selfId, $targetId): void {
                        $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "utilisateur", ?, ?, NOW())');
                        $audit->execute([$selfId, 'CHANGE_PASSWORD', $targetId, 'Modification mot de passe']);
                    }, null);
                } else {
                    $errorMsg = '';
                    if (!empty($res['error'])) {
                        $errorMsg = $res['error'];
                    } elseif (!empty($res['raw'])) {
                        $errorMsg = strip_tags($res['raw']);
                    }
                    // Personnalisation du message
                    if (strpos($errorMsg, 'Password must contain') !== false || strpos($errorMsg, '12 chars') !== false) {
                        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Le mot de passe doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.'];
                    } elseif (strpos($errorMsg, 'Invalid role_id') !== false) {
                        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ Erreur de mise à jour du rôle. Contactez l\'administrateur technique.'];
                    } else {
                        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => '❌ ' . ($errorMsg ?: 'Échec de la modification du mot de passe.')];
                    }
                }
            }
        }

        if ($auditOk && $action !== 'change_role' && $action !== 'change_password') {
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

// Récupération des utilisateurs avec extraction correcte des données
$usersResponse = api_get_users($_SESSION['token']);
$users = is_array($usersResponse['data'] ?? null) ? $usersResponse['data'] : [];

$pendingProsRes = api_get_pending_pros($_SESSION['token']);
$pendingPros = (($pendingProsRes['status'] ?? 0) === 200 && is_array($pendingProsRes['data'] ?? null)) ? $pendingProsRes['data'] : [];

$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$roleFilter = (int)($_GET['role'] ?? 0);
$approvedFilter = trim((string)($_GET['approved'] ?? 'all'));
$bannedFilter = trim((string)($_GET['banned'] ?? 'all'));

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
    <style>
        .card-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }
        .section-header {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }
        .table td {
            padding: 10px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .table tbody tr {
            transition: background 0.15s;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            color: #555;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }
        .badge-ok { background: #e8f5e9; color: #2e7d32; }
        .badge-warn { background: #fff3e0; color: #ef6c00; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-muted { background: #f5f5f5; color: #757575; }
        .badge-role { background: #e3f2fd; color: #1565c0; }
        .badge-pro { background: #fff3e0; color: #ef6c00; }
        .badge-admin { background: #fce4ec; color: #c62828; }
        .btn-sm {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .btn-sm:hover { opacity: 0.85; transform: translateY(-1px); }
        .btn-success { background: #4caf50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-primary { background: #2196f3; color: white; }
        .btn-outline { background: transparent; border: 1px solid #ddd; color: #555; }
        .btn-outline:hover { background: #f0f0f0; }
        .actions-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .actions-cell form { margin: 0; }
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 14px;
            border-radius: 30px;
            border: 1px solid #ddd;
            font-size: 13px;
            background: white;
        }
        .filter-bar input:focus, .filter-bar select:focus {
            border-color: #4caf50;
            outline: none;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; }
        .modal-user {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal-user.active { display: flex; }
        .modal-user-content {
            background: white;
            border-radius: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 0;
            position: relative;
            cursor: default;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 20px;
            cursor: pointer;
            font-size: 28px;
            color: white;
            z-index: 10;
            background: rgba(0,0,0,0.4);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover { background: rgba(0,0,0,0.7); }
        .modal-header-image {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, #1565c0, #2196f3);
            border-radius: 24px 24px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-header-image .icon-large {
            font-size: 64px;
            color: white;
        }
        .modal-body { padding: 24px; }
        .modal-body h2 { margin: 0 0 20px 0; font-size: 22px; border-bottom: 2px solid #eee; padding-bottom: 12px; }
        .modal-form-group { margin-bottom: 16px; }
        .modal-form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #333; }
        .modal-form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }
        .modal-form-group input:focus { border-color: #4caf50; outline: none; }
        .btn-modal {
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-modal:hover { opacity: 0.85; transform: translateY(-2px); }
        .btn-modal-primary { background: #2196f3; color: white; }
        .btn-modal-secondary { background: #9e9e9e; color: white; }
        .modal-actions {
            margin-top: 24px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        @media (max-width: 768px) {
            .actions-cell { flex-direction: column; align-items: stretch; }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>

    <div class="card-section">
        <div class="section-header">
            <span>👔 Professionnels en attente de validation</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Photo</th><th>ID</th><th>Email</th><th>Nom</th><th>Créé le</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($pendingPros)): ?>
                    <tr><td colspan="6" style="text-align:center;">✅ Aucun professionnel en attente.</td></tr>
                <?php else: foreach ($pendingPros as $pro): ?>
                    <tr>
                        <td>
                            <?php if (!empty($pro['photo_profil'])): ?>
                                <img src="<?= e(vc_media_url($pro['photo_profil'])) ?>" alt="Photo" class="avatar">
                            <?php else: ?>
                                <div class="avatar">👤</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($pro['id_user'] ?? '—') ?></td>
                        <td><?= e($pro['email'] ?? '—') ?></td>
                        <td><?= e(trim(($pro['prenom'] ?? '') . ' ' . ($pro['nom'] ?? ''))) ?: '—' ?></td>
                        <td><?= formatDateFr($pro['created_at'] ?? '') ?></td>
                        <td>
                            <div class="actions-cell">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= e($pro['id_user'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="approve_pro">
                                    <button class="btn-sm btn-success" type="submit">✅ Approuver</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Refuser cette demande professionnelle ? L\'utilisateur sera banni.')">
                                    <input type="hidden" name="user_id" value="<?= e($pro['id_user'] ?? 0) ?>">
                                    <input type="hidden" name="action" value="reject_pro">
                                    <button class="btn-sm btn-warning" type="submit">❌ Refuser</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-section">
        <div class="section-header">
            <span>👥 Liste complète des utilisateurs</span>
        </div>
        <div class="filter-bar">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 12px; width: 100%; align-items: center;">
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Email ou pseudo" style="flex: 2; min-width: 150px;">
                <select name="role">
                    <option value="0">Tous rôles</option>
                    <?php foreach($roleMap as $rid=>$rname): ?>
                        <option value="<?= $rid ?>" <?= $roleFilter===$rid?'selected':'' ?>><?= e($rname) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="approved">
                    <option value="all">Approuvé: tous</option>
                    <option value="1" <?= $approvedFilter==='1'?'selected':'' ?>>Oui</option>
                    <option value="0" <?= $approvedFilter==='0'?'selected':'' ?>>Non</option>
                </select>
                <select name="banned">
                    <option value="all">Banni: tous</option>
                    <option value="1" <?= $bannedFilter==='1'?'selected':'' ?>>Oui</option>
                    <option value="0" <?= $bannedFilter==='0'?'selected':'' ?>>Non</option>
                </select>
                <button class="btn-sm btn-outline" type="submit" style="padding: 8px 20px;">Filtrer</button>
                <a href="admin_users.php" class="btn-sm btn-outline" style="padding: 8px 20px; text-decoration: none;">Reset</a>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th><th>ID</th><th>Email</th><th>Pseudo</th><th>Rôle</th><th>Approuvé</th><th>Banni</th><th>Créé le</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $uid = (int)($u['id_user'] ?? 0);
                    $isSelf = $uid === (int)($_SESSION['user_id'] ?? 0);
                    $roleName = $roleMap[(int)($u['id_role'] ?? 0)] ?? '—';
                    $roleClass = match((int)($u['id_role'] ?? 0)) {
                        1 => 'badge-admin',
                        3 => 'badge-pro',
                        default => 'badge-role'
                    };
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($u['photo_profil'])): ?>
                                <img src="<?= e(vc_media_url($u['photo_profil'])) ?>" alt="Photo" class="avatar">
                            <?php else: ?>
                                <div class="avatar">👤</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($uid) ?></td>
                        <td><?= e($u['email'] ?? '—') ?></td>
                        <td><?= e($u['pseudo'] ?? '—') ?></td>
                        <td><span class="badge-status <?= $roleClass ?>"><?= e($roleName) ?></span></td>
                        <td><?= !empty($u['is_approved']) ? '<span class="badge-status badge-ok">✅ Oui</span>' : '<span class="badge-status badge-warn">⏳ Non</span>' ?></td>
                        <td><?= !empty($u['is_banned']) ? '<span class="badge-status badge-danger">🚫 Oui</span>' : '<span class="badge-status badge-ok">✅ Non</span>' ?></td>
                        <td><?= formatDateFr($u['created_at'] ?? '') ?></td>
                        <td>
                            <div class="actions-cell">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <select name="new_role" style="padding:2px 6px; font-size:11px; border-radius:4px; border:1px solid #ddd;">
                                        <?php foreach ($roleMap as $rid => $rname): ?>
                                            <option value="<?= $rid ?>" <?= (int)($u['id_role'] ?? 0) === $rid ? 'selected' : '' ?>><?= e($rname) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn-sm btn-outline" name="action" value="change_role" type="submit">🔄</button>
                                </form>
                                <?php if (!$isSelf): ?>
                                    <?php if (!empty($u['is_banned'])): ?>
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="unban">
                                            <button class="btn-sm btn-success" type="submit">🔓 Débannir</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" onsubmit="return confirm('Bannir cet utilisateur ?')">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="action" value="ban">
                                            <input type="hidden" name="ban_reason" value="Bannissement admin">
                                            <button class="btn-sm btn-danger" type="submit">🚫 Bannir</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ((int)($u['id_role'] ?? 0) === 3 && empty($u['is_approved'])): ?>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="action" value="approve_pro">
                                        <button class="btn-sm btn-success" type="submit">✅ Approuver</button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn-sm btn-primary" onclick="openPasswordModal(<?= $uid ?>)">🔑 MDP</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal pour changer le mot de passe -->
<div id="passwordModal" class="modal-user" onclick="closePasswordModal()">
    <div class="modal-user-content" onclick="event.stopPropagation()">
        <span class="modal-close" onclick="closePasswordModal()">&times;</span>
        <div class="modal-header-image">
            <div class="icon-large">🔑</div>
        </div>
        <div class="modal-body">
            <h2>Changer le mot de passe</h2>
            <form method="POST" id="passwordForm" action="">
                <input type="hidden" name="user_id" id="passwordUserId">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-form-group">
                    <label>Nouveau mot de passe (min 12 caractères)</label>
                    <input type="password" name="new_password" id="newPassword" minlength="12" required placeholder="••••••••••••">
                    <p style="font-size:11px; color:#999; margin-top:4px;">Doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.</p>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-modal-primary">🔑 Changer</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closePasswordModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPasswordModal(userId) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('newPassword').value = '';
    document.getElementById('passwordModal').classList.add('active');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePasswordModal();
    }
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const pwd = document.getElementById('newPassword').value;
    if (pwd.length < 12) {
        e.preventDefault();
        alert('Le mot de passe doit contenir au moins 12 caractères.');
    }
});
</script>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>