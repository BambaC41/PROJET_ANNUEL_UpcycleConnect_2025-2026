<?php

function chat_get_or_create_conversation($userId, $targetUserId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    if ($userId == $targetUserId) {
        return null;
    }
    $user1 = min($userId, $targetUserId);
    $user2 = max($userId, $targetUserId);
    $stmt = $pdo->prepare("SELECT id_conversation FROM conversation WHERE user1_id = ? AND user2_id = ?");
    $stmt->execute([$user1, $user2]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($conv) {
        return (int)$conv['id_conversation'];
    }
    $stmt = $pdo->prepare("INSERT INTO conversation (user1_id, user2_id) VALUES (?, ?)");
    $stmt->execute([$user1, $user2]);
    return (int)$pdo->lastInsertId();
}

function chat_get_conversations($userId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $stmt = $pdo->prepare("
        SELECT 
            c.id_conversation,
            c.user1_id,
            c.user2_id,
            u1.pseudo as user1_pseudo,
            u2.pseudo as user2_pseudo,
            u1.photo_profil as user1_photo,
            u2.photo_profil as user2_photo,
            u1.id_role as user1_role,
            u2.id_role as user2_role,
            (
                SELECT m.content FROM message m 
                WHERE m.conversation_id = c.id_conversation 
                ORDER BY m.created_at DESC LIMIT 1
            ) as last_message,
            (
                SELECT DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i:%s') FROM message m 
                WHERE m.conversation_id = c.id_conversation 
                ORDER BY m.created_at DESC LIMIT 1
            ) as last_message_at,
            (
                SELECT COUNT(*) FROM message m 
                WHERE m.conversation_id = c.id_conversation 
                AND m.sender_id != ?
                AND m.created_at > COALESCE((
                    SELECT last_read_at FROM unread_messages um 
                    WHERE um.user_id = ? AND um.conversation_id = c.id_conversation
                ), '1970-01-01')
            ) as unread_count,
            DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
            DATE_FORMAT(c.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
        FROM conversation c
        LEFT JOIN utilisateur u1 ON u1.id_user = c.user1_id
        LEFT JOIN utilisateur u2 ON u2.id_user = c.user2_id
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function chat_get_messages($convId, $limit = 50) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $limit = (int)$limit;
    $stmt = $pdo->prepare("
        SELECT 
            m.id_message,
            m.conversation_id,
            m.sender_id,
            u.pseudo as sender_pseudo,
            u.photo_profil as sender_photo,
            m.content,
            m.file_path,
            m.file_name,
            m.is_read,
            DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM message m
        LEFT JOIN utilisateur u ON u.id_user = m.sender_id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->bindParam(1, $convId, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_reverse($messages);
}

function chat_get_unread_count($userId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(unread_count), 0) FROM (
            SELECT COUNT(*) as unread_count
            FROM message m
            JOIN conversation c ON c.id_conversation = m.conversation_id
            WHERE (c.user1_id = ? OR c.user2_id = ?)
            AND m.sender_id != ?
            AND m.created_at > COALESCE((
                SELECT last_read_at FROM unread_messages um 
                WHERE um.user_id = ? AND um.conversation_id = c.id_conversation
            ), '1970-01-01')
            GROUP BY c.id_conversation
        ) t
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

function chat_mark_as_read($convId, $userId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $stmt = $pdo->prepare("
        INSERT INTO unread_messages (user_id, conversation_id, last_read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");
    $stmt->execute([$userId, $convId]);
}

function chat_send_message($userId, $targetUserId, $content, $filePath = '', $fileName = '') {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    if ($userId == $targetUserId) {
        return ['error' => 'Vous ne pouvez pas vous envoyer un message à vous-même'];
    }
    if (empty($content) && empty($filePath)) {
        return ['error' => 'Message vide'];
    }
    $convId = chat_get_or_create_conversation($userId, $targetUserId);
    if (!$convId) {
        return ['error' => 'Impossible de créer la conversation'];
    }
    $stmt = $pdo->prepare("
        INSERT INTO message (conversation_id, sender_id, content, file_path, file_name)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$convId, $userId, $content, $filePath, $fileName]);
    $stmt = $pdo->prepare("UPDATE conversation SET updated_at = NOW() WHERE id_conversation = ?");
    $stmt->execute([$convId]);
    $stmt = $pdo->prepare("
        INSERT INTO unread_messages (user_id, conversation_id, last_read_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");
    $stmt->execute([$targetUserId, $convId]);
    return ['success' => true, 'conversation_id' => $convId];
}

function chat_search_users($query, $currentUserId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $stmt = $pdo->prepare("
        SELECT id_user, pseudo, photo_profil, id_role, email
        FROM utilisateur
        WHERE id_user != ?
        AND (pseudo LIKE ? OR email LIKE ?)
        AND is_banned = 0
        AND is_approved = 1
        ORDER BY 
            CASE 
                WHEN pseudo LIKE ? THEN 1
                WHEN email LIKE ? THEN 2
                ELSE 3
            END
        LIMIT 15
    ");
    $stmt->execute([$currentUserId, "%$query%", "%$query%", "$query%", "$query%"]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function chat_delete_message($messageId, $userId) {
    global $pdo;
    if (!$pdo) {
        require_once __DIR__ . '/local_db.php';
        $pdo = get_db_connection();
    }
    $stmt = $pdo->prepare("SELECT sender_id FROM message WHERE id_message = ?");
    $stmt->execute([$messageId]);
    $sender = $stmt->fetchColumn();
    if ((int)$sender !== (int)$userId) {
        return ['error' => 'Vous ne pouvez supprimer que vos propres messages'];
    }
    $stmt = $pdo->prepare("DELETE FROM message WHERE id_message = ?");
    $ok = $stmt->execute([$messageId]);
    return $ok ? ['success' => true] : ['error' => 'Erreur lors de la suppression'];
}