package db

import (
	"api/model"
	"database/sql"
	"errors"
)

func GetOrCreateConversation(user1ID, user2ID int) (int, error) {
	if user1ID == user2ID {
		return 0, errors.New("cannot chat with yourself")
	}

	if user1ID > user2ID {
		user1ID, user2ID = user2ID, user1ID
	}

	var id int
	err := DB.QueryRow(`
		SELECT id_conversation FROM conversation
		WHERE user1_id = ? AND user2_id = ?
	`, user1ID, user2ID).Scan(&id)

	if err == nil {
		return id, nil
	}
	if err != sql.ErrNoRows {
		return 0, err
	}

	res, err := DB.Exec(`
		INSERT INTO conversation (user1_id, user2_id) VALUES (?, ?)
	`, user1ID, user2ID)
	if err != nil {
		return 0, err
	}
	id64, err := res.LastInsertId()
	return int(id64), err
}

func CreateMessage(convID, senderID int, content, filePath, fileName string) (int, error) {
	// Vérifier que la conversation existe et que l'utilisateur en fait partie
	var count int
	err := DB.QueryRow(`
		SELECT COUNT(*) FROM conversation 
		WHERE id_conversation = ? AND (user1_id = ? OR user2_id = ?)
	`, convID, senderID, senderID).Scan(&count)
	if err != nil {
		return 0, err
	}
	if count == 0 {
		return 0, errors.New("conversation not found or user not part of it")
	}

	res, err := DB.Exec(`
		INSERT INTO message (conversation_id, sender_id, content, file_path, file_name)
		VALUES (?, ?, ?, ?, ?)
	`, convID, senderID, nullIfEmpty(content), nullIfEmpty(filePath), nullIfEmpty(fileName))
	if err != nil {
		return 0, err
	}
	id, _ := res.LastInsertId()

	_, _ = DB.Exec(`UPDATE conversation SET updated_at = NOW() WHERE id_conversation = ?`, convID)

	var u1, u2 int
	_ = DB.QueryRow(`SELECT user1_id, user2_id FROM conversation WHERE id_conversation = ?`, convID).Scan(&u1, &u2)
	otherUserID := u1
	if otherUserID == senderID {
		otherUserID = u2
	}

	_, _ = DB.Exec(`
		INSERT INTO unread_messages (user_id, conversation_id, last_read_at)
		VALUES (?, ?, NOW())
		ON DUPLICATE KEY UPDATE last_read_at = NOW()
	`, otherUserID, convID)

	return int(id), nil
}

func GetConversations(userID int) ([]model.Conversation, error) {
	rows, err := DB.Query(`
		SELECT
			c.id_conversation,
			c.user1_id,
			c.user2_id,
			COALESCE(u1.pseudo, '') as user1_pseudo,
			COALESCE(u2.pseudo, '') as user2_pseudo,
			COALESCE(u1.photo_profil, '') as user1_photo,
			COALESCE(u2.photo_profil, '') as user2_photo,
			COALESCE((
				SELECT m.content FROM message m 
				WHERE m.conversation_id = c.id_conversation 
				ORDER BY m.created_at DESC LIMIT 1
			), '') as last_message,
			COALESCE((
				SELECT DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i:%s') FROM message m 
				WHERE m.conversation_id = c.id_conversation 
				ORDER BY m.created_at DESC LIMIT 1
			), '') as last_message_at,
			COALESCE((
				SELECT COUNT(*) FROM message m 
				WHERE m.conversation_id = c.id_conversation 
				AND m.sender_id != ? 
				AND m.created_at > COALESCE((
					SELECT last_read_at FROM unread_messages um 
					WHERE um.user_id = ? AND um.conversation_id = c.id_conversation
				), '1970-01-01')
			), 0) as unread_count,
			DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
			DATE_FORMAT(c.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
		FROM conversation c
		LEFT JOIN utilisateur u1 ON u1.id_user = c.user1_id
		LEFT JOIN utilisateur u2 ON u2.id_user = c.user2_id
		WHERE c.user1_id = ? OR c.user2_id = ?
		ORDER BY c.updated_at DESC
	`, userID, userID, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var convs []model.Conversation
	for rows.Next() {
		var c model.Conversation
		err := rows.Scan(
			&c.ID, &c.User1ID, &c.User2ID,
			&c.User1Pseudo, &c.User2Pseudo,
			&c.User1Photo, &c.User2Photo,
			&c.LastMessage, &c.LastMessageAt,
			&c.UnreadCount,
			&c.CreatedAt, &c.UpdatedAt,
		)
		if err != nil {
			return nil, err
		}
		convs = append(convs, c)
	}
	return convs, nil
}

func GetMessages(convID int, limit, offset int) ([]model.Message, error) {
	if limit <= 0 {
		limit = 50
	}
	rows, err := DB.Query(`
		SELECT
			m.id_message,
			m.conversation_id,
			m.sender_id,
			COALESCE(u.pseudo, '') as sender_pseudo,
			COALESCE(u.photo_profil, '') as sender_photo,
			COALESCE(m.content, ''),
			COALESCE(m.file_path, ''),
			COALESCE(m.file_name, ''),
			m.is_read,
			DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i:%s') as created_at
		FROM message m
		LEFT JOIN utilisateur u ON u.id_user = m.sender_id
		WHERE m.conversation_id = ?
		ORDER BY m.created_at DESC
		LIMIT ? OFFSET ?
	`, convID, limit, offset)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var msgs []model.Message
	for rows.Next() {
		var m model.Message
		err := rows.Scan(
			&m.ID, &m.ConversationID, &m.SenderID,
			&m.SenderPseudo, &m.SenderPhoto,
			&m.Content, &m.FilePath, &m.FileName,
			&m.IsRead, &m.CreatedAt,
		)
		if err != nil {
			return nil, err
		}
		msgs = append(msgs, m)
	}
	return msgs, nil
}

func MarkMessagesAsRead(convID, userID int) error {
	_, err := DB.Exec(`
		INSERT INTO unread_messages (user_id, conversation_id, last_read_at)
		VALUES (?, ?, NOW())
		ON DUPLICATE KEY UPDATE last_read_at = NOW()
	`, userID, convID)
	return err
}

func GetUnreadCount(userID int) (int, error) {
	var count int
	err := DB.QueryRow(`
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
	`, userID, userID, userID, userID).Scan(&count)
	if err != nil {
		return 0, err
	}
	return count, nil
}
