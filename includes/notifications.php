<?php
require_once __DIR__ . '/functions/local_db.php';
require_once __DIR__ . '/functions/onesignal.php';

function notif_create(int $userId, string $type, string $title, string $content): bool
{
    $result = (bool)db_safe_exec(function (PDO $pdo) use ($userId, $type, $title, $content) {
        $sql = 'INSERT INTO notification (id_user, type, titre, contenu, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$userId, $type, $title, $content]);
    }, false);
    
    if ($result) {
        $role = getUserRoleById($userId);
        
        $shouldSend = false;
        
        switch ($type) {
            case 'annonce_validee':
            case 'depot_valide':
            case 'code_acces':
            case 'paiement_recu':
                $shouldSend = ($role === 3);
                break;
                
            case 'evenement_a_valider':
            case 'signalement_forum':
                $shouldSend = ($role === 1 || $role === 4);
                break;
                
            case 'nouvel_abonne':
                $shouldSend = ($role === 1);
                break;
                
            default:
                $shouldSend = ($role === 3 || $role === 1);
                break;
        }
        
        if ($shouldSend) {
            envoyerNotificationOneSignal($userId, $title, $content, ['type' => $type]);
        }
    }
    
    return $result;
}

function notif_unread_count(int $userId): int
{
    return (int)db_safe_exec(function (PDO $pdo) use ($userId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notification WHERE id_user = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }, 0);
}

function notif_list(int $userId, int $limit = 50, int $offset = 0): array
{
    return (array)db_safe_exec(function (PDO $pdo) use ($userId, $limit, $offset) {
        $stmt = $pdo->prepare('SELECT id_notification, type, titre, contenu, is_read, created_at FROM notification WHERE id_user = ? ORDER BY id_notification DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }, []);
}

function notif_mark_read(int $userId, int $notificationId): bool
{
    return (bool)db_safe_exec(function (PDO $pdo) use ($userId, $notificationId) {
        $stmt = $pdo->prepare('UPDATE notification SET is_read = 1 WHERE id_notification = ? AND id_user = ?');
        return $stmt->execute([$notificationId, $userId]);
    }, false);
}

function notif_mark_all_read(int $userId): bool
{
    return (bool)db_safe_exec(function (PDO $pdo) use ($userId) {
        $stmt = $pdo->prepare('UPDATE notification SET is_read = 1 WHERE id_user = ?');
        return $stmt->execute([$userId]);
    }, false);
}

function notif_notify_roles(array $roleIds, string $type, string $title, string $content): void
{
    $roleIds = array_values(array_filter(array_map('intval', $roleIds), static fn($v) => $v > 0));
    if ($roleIds === []) {
        return;
    }
    db_safe_exec(function (PDO $pdo) use ($roleIds, $type, $title, $content) {
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = 'SELECT id_user, id_role FROM utilisateur WHERE id_role IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roleIds);
        $ins = $pdo->prepare('INSERT INTO notification (id_user, type, titre, contenu, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ins->execute([(int)$row['id_user'], $type, $title, $content]);
            
            if ($row['id_role'] === 3 || $row['id_role'] === 1) {
                envoyerNotificationOneSignal($row['id_user'], $title, $content, ['type' => $type]);
            }
        }
    }, null);
}

function getUserRoleById(int $userId): ?int
{
    return (int)db_safe_exec(function (PDO $pdo) use ($userId) {
        $stmt = $pdo->prepare('SELECT id_role FROM utilisateur WHERE id_user = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int)$result : null;
    }, null);
}
?>
