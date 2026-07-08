DROP DATABASE IF EXISTS upcycleconnect;
CREATE DATABASE upcycleconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE upcycleconnect;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE role (
    id_role INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE utilisateur (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    pseudo VARCHAR(100),
    prenom VARCHAR(100),
    nom VARCHAR(100),
    telephone VARCHAR(20),
    adresse_rue VARCHAR(150),
    adresse_ville VARCHAR(100),
    adresse_code_postal VARCHAR(20),
    adresse_pays VARCHAR(100),
    photo_profil VARCHAR(255),
    bio TEXT,
    statut VARCHAR(50) NOT NULL DEFAULT 'actif',
    is_banned BOOLEAN DEFAULT FALSE,
    ban_reason TEXT,
    ban_until DATETIME NULL,
    is_approved BOOLEAN NOT NULL DEFAULT TRUE,
    tutorial_completed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_role INT NOT NULL,
    upcycling_score INT DEFAULT 0,
    FOREIGN KEY (id_role) REFERENCES role(id_role)
) ENGINE=InnoDB;

CREATE TABLE objet (
    id_objet INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    etat VARCHAR(50),
    type_materiau VARCHAR(100),
    poids DECIMAL(8,2),
    volume DECIMAL(8,2),
    photo_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_user INT NOT NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE conteneur (
    id_conteneur INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    adresse VARCHAR(255),
    statut VARCHAR(50) NOT NULL DEFAULT 'actif',
    date_installation DATE,
    derniere_maintenance DATE
) ENGINE=InnoDB;

CREATE TABLE demande_depot (
    id_demande INT AUTO_INCREMENT PRIMARY KEY,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    validated_at DATETIME,
    deposited_at DATETIME,
    id_user INT NOT NULL,
    id_objet INT NOT NULL,
    id_conteneur INT NOT NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_objet) REFERENCES objet(id_objet),
    FOREIGN KEY (id_conteneur) REFERENCES conteneur(id_conteneur)
) ENGINE=InnoDB;

CREATE TABLE code_barre (
    id_code_barre INT AUTO_INCREMENT PRIMARY KEY,
    barcode_value VARCHAR(100) NOT NULL,
    statut VARCHAR(50) DEFAULT 'actif',
    id_demande INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demande) REFERENCES demande_depot(id_demande) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE code_acces (
    id_code_acces INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL,
    expires_at DATETIME,
    used_at DATETIME,
    id_demande INT NOT NULL,
    FOREIGN KEY (id_demande) REFERENCES demande_depot(id_demande) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE retrait (
    id_retrait INT AUTO_INCREMENT PRIMARY KEY,
    collected_at DATETIME,
    notes TEXT,
    id_user INT,
    id_code_barre INT,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_code_barre) REFERENCES code_barre(id_code_barre)
) ENGINE=InnoDB;

CREATE TABLE annonce (
    id_annonce INT AUTO_INCREMENT PRIMARY KEY,
    mode VARCHAR(50),
    prix DECIMAL(10,2),
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    validated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_user INT NOT NULL,
    id_objet INT UNIQUE,
    id_validateur INT,
    commission_payee BOOLEAN DEFAULT 0,
    commission_payee_at DATETIME NULL,
    id_acheteur INT DEFAULT NULL,
    date_achat DATETIME DEFAULT NULL,
    id_reserve_par INT DEFAULT NULL,
    date_reserve DATETIME DEFAULT NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_objet) REFERENCES objet(id_objet),
    FOREIGN KEY (id_validateur) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE categorie_prestation (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE prestation (
    id_prestation INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    type VARCHAR(50),
    prix DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_categorie INT NOT NULL,
    id_user INT NULL,
    FOREIGN KEY (id_categorie) REFERENCES categorie_prestation(id_categorie),
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE session (
    id_session INT AUTO_INCREMENT PRIMARY KEY,
    date_debut DATETIME,
    date_fin DATETIME,
    lieu VARCHAR(150),
    capacite_max INT,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_prestation INT NOT NULL,
    id_validateur INT,
    id_createur INT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (id_prestation) REFERENCES prestation(id_prestation),
    FOREIGN KEY (id_validateur) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_createur) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE inscription (
    id_inscription INT AUTO_INCREMENT PRIMARY KEY,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_user INT NOT NULL,
    id_session INT NOT NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_session) REFERENCES session(id_session)
) ENGINE=InnoDB;

CREATE TABLE paiement (
    id_paiement INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50),
    payment_ref VARCHAR(150) UNIQUE,
    montant DECIMAL(10,2),
    devise VARCHAR(10),
    statut VARCHAR(50),
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_inscription INT NULL,
    user_id INT NULL,
    metadata TEXT NULL,
    FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription),
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user),
    INDEX idx_paiement_user (user_id),
    INDEX idx_paiement_statut (statut),
    INDEX idx_paiement_payment_ref (payment_ref)
) ENGINE=InnoDB;

