<?php
declare(strict_types=1);

require_once __DIR__ . '/local_db.php';

function forum_schema_ready(): bool
{
    return (bool)db_safe_exec(static function (PDO $pdo): bool {
        $stmt = $pdo->query("SHOW TABLES LIKE 'forum_topics'");
        return $stmt !== false && $stmt->rowCount() > 0;
    }, false);
}

function forum_slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'sujet';
}

function forum_ensure_views_table(): void
{
    db_safe_exec(static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS forum_topic_views (
            id_view INT AUTO_INCREMENT PRIMARY KEY,
            topic_id INT NOT NULL,
            user_id INT NOT NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_topic_user (topic_id, user_id),
            INDEX idx_forum_view_topic (topic_id)
        ) ENGINE=InnoDB");
    }, null);
}

function forum_get_categories(bool $activeOnly = true): array
{
    if (!forum_schema_ready()) {
        return [];
    }
    return (array)db_safe_exec(static function (PDO $pdo) use ($activeOnly): array {
        $sql = 'SELECT id_category AS id, name, slug, description, sort_order, is_active FROM forum_categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

function forum_get_topics(array $filters = []): array
{
    if (!forum_schema_ready()) {
        return [];
    }
    $categoryId = (int)($filters['category_id'] ?? 0);
    $q = mb_strtolower(trim((string)($filters['q'] ?? '')));
    $status = trim((string)($filters['status'] ?? ''));
    $limit = max(1, min(100, (int)($filters['per_page'] ?? 50)));
    $includeHidden = !empty($filters['include_hidden']);

    return (array)db_safe_exec(static function (PDO $pdo) use ($categoryId, $q, $status, $limit, $includeHidden): array {
        $where = $includeHidden ? ['1=1'] : ['t.is_hidden = 0'];
        $params = [];
        if ($categoryId > 0) {
            $where[] = 't.category_id = ?';
            $params[] = $categoryId;
        }
        if ($status !== '' && $status !== 'all') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(LOWER(t.title) LIKE ? OR LOWER(u.pseudo) LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sql = 'SELECT t.id_topic AS id, t.category_id, t.author_id, t.title, t.slug, t.status,
                t.is_pinned, t.is_locked, t.is_hidden, t.views_count, t.posts_count, t.last_post_at, t.created_at,
                u.pseudo AS author_pseudo, c.name AS category_name
            FROM forum_topics t
            JOIN utilisateur u ON u.id_user = t.author_id
            JOIN forum_categories c ON c.id_category = t.category_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY t.is_pinned DESC, COALESCE(t.last_post_at, t.created_at) DESC
            LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

function forum_get_topic(int $topicId, bool $includeHidden = false): ?array
{
    if ($topicId <= 0 || !forum_schema_ready()) {
        return null;
    }
    return db_safe_exec(static function (PDO $pdo) use ($topicId, $includeHidden): ?array {
        $sql = 'SELECT t.id_topic AS id, t.category_id, t.author_id, t.title, t.slug, t.status,
                t.is_pinned, t.is_locked, t.is_hidden, t.views_count, t.posts_count, t.last_post_at, t.created_at,
                u.pseudo AS author_pseudo, c.name AS category_name
            FROM forum_topics t
            JOIN utilisateur u ON u.id_user = t.author_id
            JOIN forum_categories c ON c.id_category = t.category_id
            WHERE t.id_topic = ?';
        if (!$includeHidden) {
            $sql .= ' AND t.is_hidden = 0';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$topicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }, null);
}

function forum_get_posts(int $topicId, bool $moderator = false): array
{
    if ($topicId <= 0 || !forum_schema_ready()) {
        return [];
    }
    return (array)db_safe_exec(static function (PDO $pdo) use ($topicId, $moderator): array {
        $sql = 'SELECT p.id_post AS id, p.topic_id, p.author_id, p.content, p.is_hidden, p.hidden_reason,
                p.created_at, p.updated_at, u.pseudo AS author_pseudo
            FROM forum_posts p
            JOIN utilisateur u ON u.id_user = p.author_id
            WHERE p.topic_id = ?';
        if (!$moderator) {
            $sql .= ' AND p.is_hidden = 0';
        }
        $sql .= ' ORDER BY p.created_at ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$topicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

function forum_create_topic(int $userId, int $categoryId, string $title, string $content): ?int
{
    if ($userId <= 0 || $categoryId <= 0 || trim($title) === '' || trim($content) === '' || !forum_schema_ready()) {
        return null;
    }
    return db_safe_exec(static function (PDO $pdo) use ($userId, $categoryId, $title, $content): ?int {
        $slug = forum_slugify($title) . '-' . time();
        $stmt = $pdo->prepare('INSERT INTO forum_topics (category_id, author_id, title, slug, status, posts_count, last_post_at, created_at)
            VALUES (?, ?, ?, ?, "open", 1, NOW(), NOW())');
        $stmt->execute([$categoryId, $userId, trim($title), $slug]);
        $topicId = (int)$pdo->lastInsertId();
        if ($topicId <= 0) {
            return null;
        }
        $post = $pdo->prepare('INSERT INTO forum_posts (topic_id, author_id, content, created_at) VALUES (?, ?, ?, NOW())');
        $post->execute([$topicId, $userId, trim($content)]);
        return $topicId;
    }, null);
}

function forum_add_post(int $topicId, int $userId, string $content): bool
{
    if ($topicId <= 0 || $userId <= 0 || trim($content) === '' || !forum_schema_ready()) {
        return false;
    }
    return (bool)db_safe_exec(static function (PDO $pdo) use ($topicId, $userId, $content): bool {
        $topic = $pdo->prepare('SELECT is_locked, is_hidden FROM forum_topics WHERE id_topic = ?');
        $topic->execute([$topicId]);
        $t = $topic->fetch(PDO::FETCH_ASSOC);
        if (!$t || (int)($t['is_locked'] ?? 0) === 1 || (int)($t['is_hidden'] ?? 0) === 1) {
            return false;
        }
        $stmt = $pdo->prepare('INSERT INTO forum_posts (topic_id, author_id, content, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$topicId, $userId, trim($content)]);
        $pdo->prepare('UPDATE forum_topics SET posts_count = posts_count + 1, last_post_at = NOW() WHERE id_topic = ?')
            ->execute([$topicId]);
        return true;
    }, false);
}

function forum_record_view(int $topicId, int $userId): void
{
    if ($topicId <= 0 || $userId <= 0 || !forum_schema_ready()) {
        return;
    }
    forum_ensure_views_table();
    db_safe_exec(static function (PDO $pdo) use ($topicId, $userId): void {
        $st = $pdo->prepare('SELECT author_id FROM forum_topics WHERE id_topic = ?');
        $st->execute([$topicId]);
        if ((int)$st->fetchColumn() === $userId) {
            return;
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO forum_topic_views (topic_id, user_id, viewed_at) VALUES (?, ?, NOW())');
        $ins->execute([$topicId, $userId]);
        if ($ins->rowCount() > 0) {
            $pdo->prepare('UPDATE forum_topics SET views_count = views_count + 1 WHERE id_topic = ?')->execute([$topicId]);
        }
    }, null);
}

function forum_report_post(int $postId, int $reporterId, string $reason, string $details = ''): bool
{
    if ($postId <= 0 || $reporterId <= 0 || trim($reason) === '' || !forum_schema_ready()) {
        return false;
    }
    return (bool)db_safe_exec(static function (PDO $pdo) use ($postId, $reporterId, $reason, $details): bool {
        $stmt = $pdo->prepare('INSERT INTO forum_reports (post_id, reporter_id, reason, details, status, created_at)
            VALUES (?, ?, ?, ?, "pending", NOW())');
        return $stmt->execute([$postId, $reporterId, trim($reason), trim($details)]);
    }, false);
}

function forum_moderate_topic(int $topicId, int $moderatorId, string $action, string $reason = ''): bool
{
    if ($topicId <= 0 || !forum_schema_ready()) {
        return false;
    }
    $map = [
        'lock' => ['is_locked' => 1],
        'unlock' => ['is_locked' => 0],
        'hide' => ['is_hidden' => 1, 'status' => 'hidden'],
        'restore' => ['is_hidden' => 0, 'status' => 'open'],
    ];
    if (!isset($map[$action])) {
        return false;
    }
    return (bool)db_safe_exec(static function (PDO $pdo) use ($topicId, $moderatorId, $action, $reason, $map): bool {
        $sets = [];
        $params = [];
        foreach ($map[$action] as $col => $val) {
            $sets[] = $col . ' = ?';
            $params[] = $val;
        }
        $params[] = $topicId;
        $stmt = $pdo->prepare('UPDATE forum_topics SET ' . implode(', ', $sets) . ' WHERE id_topic = ?');
        $ok = $stmt->execute($params);
        if ($ok && $moderatorId > 0) {
            $log = $pdo->prepare('INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason, created_at)
                VALUES (?, ?, "topic", ?, ?, NOW())');
            $log->execute([$moderatorId, strtoupper($action) . '_TOPIC', $topicId, $reason]);
        }
        return $ok;
    }, false);
}

function forum_moderate_post(int $postId, int $moderatorId, string $action, string $reason = ''): bool
{
    if ($postId <= 0 || !forum_schema_ready()) {
        return false;
    }
    $hide = $action === 'hide' ? 1 : ($action === 'restore' ? 0 : null);
    if ($hide === null) {
        return false;
    }
    return (bool)db_safe_exec(static function (PDO $pdo) use ($postId, $moderatorId, $hide, $reason, $action): bool {
        $stmt = $pdo->prepare('UPDATE forum_posts SET is_hidden = ?, hidden_reason = ?, hidden_by = ?, hidden_at = NOW() WHERE id_post = ?');
        $ok = $stmt->execute([$hide, $reason, $moderatorId, $postId]);
        if ($ok && $moderatorId > 0) {
            $log = $pdo->prepare('INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason, created_at)
                VALUES (?, ?, "post", ?, ?, NOW())');
            $log->execute([$moderatorId, strtoupper($action) . '_POST', $postId, $reason]);
        }
        return $ok;
    }, false);
}

function forum_get_pending_reports(int $limit = 50): array
{
    if (!forum_schema_ready()) {
        return [];
    }
    return (array)db_safe_exec(static function (PDO $pdo) use ($limit): array {
        $stmt = $pdo->prepare('SELECT r.id_report AS id, r.post_id, r.reporter_id, r.reason, r.details, r.status, r.created_at,
                p.content AS post_content, t.id_topic AS topic_id, t.title AS topic_title,
                ur.pseudo AS reporter_pseudo
            FROM forum_reports r
            JOIN forum_posts p ON p.id_post = r.post_id
            JOIN forum_topics t ON t.id_topic = p.topic_id
            JOIN utilisateur ur ON ur.id_user = r.reporter_id
            WHERE r.status = "pending"
            ORDER BY r.created_at DESC
            LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

function forum_get_moderation_logs(int $limit = 15): array
{
    if (!forum_schema_ready()) {
        return [];
    }
    return (array)db_safe_exec(static function (PDO $pdo) use ($limit): array {
        $stmt = $pdo->prepare('SELECT l.id_log AS id, l.action, l.target_type, l.target_id, l.reason, l.created_at,
                u.pseudo AS moderator_pseudo
            FROM forum_moderation_logs l
            JOIN utilisateur u ON u.id_user = l.moderator_id
            ORDER BY l.created_at DESC
            LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

function forum_notify_moderators(string $title, string $content): void
{
    require_once __DIR__ . '/../notifications.php';
    notif_notify_roles([1, 4], 'forum', $title, $content);
}
