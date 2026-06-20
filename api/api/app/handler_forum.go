package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(v)
}

func ForumRouter(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/forum/")
	path = strings.Trim(path, "/")
	parts := strings.Split(path, "/")
	if len(parts) == 0 || parts[0] == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	switch parts[0] {
	case "categories":
		if len(parts) == 1 {
			forumCategoriesHandler(w, r)
			return
		}
		if len(parts) == 2 {
			forumCategoryByIDHandler(w, r, parts[1])
			return
		}
	case "topics":
		if len(parts) == 1 {
			forumTopicsHandler(w, r)
			return
		}
		if len(parts) == 2 {
			forumTopicByIDHandler(w, r, parts[1])
			return
		}
		if len(parts) == 3 && parts[2] == "posts" {
			forumTopicPostsHandler(w, r, parts[1])
			return
		}
	case "posts":
		if len(parts) == 2 {
			if r.Method == http.MethodPost && parts[1] != "" {

			}
			forumPostByIDHandler(w, r, parts[1])
			return
		}
		if len(parts) == 3 && parts[2] == "report" {
			forumPostReportHandler(w, r, parts[1])
			return
		}
	}
	http.Error(w, "Invalid path", http.StatusBadRequest)
}

func AdminForumRouter(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/admin/forum/")
	path = strings.Trim(path, "/")
	parts := strings.Split(path, "/")
	if len(parts) == 0 || parts[0] == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	switch parts[0] {
	case "reports":
		if len(parts) == 1 {
			adminForumReportsHandler(w, r)
			return
		}
		if len(parts) == 2 {
			adminForumReportByIDHandler(w, r, parts[1])
			return
		}
	case "posts":
		if len(parts) == 3 {
			adminForumPostActionHandler(w, r, parts[1], parts[2])
			return
		}
	case "topics":
		if len(parts) == 3 {
			adminForumTopicActionHandler(w, r, parts[1], parts[2])
			return
		}
	case "moderation-logs":
		if len(parts) == 1 {
			adminForumModerationLogsHandler(w, r)
			return
		}
	}
	http.Error(w, "Invalid path", http.StatusBadRequest)
}

