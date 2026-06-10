package model

type ForumCategory struct {
	ID          int    `json:"id"`
	Name        string `json:"name"`
	Slug        string `json:"slug"`
	Description string `json:"description"`
	SortOrder   int    `json:"sort_order"`
	IsActive    bool   `json:"is_active"`
	CreatedAt   string `json:"created_at"`
	UpdatedAt   string `json:"updated_at"`
}

type ForumTopic struct {
	ID          int    `json:"id"`
	CategoryID  int    `json:"category_id"`
	AuthorID    int    `json:"author_id"`
	Title       string `json:"title"`
	Slug        string `json:"slug"`
	Status      string `json:"status"`
	IsPinned    bool   `json:"is_pinned"`
	IsLocked    bool   `json:"is_locked"`
	IsHidden    bool   `json:"is_hidden"`
	ViewsCount  int    `json:"views_count"`
	PostsCount  int    `json:"posts_count"`
	LastPostAt  string `json:"last_post_at,omitempty"`
	CreatedAt   string `json:"created_at"`
	UpdatedAt   string `json:"updated_at"`
	AuthorPseudo string `json:"author_pseudo,omitempty"`
	CategoryName string `json:"category_name,omitempty"`
}

type ForumPost struct {
	ID           int    `json:"id"`
	TopicID      int    `json:"topic_id"`
	AuthorID     int    `json:"author_id"`
	Content      string `json:"content"`
	IsHidden     bool   `json:"is_hidden"`
	HiddenReason string `json:"hidden_reason,omitempty"`
	HiddenBy     int    `json:"hidden_by,omitempty"`
	HiddenAt     string `json:"hidden_at,omitempty"`
	CreatedAt    string `json:"created_at"`
	UpdatedAt    string `json:"updated_at"`
	AuthorPseudo string `json:"author_pseudo,omitempty"`
}

type ForumReport struct {
	ID           int    `json:"id"`
	PostID       int    `json:"post_id"`
	ReporterID   int    `json:"reporter_id"`
	Reason       string `json:"reason"`
	Details      string `json:"details"`
	Status       string `json:"status"`
	HandledBy    int    `json:"handled_by,omitempty"`
	HandledAt    string `json:"handled_at,omitempty"`
	CreatedAt    string `json:"created_at"`
	ReporterPseudo string `json:"reporter_pseudo,omitempty"`
	PostPreview  string `json:"post_preview,omitempty"`
	TopicTitle   string `json:"topic_title,omitempty"`
}

type ForumModerationLog struct {
	ID           int    `json:"id"`
	ModeratorID  int    `json:"moderator_id"`
	Action       string `json:"action"`
	TargetType   string `json:"target_type"`
	TargetID     int    `json:"target_id"`
	Reason       string `json:"reason"`
	CreatedAt    string `json:"created_at"`
	ModeratorPseudo string `json:"moderator_pseudo,omitempty"`
}

type ForumTopicFilter struct {
	CategoryID int
	Status     string
	Query      string
	AuthorID   int
	IncludeHidden bool
	Page       int
	PerPage    int
}

type ForumReportFilter struct {
	Status  string
	Page    int
	PerPage int
}
