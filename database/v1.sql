-- =====================================================
-- upcycleconnect_v2.sql (VERSION FINALE - STRIPE READY)
-- Base de données corrigée et alignée avec l'API Go
-- Date: 2026-06-11
-- =====================================================

DROP DATABASE IF EXISTS upcycleconnect;
CREATE DATABASE upcycleconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE upcycleconnect;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- =====================================================
-- 1. TABLE role
-- =====================================================
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

-- =====================================================
-- 2. TABLE utilisateur
-- =====================================================
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
    FOREIGN KEY (id_role) REFERENCES role(id_role)
) ENGINE=InnoDB;

INSERT INTO utilisateur (email, password_hash, pseudo, prenom, nom, telephone, adresse_rue, adresse_ville, adresse_code_postal, adresse_pays, photo_profil, bio, statut, is_banned, is_approved, tutorial_completed, id_role) VALUES
('admin@upcycleconnect.fr', '$2y$10$bJ.w2uBseyR6gonALT5VUu1hfbGWtriWWfozc4OLcf6hEwEHZ9Oey', 'superadmin', 'Admin', 'Principal', '0102030405', '1 rue Upcycle', 'Paris', '75001', 'France', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2', 'Administration plateforme', 'actif', 0, 1, 1, 1),
('user1@test.com', '$2y$10$bJ.w2uBseyR6gonALT5VUu1hfbGWtriWWfozc4OLcf6hEwEHZ9Oey', 'recycleur92', 'Lucas', 'Martin', '0611223344', '12 rue des Fleurs', 'Paris', '75012', 'France', 'https://picsum.photos/200?1', 'Passionne de recyclage et bricolage', 'actif', 0, 1, 0, 2),
('pro1@test.com', '$2y$10$bJ.w2uBseyR6gonALT5VUu1hfbGWtriWWfozc4OLcf6hEwEHZ9Oey', 'atelier_pro', 'Camille', 'Bernard', '0644556677', '18 rue des Artisans', 'Nantes', '44000', 'France', 'https://picsum.photos/200?4', 'Professionnel seconde main', 'actif', 0, 1, 1, 3),
('emp1@test.com', '$2y$10$bJ.w2uBseyR6gonALT5VUu1hfbGWtriWWfozc4OLcf6hEwEHZ9Oey', 'formateur_anna', 'Anna', 'Dupont', '0699001122', '5 rue des Ateliers', 'Lille', '59000', 'France', 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e', 'Animatrice formatrice UpcycleConnect', 'actif', 0, 1, 1, 4');

-- =====================================================
-- 3. TABLE objet
-- =====================================================
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
('Chaise en bois', 'Chaise ancienne a revaloriser', 'bon', 'bois', 7.50, 0.40, 'https://picsum.photos/400?wood1', 3),
('Lampe vintage', 'Lampe a reparer, abat-jour manquant', 'moyen', 'metal', 2.10, 0.15, 'https://picsum.photos/400?lamp1', 3),
('Panneaux bois brut', 'Lots de chutes pour atelier', 'bon', 'bois', 15.00, 1.20, 'https://picsum.photos/400?wood2', 4);

-- =====================================================
-- 4. TABLE conteneur
-- =====================================================
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

-- =====================================================
-- 5. TABLE demande_depot
-- =====================================================
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
('en_attente', NOW(), NULL, NULL, 3, 1, 1),
('validee', NOW(), NOW(), NULL, 3, 2, 2),
('deposee', NOW(), NOW(), NOW(), 4, 3, 1);

-- =====================================================
-- 6. TABLE annonce
-- =====================================================
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
    FOREIGN KEY (id_user) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_objet) REFERENCES objet(id_objet),
    FOREIGN KEY (id_validateur) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

INSERT INTO annonce (mode, prix, statut, validated_at, id_user, id_objet, id_validateur) VALUES
('don', 0, 'validee', NOW(), 3, 1, 1),
('vente', 35.00, 'validee', NOW(), 3, 2, 1),
('vente', 49.00, 'rejetee', NOW(), 4, 3, 2);

