-- Migration forum v2 : remplace forum_topic, forum_message, forum_signalement
-- À exécuter sur une base existante (sauvegarde recommandée).
-- Rôles applicatifs attendus : 1=ADMIN, 2=USER/Particulier, 3=PRO, 4=SALARIE (plus de STAFF ni id_role=5).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS forum_signalement;
DROP TABLE IF EXISTS forum_message;
DROP TABLE IF EXISTS forum_topic;
DROP TABLE IF EXISTS forum_reports;
DROP TABLE IF EXISTS forum_posts;
DROP TABLE IF EXISTS forum_topics;
DROP TABLE IF EXISTS forum_moderation_logs;
DROP TABLE IF EXISTS forum_categories;
SET FOREIGN_KEY_CHECKS = 1;

-- Recréer les tables (identique à upcycleconnect_v2.sql)

CREATE TABLE forum_categories (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_forum_category_slug (slug),
    INDEX idx_forum_category_active (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE forum_topics (
    id_topic INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    status ENUM('open','closed','hidden') NOT NULL DEFAULT 'open',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    views_count INT NOT NULL DEFAULT 0,
    posts_count INT NOT NULL DEFAULT 0,
    last_post_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_forum_topic_category (category_id, is_hidden, status),
    INDEX idx_forum_topic_author (author_id),
    FOREIGN KEY (category_id) REFERENCES forum_categories(id_category),
    FOREIGN KEY (author_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_posts (
    id_post INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    author_id INT NOT NULL,
    content TEXT NOT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    hidden_reason VARCHAR(255) DEFAULT NULL,
    hidden_by INT NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_forum_post_topic (topic_id, is_hidden, created_at),
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id_topic) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (hidden_by) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_reports (
    id_report INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(120) NOT NULL,
    details TEXT,
    status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    handled_by INT NULL,
    handled_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_forum_report_status (status, created_at),
    FOREIGN KEY (post_id) REFERENCES forum_posts(id_post) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (handled_by) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_moderation_logs (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type ENUM('category','topic','post','report') NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_forum_mod_log_created (created_at),
    FOREIGN KEY (moderator_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forum_topic_views (
    id_view INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_topic_user (topic_id, user_id),
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id_topic) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

INSERT INTO forum_categories (name, slug, description, sort_order, is_active) VALUES
('Questions generales', 'questions-generales', 'Entraide communautaire', 1, 1),
('Reparation', 'reparation', 'Conseils reparation', 2, 1),
('Upcycling', 'upcycling', 'Projets creatifs', 3, 1),
('Conteneurs', 'conteneurs', 'Depots et collecte', 4, 1),
('Formations', 'formations', 'Ateliers et sessions', 5, 1),
('Vie d''equipe', 'vie-equipe', 'Coordination interne', 6, 1),
('Animation & ateliers', 'animation-ateliers', 'Animation terrain', 7, 1),
('Formation continue', 'formation-continue', 'Ressources pedagogiques', 8, 1);
