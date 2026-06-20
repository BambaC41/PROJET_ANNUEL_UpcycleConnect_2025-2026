package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

func ChatRouter(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/chat/")
	parts := strings.Split(strings.Trim(path, "/"), "/")

	if len(parts) == 0 || parts[0] == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	switch parts[0] {
	case "conversations":
		chatConversationsHandler(w, r)
		return
	case "unread":
		chatUnreadHandler(w, r)
		return
	case "messages":
		if len(parts) == 2 {
			chatMessagesHandler(w, r, parts[1])
			return
		}
		if len(parts) == 3 && parts[2] == "mark-read" {
			chatMarkReadHandler(w, r, parts[1])
			return
		}
	}
	http.Error(w, "Invalid path", http.StatusBadRequest)
}

func chatConversationsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	convs, err := db.GetConversations(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, convs)
}

func chatUnreadHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	count, err := db.GetUnreadCount(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, map[string]int{"unread": count})
}

func chatMessagesHandler(w http.ResponseWriter, r *http.Request, convIDStr string) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	convID, err := strconv.Atoi(convIDStr)
	if err != nil {
		http.Error(w, "Invalid conversation ID", http.StatusBadRequest)
		return
	}

	var count int
	_ = db.DB.QueryRow(`
		SELECT COUNT(*) FROM conversation
		WHERE id_conversation = ? AND (user1_id = ? OR user2_id = ?)
	`, convID, claims.UserID, claims.UserID).Scan(&count)
	if count == 0 {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return
	}

	limit := 50
	offset := 0
	if l := r.URL.Query().Get("limit"); l != "" {
		limit, _ = strconv.Atoi(l)
	}
	if o := r.URL.Query().Get("offset"); o != "" {
		offset, _ = strconv.Atoi(o)
	}

	msgs, err := db.GetMessages(convID, limit, offset)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	go db.MarkMessagesAsRead(convID, claims.UserID)

	writeJSON(w, http.StatusOK, msgs)
}

func chatMarkReadHandler(w http.ResponseWriter, r *http.Request, convIDStr string) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	convID, err := strconv.Atoi(convIDStr)
	if err != nil {
		http.Error(w, "Invalid conversation ID", http.StatusBadRequest)
		return
	}

	if err := db.MarkMessagesAsRead(convID, claims.UserID); err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"message": "marked as read"})
}

func SendMessageHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	var req model.SendMessageRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	if req.UserID == 0 || (strings.TrimSpace(req.Content) == "" && req.FilePath == "") {
		http.Error(w, "User ID and content/file required", http.StatusBadRequest)
		return
	}

	convID, err := db.GetOrCreateConversation(claims.UserID, req.UserID)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	// Créer le message
	msgID, err := db.CreateMessage(convID, claims.UserID, req.Content, req.FilePath, req.FileName)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	writeJSON(w, http.StatusCreated, map[string]any{
		"message":         "message sent",
		"id_message":      msgID,
		"conversation_id": convID,
	})
}
func SearchUsersHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	query := strings.TrimSpace(r.URL.Query().Get("q"))
	if len(query) < 1 {
		writeJSON(w, http.StatusOK, []map[string]any{})
		return
	}

	rows, err := db.DB.Query(`
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
	`, claims.UserID, "%"+query+"%", "%"+query+"%", query+"%", query+"%")
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	defer rows.Close()

	var users []map[string]any
	for rows.Next() {
		var u struct {
			ID     int    `json:"id"`
			Pseudo string `json:"pseudo"`
			Photo  string `json:"photo"`
			Role   int    `json:"role"`
			Email  string `json:"email"`
		}
		err := rows.Scan(&u.ID, &u.Pseudo, &u.Photo, &u.Role, &u.Email)
		if err != nil {
			continue
		}
		users = append(users, map[string]any{
			"id_user":   u.ID,
			"pseudo":    u.Pseudo,
			"photo_url": u.Photo,
			"id_role":   u.Role,
			"email":     u.Email,
		})
	}
	writeJSON(w, http.StatusOK, users)
}