-- =====================================================
-- 7. TABLE categorie_prestation
-- =====================================================
CREATE TABLE categorie_prestation (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

INSERT INTO categorie_prestation (nom, description) VALUES
('Atelier', 'Ateliers creatifs et recyclage'),
('Reparation', 'Services de reparation'),
('Formation', 'Formations professionnelles');

-- =====================================================
-- 8. TABLE prestation
-- =====================================================
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
('Formation bois recycle', 'Formation intensive artisan', 'formation', 120.00, 3, NULL);

-- =====================================================
-- 9. TABLE session
-- =====================================================
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
    FOREIGN KEY (id_prestation) REFERENCES prestation(id_prestation),
    FOREIGN KEY (id_validateur) REFERENCES utilisateur(id_user),
    FOREIGN KEY (id_createur) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, id_prestation, id_validateur, id_createur) VALUES
('2026-05-04 09:45:00', '2026-05-04 11:15:00', 'Salle 05', 15, 'valide', 1, 1, NULL),
('2026-05-05 14:00:00', '2026-05-05 15:30:00', 'Salle 05', 12, 'valide', 2, 1, NULL),
('2026-05-06 15:45:00', '2026-05-06 17:15:00', 'Atelier B', 10, 'en_attente', 3, 1, 4);

-- =====================================================
-- 10. TABLE inscription
-- =====================================================
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
('confirmee', 3, 1),
('en_attente', 3, 3),
('confirmee', 4, 2);

-- =====================================================
-- 11. TABLE paiement (VERSION STRIPE READY)
-- =====================================================
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

-- Données de démonstration (optionnelles)
INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, id_inscription, user_id) VALUES
('demo', 'PAY-DEMO-0001', 20.00, 'EUR', 'paid', NOW(), 1, 3),
('demo', 'PAY-DEMO-0002', 120.00, 'EUR', 'pending', NULL, 2, 3),
('demo', 'PAY-DEMO-0003', 30.00, 'EUR', 'paid', NOW(), 3, 4);

-- =====================================================
-- 12. TABLE notification
-- =====================================================
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
(1, 'system', 'Back-office', 'Bienvenue sur le panneau admin : validez annonces, evenements et conseils.'),
(2, 'system', 'Staff', 'Nouvelles demandes de validation peuvent arriver dans la file.'),
(3, 'annonce', 'Annonce en attente', 'Votre annonce est en attente de validation admin.'),
(3, 'depot', 'Depot valide', 'Votre demande de depot C-001 a ete validee.'),
(4, 'pro', 'Compte professionnel actif', 'Votre compte professionnel est actif.'),
(1, 'event', 'Evenement a valider', 'Un nouvel atelier est en attente de validation.');

