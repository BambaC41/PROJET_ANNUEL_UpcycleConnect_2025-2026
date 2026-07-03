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

INSERT INTO role (id_role, code, libelle) VALUES
(1, 'ADMIN', 'Administrateur'),
(2, 'USER', 'Particulier'),
(3, 'PRO', 'Professionnel'),
(4, 'SALARIE', 'Salarie animateur formateur');

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

INSERT INTO utilisateur (id_user, email, password_hash, pseudo, prenom, nom, telephone, adresse_rue, adresse_ville, adresse_code_postal, adresse_pays, photo_profil, bio, statut, is_banned, is_approved, tutorial_completed, id_role, upcycling_score) VALUES
(1, 'admin@upcycleconnect.fr', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'superadmin', 'Admin', 'Principal', '0102030405', '1 rue Upcycle', 'Paris', '75001', 'France', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2', 'Administration plateforme', 'actif', 0, 1, 1, 1, 0),
(2, 'staff@upcycleconnect.fr', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'staff', 'Staff', 'Upcycle', '0102030406', '2 rue Upcycle', 'Paris', '75001', 'France', 'https://picsum.photos/200?2', 'Equipe UpcycleConnect', 'actif', 0, 1, 1, 1, 0),
(3, 'user1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'recycleur92', 'Lucas', 'Martin', '0611223344', '12 rue des Fleurs', 'Paris', '75012', 'France', 'https://picsum.photos/200?1', 'Passionne de recyclage et bricolage', 'actif', 0, 1, 0, 2, 0),
(4, 'pro1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'atelier_pro', 'Camille', 'Bernard', '0644556677', '18 rue des Artisans', 'Nantes', '44000', 'France', 'https://picsum.photos/200?4', 'Professionnel seconde main', 'actif', 0, 1, 1, 3, 0),
(5, 'emp1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'formateur_anna', 'Anna', 'Dupont', '0699001122', '5 rue des Ateliers', 'Lille', '59000', 'France', 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e', 'Animatrice formatrice UpcycleConnect', 'actif', 0, 1, 1, 4, 0),
(6, 'jean.dupont@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'jean_dupont', 'Jean', 'Dupont', '0612345678', '15 rue de la Paix', 'Lyon', '69000', 'France', 'https://picsum.photos/200?5', 'Bricoleur et upcycleur amateur', 'actif', 0, 1, 1, 2, 0),
(7, 'marie.curie@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'marie_upcycle', 'Marie', 'Curie', '0698765432', '8 avenue des Arts', 'Paris', '75010', 'France', 'https://picsum.photos/200?6', 'Passionnee de DIY et upcycling', 'actif', 0, 1, 1, 2, 0),
(8, 'pierre.durand@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'pierre_diy', 'Pierre', 'Durand', '0645123789', '23 rue du Travail', 'Bordeaux', '33000', 'France', 'https://picsum.photos/200?7', 'Artisan ebeniste', 'actif', 0, 1, 1, 3, 0);

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

INSERT INTO objet (titre, description, etat, type_materiau, poids, volume, photo_url, id_user) VALUES
('Chaise en bois', 'Chaise ancienne a revaloriser', 'bon', 'bois', 7.50, 0.40, 'https://picsum.photos/400?wood1', 4),
('Lampe vintage', 'Lampe a reparer, abat-jour manquant', 'moyen', 'metal', 2.10, 0.15, 'https://picsum.photos/400?lamp1', 4),
('Panneaux bois brut', 'Lots de chutes pour atelier', 'bon', 'bois', 15.00, 1.20, 'https://picsum.photos/400?wood2', 4),
('Table basse', 'Table en bois massif a restaurer', 'a_renover', 'bois', 12.00, 0.50, 'https://picsum.photos/400?table', 4),
('Velo ancien', 'Velo des annees 70 a customiser', 'correct', 'metal', 15.00, 1.20, 'https://picsum.photos/400?velo', 4);

CREATE TABLE conteneur (
    id_conteneur INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    adresse VARCHAR(255),
    statut VARCHAR(50) NOT NULL DEFAULT 'actif',
    date_installation DATE,
    derniere_maintenance DATE
) ENGINE=InnoDB;

INSERT INTO conteneur (code, adresse, statut, date_installation, derniere_maintenance) VALUES
('C-001', '12 Avenue de France, Paris', 'actif', '2026-01-10', '2026-04-01'),
('C-002', '45 Rue Nationale, Lyon', 'actif', '2026-02-08', '2026-04-20');

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

INSERT INTO demande_depot (statut, requested_at, validated_at, deposited_at, id_user, id_objet, id_conteneur) VALUES
('retiree', NOW(), NOW(), NOW(), 4, 1, 1),
('validee', NOW(), NOW(), NULL, 4, 2, 2),
('validee', NOW(), NOW(), NULL, 4, 3, 1),
('validee', NOW(), NOW(), NULL, 4, 4, 2);

CREATE TABLE code_barre (
    id_code_barre INT AUTO_INCREMENT PRIMARY KEY,
    barcode_value VARCHAR(100) NOT NULL,
    statut VARCHAR(50) DEFAULT 'actif',
    id_demande INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_demande) REFERENCES demande_depot(id_demande) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO code_barre (barcode_value, statut, id_demande, created_at) VALUES 
('3765203685931', 'actif', 1, NOW()),
('3769123456789', 'actif', 2, NOW()),
('3767127856336', 'actif', 3, NOW()),
('3769777510835', 'actif', 4, NOW());

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

INSERT INTO annonce (mode, prix, statut, validated_at, id_user, id_objet, id_validateur, commission_payee) VALUES
('don', 0, 'validee', NOW(), 4, 1, 1, 0),
('vente', 35.00, 'validee', NOW(), 4, 2, 1, 0),
('vente', 49.00, 'rejetee', NOW(), 5, 3, 1, 0),
('vente', 25.00, 'validee', NOW(), 6, 4, 1, 0);

CREATE TABLE categorie_prestation (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

INSERT INTO categorie_prestation (nom, description) VALUES
('Atelier', 'Ateliers creatifs et recyclage'),
('Reparation', 'Services de reparation'),
('Formation', 'Formations professionnelles');

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

INSERT INTO prestation (titre, description, type, prix, id_categorie, id_user) VALUES
('Atelier couture', 'Apprendre a reparer des vetements', 'atelier', 20.00, 1, NULL),
('Reparation velo', 'Service reparation velo', 'service', 30.00, 2, NULL),
('Formation bois recycle', 'Formation intensive artisan', 'formation', 120.00, 3, NULL),
('Upcycling meubles', 'Apprenez a transformer vos vieux meubles', 'atelier', 45.00, 1, 5);

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

INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, id_prestation, id_validateur, id_createur, image_url) VALUES
('2026-06-20 09:00:00', '2026-06-20 17:00:00', 'Lille, 59000', 20, 'valide', 3, 1, 5, 'uploads/events/default.jpg');

CREATE TABLE inscription (
    id_inscription INT AUTO_INCREMENT PRIMARY KEY,
    statut VARCHAR(50) NOT NULL DEFAULT 'en_attente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_user INT NOT NULL,
    id_session INT NOT NULL,
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_session) REFERENCES session(id_session)
) ENGINE=InnoDB;

INSERT INTO inscription (statut, id_user, id_session) VALUES
('confirmee', 3, 1);

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

INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, id_inscription, user_id) VALUES
('stripe', 'cs_test_example', 120.00, 'EUR', 'paid', NOW(), 1, 3);

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

INSERT INTO notification (id_user, type, titre, contenu) VALUES
(1, 'system', 'Back-office', 'Bienvenue sur le panneau admin'),
(3, 'paiement_stripe', '✅ Paiement confirme', 'Votre paiement de 120€ a ete recu.'),
(3, 'annonce', 'Annonce validee', 'Votre annonce a ete validee par l\'administration.'),
(6, 'forum', 'Nouveau sujet', 'Un nouveau sujet a ete cree dans le forum.');

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

INSERT INTO conseil (titre, contenu, categorie, image_url, is_active) VALUES
('Preparer du bois de palette', 'Poncez, retirez les clous et protegez le bois. Peignez avec des peintures naturelles pour un resultat unique.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', TRUE),
('Entretenir son velo', 'Verifiez pneus, chaine et freins regulierement. Un entretien regulier prolonge la duree de vie de votre velo.', 'Guide', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e', TRUE),
('Recycler ses bocaux', 'Transformez vos bocaux en verre en luminaires ou pots de fleurs uniques.', 'Tutoriel', 'https://images.unsplash.com/photo-1602143407151-7111542de6e8', TRUE);

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

INSERT INTO projet_upcycling (id_pro, id_objet, titre, description, statut, progression, image_url, is_featured, is_public) VALUES
(4, 1, 'Renovation chaise bistrot', 'Transformation de chaise en piece design.', 'termine', 100, 'https://picsum.photos/600?project1', TRUE, TRUE);

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

INSERT INTO abonnement_pro (id_pro, formule, date_debut, date_fin, prix, statut) VALUES
(4, 'premium_annuel', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 299.00, 'actif');

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

INSERT INTO forum_categories (id_category, name, slug, description, sort_order, is_active) VALUES
(1, 'Questions generales', 'questions-generales', 'Echanges communautaires et entraide sur l\'upcycling', 1, 1),
(2, 'Reparation', 'reparation', 'Conseils reparation et maintenance d\'objets', 2, 1),
(3, 'Upcycling', 'upcycling', 'Projets creatifs et reemploi de materiaux', 3, 1),
(4, 'Conteneurs', 'conteneurs', 'Depots, collecte et logistique des conteneurs', 4, 1),
(5, 'Formations', 'formations', 'Ateliers et sessions de formation', 5, 1);

-- Correction : category_id ne peut pas être NOT NULL avec ON DELETE SET NULL
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

INSERT INTO forum_topics (id_topic, category_id, author_id, title, slug, status, is_pinned, is_locked, is_hidden, views_count, posts_count, last_post_at, created_at) VALUES
(1, 1, 3, 'Comment bien debuter dans l\'upcycling ?', 'comment-bien-debuter-dans-upcycling', 'open', 1, 0, 0, 156, 5, '2026-06-10 15:30:00', '2026-06-01 10:00:00'),
(2, 2, 6, 'Reparer une vieille chaise en bois', 'reparer-une-vieille-chaise-en-bois', 'open', 0, 0, 0, 89, 3, '2026-06-09 18:20:00', '2026-06-03 14:15:00'),
(3, 3, 7, 'Transformation de palettes en mobilier', 'transformation-palettes-mobilier', 'open', 0, 0, 0, 234, 8, '2026-06-10 20:45:00', '2026-06-02 09:30:00'),
(4, 4, 3, 'Question sur les conteneurs de depot', 'question-conteneurs-depot', 'open', 0, 0, 0, 45, 2, '2026-06-08 11:00:00', '2026-06-05 16:20:00'),
(5, 5, 4, 'Formation upcycling pour professionnels', 'formation-upcycling-professionnels', 'open', 0, 0, 0, 67, 4, '2026-06-07 14:30:00', '2026-06-04 08:45:00'),
(6, 1, 8, 'Ou trouver des materiaux gratuits pour upcycler ?', 'ou-trouver-materiaux-gratuits', 'open', 0, 0, 0, 112, 6, '2026-06-09 09:15:00', '2026-06-03 11:00:00'),
(7, 2, 5, 'Reparation d\'un velo ancien', 'reparation-velo-ancien', 'open', 1, 0, 0, 78, 3, '2026-06-08 16:45:00', '2026-06-04 13:30:00'),
(8, 3, 3, 'Creation de luminaires avec des bocaux', 'creation-luminaires-bocaux', 'open', 0, 0, 0, 145, 7, '2026-06-10 12:00:00', '2026-06-01 17:15:00');

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

INSERT INTO forum_posts (id_post, topic_id, author_id, content, created_at) VALUES
(1, 1, 3, 'Bonjour à tous ! Je debute dans l\'upcycling et je cherche des conseils pour commencer. Quels outils sont indispensables ? Quels materiaux sont les plus faciles à travailler ?', '2026-06-01 10:00:00'),
(2, 1, 6, 'Bienvenue ! Je recommande de commencer avec du bois de palette, c\'est facile à trouver et à travailler. Outils indispensables : une ponceuse, un marteau et des vis.', '2026-06-01 11:30:00'),
(3, 1, 7, 'Je plussoie pour le bois de palette ! La peinture à la craie est aussi très sympa pour débuter. N\'hesitez pas à regarder des tutos sur YouTube.', '2026-06-01 14:20:00'),
(4, 1, 3, 'Merci pour vos conseils ! Je vais me lancer sur une petite étagère en palette.', '2026-06-02 09:15:00'),
(5, 1, 8, 'Super projet ! N\'oublie pas de bien poncer les palettes pour éviter les échardes.', '2026-06-10 15:30:00'),
(6, 2, 6, 'J\'ai une vieille chaise en bois massif, le siège est cassé. Comment puis-je la réparer ?', '2026-06-03 14:15:00'),
(7, 2, 3, 'Tu peux remplacer l\'assise par une planche de contreplaqué et ajouter un coussin. Je l\'ai fait plusieurs fois !', '2026-06-04 10:00:00'),
(8, 2, 5, 'Pense à décaper l\'ancienne peinture avant de poncer et de vernir. Ça lui redonnera une seconde jeunesse.', '2026-06-09 18:20:00'),
(9, 3, 7, 'J\'ai récupéré 5 palettes. Des idées pour les transformer en mobilier de salon ?', '2026-06-02 09:30:00'),
(10, 3, 3, 'Tu peux faire une table basse avec 2 palettes superposées, ou une bibliothèque murale. Je peux t\'envoyer des plans.', '2026-06-02 12:00:00'),
(11, 3, 4, 'J\'ai fait une méridienne avec 3 palettes, c\'est top pour le jardin !', '2026-06-03 10:30:00'),
(12, 3, 6, 'N\'oublie pas de traiter le bois avec une lasure protectrice si c\'est pour l\'extérieur.', '2026-06-03 16:45:00'),
(13, 3, 7, 'Merci pour vos idées ! Je vais partir sur une table basse, j\'ai hâte de partager le résultat !', '2026-06-04 09:00:00'),
(14, 3, 8, 'Hâte de voir le résultat, poste des photos quand tu auras fini !', '2026-06-10 20:45:00');

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

INSERT INTO forum_reports (post_id, reporter_id, reason, details, status, created_at) VALUES
(2, 3, 'spam', 'Message publicitaire non sollicité', 'pending', NOW()),
(5, 6, 'inappropriate', 'Langage inapproprié', 'pending', NOW()),
(8, 3, 'off_topic', 'Hors sujet par rapport à la discussion', 'reviewed', NOW());

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

INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason, created_at) VALUES
(1, 'HIDE_POST', 'post', 1, 'Message inapproprié', NOW()),
(5, 'LOCK_TOPIC', 'topic', 1, 'Discussion hors de contrôle', NOW()),
(1, 'handle_report', 'report', 1, 'Signalement traité', NOW()),
(1, 'RESTORE_POST', 'post', 1, 'Message restauré après vérification', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 'UNLOCK_TOPIC', 'topic', 1, 'Sujet rouvert', DATE_SUB(NOW(), INTERVAL 2 DAY));

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

INSERT INTO user_warnings (user_id, warning_type, message, issued_by, created_at) VALUES
(3, 'forum', 'Merci de respecter les règles du forum. Évitez le langage inapproprié.', 1, NOW()),
(6, 'forum', 'Attention au spam', 5, DATE_SUB(NOW(), INTERVAL 1 DAY));

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

UPDATE forum_topics SET posts_count = 5 WHERE id_topic = 1;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 2;
UPDATE forum_topics SET posts_count = 8 WHERE id_topic = 3;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 4;
UPDATE forum_topics SET posts_count = 4 WHERE id_topic = 5;
UPDATE forum_topics SET posts_count = 6 WHERE id_topic = 6;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 7;
UPDATE forum_topics SET posts_count = 7 WHERE id_topic = 8;

UPDATE forum_topics SET last_post_at = '2026-06-10 15:30:00' WHERE id_topic = 1;
UPDATE forum_topics SET last_post_at = '2026-06-09 18:20:00' WHERE id_topic = 2;
UPDATE forum_topics SET last_post_at = '2026-06-10 20:45:00' WHERE id_topic = 3;
UPDATE forum_topics SET last_post_at = '2026-06-08 11:00:00' WHERE id_topic = 4;
UPDATE forum_topics SET last_post_at = '2026-06-07 14:30:00' WHERE id_topic = 5;
UPDATE forum_topics SET last_post_at = '2026-06-09 09:15:00' WHERE id_topic = 6;
UPDATE forum_topics SET last_post_at = '2026-06-08 16:45:00' WHERE id_topic = 7;
UPDATE forum_topics SET last_post_at = '2026-06-10 12:00:00' WHERE id_topic = 8;

SET FOREIGN_KEY_CHECKS = 1;

SELECT ' Base upcycleconnect prete' AS message;