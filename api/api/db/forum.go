package db

import (
	"api/model"
	"database/sql"
	"errors"
	"fmt"
	"regexp"
	"strings"
	"time"
)

var slugSanitize = regexp.MustCompile(`[^a-z0-9]+`)

func forumSlugify(title string, id int) string {
	s := strings.ToLower(strings.TrimSpace(title))
	s = slugSanitize.ReplaceAllString(s, "-")
	s = strings.Trim(s, "-")
	if s == "" {
		s = "topic"
	}
	if len(s) > 180 {
		s = s[:180]
	}
	return fmt.Sprintf("%s-%d", s, id)
}

func InsertForumModerationLog(moderatorID int, action, targetType string, targetID int, reason string) error {
	_, err := DB.Exec(`
		INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason)
		VALUES (?, ?, ?, ?, ?)
	`, moderatorID, action, targetType, targetID, nullIfEmpty(reason))
	return err
}

func nullIfEmpty(s string) any {
	if strings.TrimSpace(s) == "" {
		return nil
	}
	return s
}

// --- Categories ---

func ListForumCategories(activeOnly bool) ([]model.ForumCategory, error) {
	q := `
		SELECT id_category, name, slug, COALESCE(description,''), sort_order, is_active,
		       COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(updated_at,'%Y-%m-%d %H:%i:%s'),'')
		FROM forum_categories`
	if activeOnly {
		q += ` WHERE is_active = 1`
	}
	q += ` ORDER BY sort_order ASC, name ASC`
	rows, err := DB.Query(q)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []model.ForumCategory
	for rows.Next() {
		var c model.ForumCategory
		var active int
		if err := rows.Scan(&c.ID, &c.Name, &c.Slug, &c.Description, &c.SortOrder, &active, &c.CreatedAt, &c.UpdatedAt); err != nil {
			return nil, err
		}
		c.IsActive = active == 1
		out = append(out, c)
	}
	return out, nil
}

