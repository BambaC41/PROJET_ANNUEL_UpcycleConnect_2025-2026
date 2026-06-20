package model

type Conversation struct {
	ID            int    `json:"id_conversation"`
	User1ID       int    `json:"user1_id"`
	User2ID       int    `json:"user2_id"`
	User1Pseudo   string `json:"user1_pseudo,omitempty"`
	User2Pseudo   string `json:"user2_pseudo,omitempty"`
	User1Photo    string `json:"user1_photo,omitempty"`
	User2Photo    string `json:"user2_photo,omitempty"`
	LastMessage   string `json:"last_message,omitempty"`
	LastMessageAt string `json:"last_message_at,omitempty"`
	UnreadCount   int    `json:"unread_count"`
	CreatedAt     string `json:"created_at"`
	UpdatedAt     string `json:"updated_at"`
}

type Message struct {
	ID             int    `json:"id_message"`
	ConversationID int    `json:"conversation_id"`
	SenderID       int    `json:"sender_id"`
	SenderPseudo   string `json:"sender_pseudo,omitempty"`
	SenderPhoto    string `json:"sender_photo,omitempty"`
	Content        string `json:"content"`
	FilePath       string `json:"file_path,omitempty"`
	FileName       string `json:"file_name,omitempty"`
	IsRead         bool   `json:"is_read"`
	CreatedAt      string `json:"created_at"`
}

type SendMessageRequest struct {
	UserID   int    `json:"user_id"`
	Content  string `json:"content"`
	FilePath string `json:"file_path,omitempty"`
	FileName string `json:"file_name,omitempty"`
}