-- =====================================================
-- 13. TABLE conseil
-- =====================================================
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
('Preparer du bois de palette', 'Poncez, retirez les clous et protegez le bois.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', TRUE),
('Nouveaux conteneurs ouverts', 'Nouveaux conteneurs disponibles dans plusieurs quartiers.', 'News', 'https://images.unsplash.com/photo-1495020689067-958852a7765e', TRUE),
('Entretenir son velo', 'Verifiez pneus, chaine et freins regulierement.', 'Guide', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e', TRUE);

-- =====================================================
-- 14. TABLE projet_upcycling
-- =====================================================
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
(4, 1, 'Renovation chaise bistrot', 'Transformation de chaise en piece design.', 'en_cours', 60, 'https://picsum.photos/600?project1', TRUE, TRUE),
(4, 3, 'Bibliotheque modulaire', 'Assemblage de panneaux recycles.', 'brouillon', 20, 'https://picsum.photos/600?project2', FALSE, TRUE);

-- =====================================================
-- 15. TABLE abonnement_pro
-- =====================================================
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
(4, 'premium', '2026-05-01', '2027-04-30', 49.00, 'actif');

-- =====================================================
-- 16. TABLE campagne_publicitaire
-- =====================================================
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

INSERT INTO campagne_publicitaire (id_pro, titre, description, budget, date_debut, date_fin, statut) VALUES
(4, 'Promotion atelier bois recycle', 'Campagne locale sur 30 jours', 180.00, '2026-05-01', '2026-05-31', 'en_attente');

-- =====================================================
-- 17. TABLE audit_log
-- =====================================================
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

INSERT INTO audit_log (id_user, action, cible_type, cible_id, details) VALUES
(1, 'VALIDATION_ANNONCE', 'annonce', 1, 'Annonce validee pendant la phase de demo'),
(2, 'VALIDATION_DEPOT', 'demande_depot', 1, 'Demande de depot approuvee par le staff');

-- =====================================================
-- 18. TABLE forum_categories
-- =====================================================
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

INSERT INTO forum_categories (name, slug, description, sort_order, is_active) VALUES
('Questions generales', 'questions-generales', 'Echanges communautaires et entraide', 1, 1),
('Reparation', 'reparation', 'Conseils reparation et maintenance', 2, 1),
('Upcycling', 'upcycling', 'Projets creatifs et reemploi', 3, 1),
('Conteneurs', 'conteneurs', 'Depots, collecte et logistique', 4, 1),
('Formations', 'formations', 'Ateliers et sessions', 5, 1),
('Vie equipe', 'vie-equipe', 'Coordination interne equipe', 6, 1),
('Animation ateliers', 'animation-ateliers', 'Animation terrain et ateliers', 7, 1),
('Formation continue', 'formation-continue', 'Ressources pedagogiques salarie', 8, 1);

-- =====================================================
-- 19. TABLE forum_topics
-- =====================================================
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
    FOREIGN KEY (category_id) REFERENCES forum_categories(id_category),
    FOREIGN KEY (author_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

INSERT INTO forum_topics (category_id, author_id, title, slug, status, is_pinned, posts_count, last_post_at) VALUES
(3, 4, 'Idees de reemploi textile', 'idees-reemploi-textile-1', 'open', 1, 2, NOW()),
(8, 4, 'Preparer un atelier debutant', 'preparer-atelier-debutant-2', 'open', 0, 0, NULL);

-- =====================================================
-- 20. TABLE forum_posts
-- =====================================================
CREATE TABLE forum_posts (
    id_post INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    author_id INT NOT NULL,
    content TEXT NOT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id_topic) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

INSERT INTO forum_posts (topic_id, author_id, content) VALUES
(1, 4, 'Quels materiaux privilegier pour debuter avec un groupe de 12 participants ?'),
(1, 2, 'Commencez par coton et denim recuperes, avec des patrons simples.');

UPDATE forum_topics SET posts_count = 2, last_post_at = NOW() WHERE id_topic = 1;

-- =====================================================
-- 21. TABLE forum_reports
-- =====================================================
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

INSERT INTO forum_reports (post_id, reporter_id, reason, details, status) VALUES
(2, 4, 'off_topic', 'Message a verifier avant publication officielle', 'pending');

-- =====================================================
-- 22. TABLE document_genere
-- =====================================================
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

INSERT INTO document_genere (id_user, type, titre, file_path, contenu_html, id_paiement, id_demande, id_inscription) VALUES
(3, 'recu_paiement', 'Recu atelier couture', 'documents/recu_1.html', '<h1>Recu paiement</h1><p>Paiement confirme.</p>', 1, NULL, 1),
(3, 'fiche_depot', 'Fiche depot C-002', 'documents/depot_2.html', '<h1>Fiche depot</h1><p>Code acces: ACC-UC-2026-0002</p>', NULL, 2, NULL),
(4, 'facture_abonnement', 'Facture abonnement premium', 'documents/facture_abonnement_pro1.html', '<h1>Facture abonnement</h1><p>Formule premium active.</p>', NULL, NULL, NULL);

-- =====================================================
-- 23. TABLE facture
-- =====================================================
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

INSERT INTO facture (numero, id_user, id_paiement, montant_ht, montant_ttc, statut) VALUES
('FAC-2026-0001', 3, 1, 16.67, 20.00, 'generee'),
('FAC-2026-0002', 4, 3, 25.00, 30.00, 'generee');

-- =====================================================
-- 24. TABLE forum_topic_views
-- =====================================================
CREATE TABLE forum_topic_views (
    id_view INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_topic_user (topic_id, user_id),
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id_topic) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id_user)
) ENGINE=InnoDB;

-- =====================================================
-- 25. TABLE forum_moderation_logs
-- =====================================================
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

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- VERIFICATION FINALE
-- =====================================================
SELECT '✅ Base upcycleconnect prete pour Stripe !' AS message;
SELECT COUNT(*) AS nb_tables FROM information_schema.tables WHERE table_schema = 'upcycleconnect';