CREATE TABLE notification (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    titre VARCHAR(180) NOT NULL,
    contenu TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE conseil (
    id_conseil INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    contenu TEXT NOT NULL,
    categorie VARCHAR(100),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    id_auteur INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_auteur) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE projet_upcycling (
    id_projet INT AUTO_INCREMENT PRIMARY KEY,
    id_pro INT NOT NULL,
    id_objet INT NULL,
    titre VARCHAR(180) NOT NULL,
    description TEXT,
    statut VARCHAR(50) NOT NULL DEFAULT 'brouillon',
    progression INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    is_featured BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pro) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_objet) REFERENCES objet(id_objet)
) ENGINE=InnoDB;

CREATE TABLE abonnement_pro (
    id_abonnement INT AUTO_INCREMENT PRIMARY KEY,
    id_pro INT NOT NULL,
    formule VARCHAR(50) NOT NULL DEFAULT 'gratuit',
    date_debut DATE NOT NULL,
    date_fin DATE NULL,
    prix DECIMAL(10,2) NOT NULL DEFAULT 0,
    statut VARCHAR(50) NOT NULL DEFAULT 'actif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pro) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE campagne_publicitaire (
    id_campagne INT AUTO_INCREMENT PRIMARY KEY,
    id_pro INT NOT NULL,
    titre VARCHAR(180) NOT NULL,
    description TEXT,
    budget DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_debut DATE NULL,
    date_fin DATE NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pro) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id_audit INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    cible_type VARCHAR(80),
    cible_id INT NULL,
    details TEXT,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE document_genere (
    id_document INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    titre VARCHAR(180) NOT NULL,
    file_path VARCHAR(255),
    contenu_html MEDIUMTEXT,
    id_paiement INT NULL,
    id_demande INT NULL,
    id_inscription INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_paiement) REFERENCES paiement(id_paiement),
    FOREIGN KEY (id_demande) REFERENCES demande_depot(id_demande),
    FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription)
) ENGINE=InnoDB;

CREATE TABLE facture (
    id_facture INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(60) NOT NULL UNIQUE,
    id_user INT NOT NULL,
    id_paiement INT NULL,
    montant_ht DECIMAL(10,2) NOT NULL DEFAULT 0,
    montant_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
    tva DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    devise VARCHAR(10) NOT NULL DEFAULT 'EUR',
    statut VARCHAR(50) NOT NULL DEFAULT 'generee',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_paiement) REFERENCES paiement(id_paiement)
) ENGINE=InnoDB;

CREATE TABLE forum_categories (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_forum_category_slug (slug)
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
    FOREIGN KEY (category_id) REFERENCES forum_categories(id_category) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_posts (
    id_post INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    author_id INT NOT NULL,
    content TEXT NOT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    hidden_reason VARCHAR(255) DEFAULT NULL,
    hidden_by INT DEFAULT NULL,
    hidden_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    FOREIGN KEY (post_id) REFERENCES forum_posts(id_post) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (handled_by) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_topic_views (
    id_view INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_topic_user (topic_id, user_id),
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id_topic) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_moderation_logs (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type ENUM('category','topic','post','report') NOT NULL,
    target_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

CREATE TABLE forum_bans (
    id_ban INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    banned_until DATETIME NOT NULL,
    banned_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (banned_by) REFERENCES utilisateur(id_user),
    INDEX idx_forum_bans_user (user_id),
    INDEX idx_forum_bans_active (banned_until)
) ENGINE=InnoDB;

CREATE TABLE user_warnings (
    id_warning INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    warning_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    issued_by INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (issued_by) REFERENCES utilisateur(id_user),
    INDEX idx_warnings_user (user_id),
    INDEX idx_warnings_unread (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS commissions (
    id_commission INT AUTO_INCREMENT PRIMARY KEY,
    annonce_id INT NOT NULL,
    vendeur_id INT NOT NULL,
    acheteur_id INT NULL,
    montant DECIMAL(10,2) NOT NULL,
    pourcentage DECIMAL(5,2) DEFAULT 5.00,
    statut ENUM('due', 'paid') DEFAULT 'due',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    FOREIGN KEY (annonce_id) REFERENCES annonce(id_annonce),
    FOREIGN KEY (vendeur_id) REFERENCES utilisateur(id_user),
    FOREIGN KEY (acheteur_id) REFERENCES utilisateur(id_user),
    INDEX idx_commissions_vendeur (vendeur_id),
    INDEX idx_commissions_statut (statut)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversation (
    id_conversation INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES utilisateur(id_user) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES utilisateur(id_user) ON DELETE CASCADE,
    UNIQUE KEY uk_conversation_users (user1_id, user2_id),
    INDEX idx_conversation_updated (updated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS message (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversation(id_conversation) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES utilisateur(id_user) ON DELETE CASCADE,
    INDEX idx_message_conversation (conversation_id),
    INDEX idx_message_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unread_messages (
    id_unread INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    conversation_id INT NOT NULL,
    last_read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversation(id_conversation) ON DELETE CASCADE,
    UNIQUE KEY uk_unread_user_conversation (user_id, conversation_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;