func forumCategoriesHandler(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		claims, ok := requireForumMember(w, r)
		if !ok {
			return
		}
		activeOnly := !isForumModerator(claims)
		items, err := db.ListForumCategories(activeOnly)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		writeJSON(w, http.StatusOK, items)
	case http.MethodPost:
		if _, ok := requireForumModerator(w, r); !ok {
			return
		}
		var c model.ForumCategory
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if strings.TrimSpace(c.Name) == "" || strings.TrimSpace(c.Slug) == "" {
			http.Error(w, "name and slug required", http.StatusBadRequest)
			return
		}
		id, err := db.CreateForumCategory(c)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		writeJSON(w, http.StatusCreated, map[string]any{"message": "category created", "id": id})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func forumCategoryByIDHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	switch r.Method {
	case http.MethodGet:
		if _, ok := requireForumMember(w, r); !ok {
			return
		}
		c, err := db.GetForumCategoryByID(id)
		if err != nil || c == nil {
			http.Error(w, "Category not found", http.StatusNotFound)
			return
		}
		writeJSON(w, http.StatusOK, c)
	case http.MethodPut:
		claims, ok := requireForumModerator(w, r)
		if !ok {
			return
		}
		var c model.ForumCategory
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if err := db.UpdateForumCategory(id, c); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "update_category", "category", id, "")
		writeJSON(w, http.StatusOK, map[string]string{"message": "category updated"})
	case http.MethodDelete:
		claims, ok := requireForumModerator(w, r)
		if !ok {
			return
		}
		if err := db.DeleteForumCategory(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "delete_category", "category", id, "")
		writeJSON(w, http.StatusOK, map[string]string{"message": "category deleted"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func forumTopicsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodPost {
		forumTopicsCreateHandler(w, r)
		return
	}
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}
	f := model.ForumTopicFilter{
		Page:          parsePositiveInt(r.URL.Query().Get("page"), 1),
		PerPage:       parsePositiveInt(r.URL.Query().Get("per_page"), 20),
		Query:         r.URL.Query().Get("q"),
		Status:        r.URL.Query().Get("status"),
		IncludeHidden: isForumModerator(claims),
	}
	if cid := r.URL.Query().Get("category_id"); cid != "" {
		f.CategoryID, _ = strconv.Atoi(cid)
	}
	items, total, err := db.ListForumTopics(f)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": f.Page, "per_page": f.PerPage})
}

func forumTopicByIDHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}
	includeHidden := isForumModerator(claims)

	switch r.Method {
	case http.MethodGet:
		topic, err := db.GetForumTopicByID(id, includeHidden)
		if err != nil || topic == nil {
			http.Error(w, "Topic not found", http.StatusNotFound)
			return
		}
		_ = db.IncrementForumTopicViews(id)
		posts, _ := db.ListForumPosts(id, includeHidden)
		writeJSON(w, http.StatusOK, map[string]any{"topic": topic, "posts": posts})
	case http.MethodPost:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	case http.MethodPut:
		var body struct {
			Title      string `json:"title"`
			CategoryID int    `json:"category_id"`
			Status     string `json:"status"`
			IsPinned   bool   `json:"is_pinned"`
		}
		if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		existing, err := db.GetForumTopicByID(id, true)
		if err != nil || existing == nil {
			http.Error(w, "Topic not found", http.StatusNotFound)
			return
		}
		if !isForumModerator(claims) {
			if existing.AuthorID != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			if existing.IsHidden || existing.Status == "hidden" {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			existing.Title = body.Title
			if body.CategoryID > 0 {
				existing.CategoryID = body.CategoryID
			}
		} else {
			if body.Title != "" {
				existing.Title = body.Title
			}
			if body.CategoryID > 0 {
				existing.CategoryID = body.CategoryID
			}
			if body.Status != "" {
				existing.Status = body.Status
			}
			existing.IsPinned = body.IsPinned
		}
		if err := db.UpdateForumTopic(id, *existing); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}
		writeJSON(w, http.StatusOK, map[string]string{"message": "topic updated"})
	case http.MethodDelete:
		existing, _ := db.GetForumTopicByID(id, true)
		if existing == nil {
			http.Error(w, "Topic not found", http.StatusNotFound)
			return
		}
		if !isForumModerator(claims) {
			if existing.AuthorID != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
		} else {
			_ = db.InsertForumModerationLog(claims.UserID, "delete_topic", "topic", id, "")
		}
		if err := db.DeleteForumTopic(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		writeJSON(w, http.StatusOK, map[string]string{"message": "topic deleted"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func forumTopicsCreateHandler(w http.ResponseWriter, r *http.Request) {
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}
	var body struct {
		CategoryID int    `json:"category_id"`
		Title      string `json:"title"`
		Content    string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if body.CategoryID <= 0 || strings.TrimSpace(body.Title) == "" || strings.TrimSpace(body.Content) == "" {
		http.Error(w, "category_id, title and content required", http.StatusBadRequest)
		return
	}
	cat, err := db.GetForumCategoryByID(body.CategoryID)
	if err != nil || cat == nil || (!cat.IsActive && !isForumModerator(claims)) {
		http.Error(w, "Invalid category", http.StatusBadRequest)
		return
	}
	t := model.ForumTopic{
		CategoryID: body.CategoryID,
		AuthorID:   claims.UserID,
		Title:      strings.TrimSpace(body.Title),
		Status:     "open",
	}
	tid, err := db.CreateForumTopic(t)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	_, _ = db.CreateForumPost(model.ForumPost{
		TopicID:  int(tid),
		AuthorID: claims.UserID,
		Content:  strings.TrimSpace(body.Content),
	})
	writeJSON(w, http.StatusCreated, map[string]any{"message": "topic created", "id": tid})
}

func forumTopicPostsHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	topicID, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid topic ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}
	includeHidden := isForumModerator(claims)

	switch r.Method {
	case http.MethodGet:
		posts, err := db.ListForumPosts(topicID, includeHidden)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		writeJSON(w, http.StatusOK, posts)
	case http.MethodPost:
		topic, err := db.GetForumTopicByID(topicID, includeHidden)
		if err != nil || topic == nil {
			http.Error(w, "Topic not found", http.StatusNotFound)
			return
		}
		if topic.IsLocked {
			http.Error(w, "Topic is locked", http.StatusForbidden)
			return
		}
		if topic.Status == "closed" {
			http.Error(w, "Topic is closed", http.StatusForbidden)
			return
		}
		var body struct {
			Content string `json:"content"`
		}
		if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if strings.TrimSpace(body.Content) == "" {
			http.Error(w, "content required", http.StatusBadRequest)
			return
		}
		id, err := db.CreateForumPost(model.ForumPost{
			TopicID:  topicID,
			AuthorID: claims.UserID,
			Content:  strings.TrimSpace(body.Content),
		})
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		writeJSON(w, http.StatusCreated, map[string]any{"message": "post created", "id": id})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func forumPostByIDHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}

	switch r.Method {
	case http.MethodPut:
		post, err := db.GetForumPostByID(id)
		if err != nil || post == nil {
			http.Error(w, "Post not found", http.StatusNotFound)
			return
		}
		if post.IsHidden && !isForumModerator(claims) {
			http.Error(w, "Forbidden", http.StatusForbidden)
			return
		}
		if post.AuthorID != claims.UserID {
			http.Error(w, "Forbidden", http.StatusForbidden)
			return
		}
		var body struct {
			Content string `json:"content"`
		}
		if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if strings.TrimSpace(body.Content) == "" {
			http.Error(w, "content required", http.StatusBadRequest)
			return
		}
		if err := db.UpdateForumPost(id, strings.TrimSpace(body.Content)); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		writeJSON(w, http.StatusOK, map[string]string{"message": "post updated"})
	case http.MethodDelete:
		post, err := db.GetForumPostByID(id)
		if err != nil || post == nil {
			http.Error(w, "Post not found", http.StatusNotFound)
			return
		}
		if isForumModerator(claims) {
			_ = db.InsertForumModerationLog(claims.UserID, "delete_post", "post", id, "")
			if err := db.DeleteForumPost(id); err != nil {
				http.Error(w, err.Error(), http.StatusNotFound)
				return
			}
			writeJSON(w, http.StatusOK, map[string]string{"message": "post deleted"})
			return
		}
		if post.AuthorID != claims.UserID || post.IsHidden {
			http.Error(w, "Forbidden", http.StatusForbidden)
			return
		}
		if err := db.DeleteForumPost(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		writeJSON(w, http.StatusOK, map[string]string{"message": "post deleted"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func forumPostReportHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	postID, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid post ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumMember(w, r)
	if !ok {
		return
	}
	post, err := db.GetForumPostByID(postID)
	if err != nil || post == nil || (post.IsHidden && !isForumModerator(claims)) {
		http.Error(w, "Post not found", http.StatusNotFound)
		return
	}
	var body struct {
		Reason  string `json:"reason"`
		Details string `json:"details"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if strings.TrimSpace(body.Reason) == "" {
		http.Error(w, "reason required", http.StatusBadRequest)
		return
	}
	id, err := db.CreateForumReport(model.ForumReport{
		PostID:     postID,
		ReporterID: claims.UserID,
		Reason:     strings.TrimSpace(body.Reason),
		Details:    strings.TrimSpace(body.Details),
	})
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusCreated, map[string]any{"message": "report created", "id": id})
}

func adminForumReportsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireForumModerator(w, r); !ok {
		return
	}
	f := model.ForumReportFilter{
		Status:  r.URL.Query().Get("status"),
		Page:    parsePositiveInt(r.URL.Query().Get("page"), 1),
		PerPage: parsePositiveInt(r.URL.Query().Get("per_page"), 25),
	}
	items, total, err := db.ListForumReports(f)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": f.Page, "per_page": f.PerPage})
}

func adminForumReportByIDHandler(w http.ResponseWriter, r *http.Request, idStr string) {
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireForumModerator(w, r)
	if !ok {
		return
	}
	var body struct {
		Status string `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	status := strings.TrimSpace(body.Status)
	if status != "reviewed" && status != "dismissed" && status != "pending" {
		http.Error(w, "invalid status", http.StatusBadRequest)
		return
	}
	if err := db.UpdateForumReportStatus(id, status, claims.UserID); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}
	_ = db.InsertForumModerationLog(claims.UserID, "handle_report", "report", id, status)
	writeJSON(w, http.StatusOK, map[string]string{"message": "report updated"})
}

func adminForumPostActionHandler(w http.ResponseWriter, r *http.Request, idStr, action string) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumModerator(w, r)
	if !ok {
		return
	}
	var body struct {
		Reason string `json:"reason"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	switch action {
	case "hide":
		if err := db.SetForumPostHidden(id, true, body.Reason, claims.UserID); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "hide_post", "post", id, body.Reason)
	case "restore":
		if err := db.SetForumPostHidden(id, false, "", 0); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "restore_post", "post", id, body.Reason)
	default:
		http.Error(w, "Invalid action", http.StatusBadRequest)
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"message": "ok"})
}

func adminForumTopicActionHandler(w http.ResponseWriter, r *http.Request, idStr, action string) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	claims, ok := requireForumModerator(w, r)
	if !ok {
		return
	}
	var body struct {
		Reason string `json:"reason"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	switch action {
	case "lock":
		if err := db.SetForumTopicLocked(id, true); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "lock_topic", "topic", id, body.Reason)
	case "unlock":
		if err := db.SetForumTopicLocked(id, false); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "unlock_topic", "topic", id, body.Reason)
	case "hide":
		if err := db.SetForumTopicHidden(id, true, "hidden"); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "hide_topic", "topic", id, body.Reason)
	case "restore":
		if err := db.SetForumTopicHidden(id, false, "open"); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		_ = db.InsertForumModerationLog(claims.UserID, "restore_topic", "topic", id, body.Reason)
	default:
		http.Error(w, "Invalid action", http.StatusBadRequest)
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"message": "ok"})
}

func adminForumModerationLogsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireForumModerator(w, r); !ok {
		return
	}
	page := parsePositiveInt(r.URL.Query().Get("page"), 1)
	perPage := parsePositiveInt(r.URL.Query().Get("per_page"), 50)
	items, total, err := db.ListForumModerationLogs(page, perPage)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": total, "page": page, "per_page": perPage})
}