func GetForumCategoryByID(id int) (*model.ForumCategory, error) {
	var c model.ForumCategory
	var active int
	err := DB.QueryRow(`
		SELECT id_category, name, slug, COALESCE(description,''), sort_order, is_active,
		       COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(updated_at,'%Y-%m-%d %H:%i:%s'),'')
		FROM forum_categories WHERE id_category = ?
	`, id).Scan(&c.ID, &c.Name, &c.Slug, &c.Description, &c.SortOrder, &active, &c.CreatedAt, &c.UpdatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	c.IsActive = active == 1
	return &c, nil
}

func CreateForumCategory(c model.ForumCategory) (int64, error) {
	active := 0
	if c.IsActive {
		active = 1
	}
	res, err := DB.Exec(`
		INSERT INTO forum_categories (name, slug, description, sort_order, is_active)
		VALUES (?, ?, ?, ?, ?)
	`, c.Name, c.Slug, c.Description, c.SortOrder, active)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func UpdateForumCategory(id int, c model.ForumCategory) error {
	active := 0
	if c.IsActive {
		active = 1
	}
	res, err := DB.Exec(`
		UPDATE forum_categories SET name=?, slug=?, description=?, sort_order=?, is_active=?
		WHERE id_category=?
	`, c.Name, c.Slug, c.Description, c.SortOrder, active, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("category not found")
	}
	return nil
}

func DeleteForumCategory(id int) error {
	res, err := DB.Exec(`DELETE FROM forum_categories WHERE id_category = ?`, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("category not found")
	}
	return nil
}

// --- Topics ---

func scanForumTopic(scanner interface{ Scan(dest ...any) error }, t *model.ForumTopic, withMeta bool) error {
	var pinned, locked, hidden int
	var lastPost sql.NullString
	dest := []any{
		&t.ID, &t.CategoryID, &t.AuthorID, &t.Title, &t.Slug, &t.Status,
		&pinned, &locked, &hidden, &t.ViewsCount, &t.PostsCount, &lastPost,
		&t.CreatedAt, &t.UpdatedAt,
	}
	if withMeta {
		dest = append(dest, &t.AuthorPseudo, &t.CategoryName)
	}
	if err := scanner.Scan(dest...); err != nil {
		return err
	}
	t.IsPinned = pinned == 1
	t.IsLocked = locked == 1
	t.IsHidden = hidden == 1
	if lastPost.Valid {
		t.LastPostAt = lastPost.String
	}
	return nil
}

const forumTopicSelect = `
	SELECT t.id_topic, t.category_id, t.author_id, t.title, t.slug, t.status,
	       t.is_pinned, t.is_locked, t.is_hidden, t.views_count, t.posts_count,
	       COALESCE(DATE_FORMAT(t.last_post_at,'%Y-%m-%d %H:%i:%s'),''),
	       COALESCE(DATE_FORMAT(t.created_at,'%Y-%m-%d %H:%i:%s'),''),
	       COALESCE(DATE_FORMAT(t.updated_at,'%Y-%m-%d %H:%i:%s'),'')`

const forumTopicSelectMeta = forumTopicSelect + `,
	       COALESCE(u.pseudo,''), COALESCE(c.name,'')`

func ListForumTopics(f model.ForumTopicFilter) ([]model.ForumTopic, int, error) {
	if f.Page < 1 {
		f.Page = 1
	}
	if f.PerPage < 1 {
		f.PerPage = 20
	}
	where := []string{"1=1"}
	args := []any{}
	if !f.IncludeHidden {
		where = append(where, "t.is_hidden = 0", "t.status <> 'hidden'")
	}
	if f.CategoryID > 0 {
		where = append(where, "t.category_id = ?")
		args = append(args, f.CategoryID)
	}
	if f.AuthorID > 0 {
		where = append(where, "t.author_id = ?")
		args = append(args, f.AuthorID)
	}
	if s := strings.TrimSpace(f.Status); s != "" && s != "all" {
		where = append(where, "t.status = ?")
		args = append(args, s)
	}
	if q := strings.TrimSpace(f.Query); q != "" {
		like := "%" + q + "%"
		where = append(where, "(t.title LIKE ? OR t.slug LIKE ?)")
		args = append(args, like, like)
	}
	whereSQL := strings.Join(where, " AND ")

	var total int
	if err := DB.QueryRow(`SELECT COUNT(*) FROM forum_topics t WHERE `+whereSQL, args...).Scan(&total); err != nil {
		return nil, 0, err
	}

	offset := (f.Page - 1) * f.PerPage
	listArgs := append(args, f.PerPage, offset)
	rows, err := DB.Query(forumTopicSelectMeta+`
		FROM forum_topics t
		LEFT JOIN utilisateur u ON u.id_user = t.author_id
		LEFT JOIN forum_categories c ON c.id_category = t.category_id
		WHERE `+whereSQL+`
		ORDER BY t.is_pinned DESC, COALESCE(t.last_post_at, t.created_at) DESC
		LIMIT ? OFFSET ?
	`, listArgs...)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var items []model.ForumTopic
	for rows.Next() {
		var t model.ForumTopic
		if err := scanForumTopic(rows, &t, true); err != nil {
			return nil, 0, err
		}
		items = append(items, t)
	}
	return items, total, nil
}

func GetForumTopicByID(id int, includeHidden bool) (*model.ForumTopic, error) {
	q := forumTopicSelectMeta + `
		FROM forum_topics t
		LEFT JOIN utilisateur u ON u.id_user = t.author_id
		LEFT JOIN forum_categories c ON c.id_category = t.category_id
		WHERE t.id_topic = ?`
	if !includeHidden {
		q += ` AND t.is_hidden = 0 AND t.status <> 'hidden'`
	}
	var t model.ForumTopic
	err := scanForumTopic(DB.QueryRow(q, id), &t, true)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &t, nil
}

func CreateForumTopic(t model.ForumTopic) (int64, error) {
	pinned, locked, hidden := 0, 0, 0
	if t.IsPinned {
		pinned = 1
	}
	if t.IsLocked {
		locked = 1
	}
	if t.IsHidden {
		hidden = 1
	}
	if strings.TrimSpace(t.Status) == "" {
		t.Status = "open"
	}
	res, err := DB.Exec(`
		INSERT INTO forum_topics (category_id, author_id, title, slug, status, is_pinned, is_locked, is_hidden, posts_count, last_post_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
	`, t.CategoryID, t.AuthorID, t.Title, t.Slug, t.Status, pinned, locked, hidden)
	if err != nil {
		return 0, err
	}
	id, err := res.LastInsertId()
	if err != nil {
		return 0, err
	}
	slug := forumSlugify(t.Title, int(id))
	_, _ = DB.Exec(`UPDATE forum_topics SET slug = ? WHERE id_topic = ?`, slug, id)
	return id, nil
}

func UpdateForumTopic(id int, t model.ForumTopic) error {
	pinned, locked, hidden := 0, 0, 0
	if t.IsPinned {
		pinned = 1
	}
	if t.IsLocked {
		locked = 1
	}
	if t.IsHidden {
		hidden = 1
	}
	res, err := DB.Exec(`
		UPDATE forum_topics SET category_id=?, title=?, slug=?, status=?, is_pinned=?, is_locked=?, is_hidden=?
		WHERE id_topic=?
	`, t.CategoryID, t.Title, t.Slug, t.Status, pinned, locked, hidden, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("topic not found")
	}
	return nil
}

func SetForumTopicLocked(id int, locked bool) error {
	v := 0
	if locked {
		v = 1
	}
	res, err := DB.Exec(`UPDATE forum_topics SET is_locked = ? WHERE id_topic = ?`, v, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("topic not found")
	}
	return nil
}

func SetForumTopicHidden(id int, hidden bool, status string) error {
	v := 0
	if hidden {
		v = 1
	}
	res, err := DB.Exec(`UPDATE forum_topics SET is_hidden = ?, status = ? WHERE id_topic = ?`, v, status, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("topic not found")
	}
	return nil
}

func IncrementForumTopicViews(id int) error {
	_, err := DB.Exec(`UPDATE forum_topics SET views_count = views_count + 1 WHERE id_topic = ?`, id)
	return err
}

func BumpForumTopicAfterPost(topicID int) error {
	_, err := DB.Exec(`
		UPDATE forum_topics SET posts_count = posts_count + 1, last_post_at = ?
		WHERE id_topic = ?
	`, time.Now().Format("2006-01-02 15:04:05"), topicID)
	return err
}

func DeleteForumTopic(id int) error {
	res, err := DB.Exec(`DELETE FROM forum_topics WHERE id_topic = ?`, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("topic not found")
	}
	return nil
}

// --- Posts ---

func ListForumPosts(topicID int, includeHidden bool) ([]model.ForumPost, error) {
	q := `
		SELECT p.id_post, p.topic_id, p.author_id, p.content, p.is_hidden,
		       COALESCE(p.hidden_reason,''), COALESCE(p.hidden_by,0),
		       COALESCE(DATE_FORMAT(p.hidden_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(p.created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(p.updated_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(u.pseudo,'')
		FROM forum_posts p
		LEFT JOIN utilisateur u ON u.id_user = p.author_id
		WHERE p.topic_id = ?`
	if !includeHidden {
		q += ` AND p.is_hidden = 0`
	}
	q += ` ORDER BY p.created_at ASC`
	rows, err := DB.Query(q, topicID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []model.ForumPost
	for rows.Next() {
		var p model.ForumPost
		var hidden int
		if err := rows.Scan(&p.ID, &p.TopicID, &p.AuthorID, &p.Content, &hidden,
			&p.HiddenReason, &p.HiddenBy, &p.HiddenAt, &p.CreatedAt, &p.UpdatedAt, &p.AuthorPseudo); err != nil {
			return nil, err
		}
		p.IsHidden = hidden == 1
		out = append(out, p)
	}
	return out, nil
}

func GetForumPostByID(id int) (*model.ForumPost, error) {
	var p model.ForumPost
	var hidden int
	err := DB.QueryRow(`
		SELECT p.id_post, p.topic_id, p.author_id, p.content, p.is_hidden,
		       COALESCE(p.hidden_reason,''), COALESCE(p.hidden_by,0),
		       COALESCE(DATE_FORMAT(p.hidden_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(p.created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(p.updated_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(u.pseudo,'')
		FROM forum_posts p
		LEFT JOIN utilisateur u ON u.id_user = p.author_id
		WHERE p.id_post = ?
	`, id).Scan(&p.ID, &p.TopicID, &p.AuthorID, &p.Content, &hidden,
		&p.HiddenReason, &p.HiddenBy, &p.HiddenAt, &p.CreatedAt, &p.UpdatedAt, &p.AuthorPseudo)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	p.IsHidden = hidden == 1
	return &p, nil
}

func CreateForumPost(p model.ForumPost) (int64, error) {
	res, err := DB.Exec(`
		INSERT INTO forum_posts (topic_id, author_id, content)
		VALUES (?, ?, ?)
	`, p.TopicID, p.AuthorID, p.Content)
	if err != nil {
		return 0, err
	}
	id, err := res.LastInsertId()
	if err == nil {
		_ = BumpForumTopicAfterPost(p.TopicID)
	}
	return id, err
}

func UpdateForumPost(id int, content string) error {
	res, err := DB.Exec(`UPDATE forum_posts SET content = ? WHERE id_post = ?`, content, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("post not found")
	}
	return nil
}

func SetForumPostHidden(id int, hidden bool, reason string, hiddenBy int) error {
	v := 0
	var hiddenAt any = nil
	var hiddenByVal any = nil
	var reasonVal any = nil
	if hidden {
		v = 1
		hiddenAt = time.Now().Format("2006-01-02 15:04:05")
		hiddenByVal = hiddenBy
		reasonVal = nullIfEmpty(reason)
	}
	res, err := DB.Exec(`
		UPDATE forum_posts SET is_hidden=?, hidden_reason=?, hidden_by=?, hidden_at=?
		WHERE id_post=?
	`, v, reasonVal, hiddenByVal, hiddenAt, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("post not found")
	}
	return nil
}

func DeleteForumPost(id int) error {
	var topicID int
	_ = DB.QueryRow(`SELECT topic_id FROM forum_posts WHERE id_post = ?`, id).Scan(&topicID)
	res, err := DB.Exec(`DELETE FROM forum_posts WHERE id_post = ?`, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("post not found")
	}
	if topicID > 0 {
		_, _ = DB.Exec(`UPDATE forum_topics SET posts_count = GREATEST(posts_count - 1, 0) WHERE id_topic = ?`, topicID)
	}
	return nil
}

// --- Reports ---

func CreateForumReport(r model.ForumReport) (int64, error) {
	res, err := DB.Exec(`
		INSERT INTO forum_reports (post_id, reporter_id, reason, details, status)
		VALUES (?, ?, ?, ?, 'pending')
	`, r.PostID, r.ReporterID, r.Reason, nullIfEmpty(r.Details))
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func ListForumReports(f model.ForumReportFilter) ([]model.ForumReport, int, error) {
	if f.Page < 1 {
		f.Page = 1
	}
	if f.PerPage < 1 {
		f.PerPage = 25
	}
	where := []string{"1=1"}
	args := []any{}
	if s := strings.TrimSpace(f.Status); s != "" && s != "all" {
		where = append(where, "r.status = ?")
		args = append(args, s)
	}
	whereSQL := strings.Join(where, " AND ")
	var total int
	if err := DB.QueryRow(`SELECT COUNT(*) FROM forum_reports r WHERE `+whereSQL, args...).Scan(&total); err != nil {
		return nil, 0, err
	}
	offset := (f.Page - 1) * f.PerPage
	listArgs := append(args, f.PerPage, offset)
	rows, err := DB.Query(`
		SELECT r.id_report, r.post_id, r.reporter_id, r.reason, COALESCE(r.details,''), r.status,
		       COALESCE(r.handled_by,0), COALESCE(DATE_FORMAT(r.handled_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(r.created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(ur.pseudo,''), LEFT(COALESCE(p.content,''), 120), COALESCE(t.title,'')
		FROM forum_reports r
		LEFT JOIN utilisateur ur ON ur.id_user = r.reporter_id
		LEFT JOIN forum_posts p ON p.id_post = r.post_id
		LEFT JOIN forum_topics t ON t.id_topic = p.topic_id
		WHERE `+whereSQL+`
		ORDER BY r.created_at DESC
		LIMIT ? OFFSET ?
	`, listArgs...)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var items []model.ForumReport
	for rows.Next() {
		var r model.ForumReport
		if err := rows.Scan(&r.ID, &r.PostID, &r.ReporterID, &r.Reason, &r.Details, &r.Status,
			&r.HandledBy, &r.HandledAt, &r.CreatedAt, &r.ReporterPseudo, &r.PostPreview, &r.TopicTitle); err != nil {
			return nil, 0, err
		}
		items = append(items, r)
	}
	return items, total, nil
}

func GetForumReportByID(id int) (*model.ForumReport, error) {
	var r model.ForumReport
	err := DB.QueryRow(`
		SELECT id_report, post_id, reporter_id, reason, COALESCE(details,''), status,
		       COALESCE(handled_by,0), COALESCE(DATE_FORMAT(handled_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s'),'')
		FROM forum_reports WHERE id_report = ?
	`, id).Scan(&r.ID, &r.PostID, &r.ReporterID, &r.Reason, &r.Details, &r.Status, &r.HandledBy, &r.HandledAt, &r.CreatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &r, nil
}

func UpdateForumReportStatus(id int, status string, handledBy int) error {
	res, err := DB.Exec(`
		UPDATE forum_reports SET status = ?, handled_by = ?, handled_at = NOW()
		WHERE id_report = ?
	`, status, handledBy, id)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("report not found")
	}
	return nil
}

func ListForumModerationLogs(page, perPage int) ([]model.ForumModerationLog, int, error) {
	if page < 1 {
		page = 1
	}
	if perPage < 1 {
		perPage = 50
	}
	var total int
	if err := DB.QueryRow(`SELECT COUNT(*) FROM forum_moderation_logs`).Scan(&total); err != nil {
		return nil, 0, err
	}
	offset := (page - 1) * perPage
	rows, err := DB.Query(`
		SELECT l.id_log, l.moderator_id, l.action, l.target_type, l.target_id,
		       COALESCE(l.reason,''), COALESCE(DATE_FORMAT(l.created_at,'%Y-%m-%d %H:%i:%s'),''),
		       COALESCE(u.pseudo,'')
		FROM forum_moderation_logs l
		LEFT JOIN utilisateur u ON u.id_user = l.moderator_id
		ORDER BY l.created_at DESC
		LIMIT ? OFFSET ?
	`, perPage, offset)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var out []model.ForumModerationLog
	for rows.Next() {
		var l model.ForumModerationLog
		if err := rows.Scan(&l.ID, &l.ModeratorID, &l.Action, &l.TargetType, &l.TargetID,
			&l.Reason, &l.CreatedAt, &l.ModeratorPseudo); err != nil {
			return nil, 0, err
		}
		out = append(out, l)
	}
	return out, total, nil
}
