USE upcycleconnect;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO role (id_role, code, libelle) VALUES
(1, 'ADMIN', 'Administrateur'),
(2, 'USER', 'Particulier'),
(3, 'PRO', 'Professionnel'),
(4, 'SALARIE', 'Salarie animateur formateur');

INSERT INTO utilisateur (id_user, email, password_hash, pseudo, prenom, nom, telephone, adresse_rue, adresse_ville, adresse_code_postal, adresse_pays, photo_profil, bio, statut, is_banned, is_approved, tutorial_completed, id_role, upcycling_score) VALUES
(1, 'admin@upcycleconnect.fr', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'superadmin', 'Admin', 'Principal', '0102030405', '1 rue Upcycle', 'Paris', '75001', 'France', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2', 'Administration plateforme', 'actif', 0, 1, 1, 1, 0),
(2, 'staff@upcycleconnect.fr', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'staff', 'Staff', 'Upcycle', '0102030406', '2 rue Upcycle', 'Paris', '75001', 'France', 'https://picsum.photos/200?2', 'Equipe UpcycleConnect', 'actif', 0, 1, 1, 4, 0),
(3, 'user1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'recycleur92', 'Lucas', 'Martin', '0611223344', '12 rue des Fleurs', 'Paris', '75012', 'France', 'https://picsum.photos/200?1', 'Passionne de recyclage et bricolage', 'actif', 0, 1, 0, 2, 0),
(4, 'pro1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'atelier_pro', 'Camille', 'Bernard', '0644556677', '18 rue des Artisans', 'Nantes', '44000', 'France', 'https://picsum.photos/200?4', 'Professionnel seconde main', 'actif', 0, 1, 1, 3, 0),
(5, 'emp1@test.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'formateur_anna', 'Anna', 'Dupont', '0699001122', '5 rue des Ateliers', 'Lille', '59000', 'France', 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e', 'Animatrice formatrice UpcycleConnect', 'actif', 0, 1, 1, 4, 0),
(6, 'jean.dupont@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'jean_dupont', 'Jean', 'Dupont', '0612345678', '15 rue de la Paix', 'Lyon', '69000', 'France', 'https://picsum.photos/200?5', 'Bricoleur et upcycleur amateur', 'actif', 0, 1, 1, 2, 0),
(7, 'marie.curie@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'marie_upcycle', 'Marie', 'Curie', '0698765432', '8 avenue des Arts', 'Paris', '75010', 'France', 'https://picsum.photos/200?6', 'Passionnee de DIY et upcycling', 'actif', 0, 1, 1, 2, 0),
(8, 'pierre.durand@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'pierre_diy', 'Pierre', 'Durand', '0645123789', '23 rue du Travail', 'Bordeaux', '33000', 'France', 'https://picsum.photos/200?7', 'Artisan ebeniste', 'actif', 0, 1, 1, 3, 0);

INSERT INTO objet (titre, description, etat, type_materiau, poids, volume, photo_url, id_user) VALUES
('Chaise en bois', 'Chaise ancienne a revaloriser', 'bon', 'bois', 7.50, 0.40, 'https://picsum.photos/400?wood1', 4),
('Lampe vintage', 'Lampe a reparer, abat-jour manquant', 'moyen', 'metal', 2.10, 0.15, 'https://picsum.photos/400?lamp1', 4),
('Panneaux bois brut', 'Lots de chutes pour atelier', 'bon', 'bois', 15.00, 1.20, 'https://picsum.photos/400?wood2', 4),
('Table basse', 'Table en bois massif a restaurer', 'a_renover', 'bois', 12.00, 0.50, 'https://picsum.photos/400?table', 4),
('Velo ancien', 'Velo des annees 70 a customiser', 'correct', 'metal', 15.00, 1.20, 'https://picsum.photos/400?velo', 4);

INSERT INTO conteneur (code, adresse, statut, date_installation, derniere_maintenance) VALUES
('C-001', '12 Avenue de France, Paris', 'actif', '2026-01-10', '2026-04-01'),
('C-002', '45 Rue Nationale, Lyon', 'actif', '2026-02-08', '2026-04-20');

INSERT INTO demande_depot (statut, requested_at, validated_at, deposited_at, id_user, id_objet, id_conteneur) VALUES
('retiree', NOW(), NOW(), NOW(), 4, 1, 1),
('validee', NOW(), NOW(), NULL, 4, 2, 2),
('validee', NOW(), NOW(), NULL, 4, 3, 1),
('validee', NOW(), NOW(), NULL, 4, 4, 2);

INSERT INTO code_barre (barcode_value, statut, id_demande, created_at) VALUES 
('3765203685931', 'actif', 1, NOW()),
('3769123456789', 'actif', 2, NOW()),
('3767127856336', 'actif', 3, NOW()),
('3769777510835', 'actif', 4, NOW());

INSERT INTO annonce (mode, prix, statut, validated_at, id_user, id_objet, id_validateur, commission_payee) VALUES
('don', 0, 'validee', NOW(), 4, 1, 1, 0),
('vente', 35.00, 'validee', NOW(), 4, 2, 1, 0),
('vente', 49.00, 'rejetee', NOW(), 5, 3, 1, 0),
('vente', 25.00, 'validee', NOW(), 6, 4, 1, 0);

INSERT INTO categorie_prestation (nom, description) VALUES
('Atelier', 'Ateliers creatifs et recyclage'),
('Reparation', 'Services de reparation'),
('Formation', 'Formations professionnelles');

INSERT INTO prestation (titre, description, type, prix, id_categorie, id_user) VALUES
('Atelier couture', 'Apprendre a reparer des vetements', 'atelier', 20.00, 1, NULL),
('Reparation velo', 'Service reparation velo', 'service', 30.00, 2, NULL),
('Formation bois recycle', 'Formation intensive artisan', 'formation', 120.00, 3, NULL),
('Upcycling meubles', 'Apprenez a transformer vos vieux meubles', 'atelier', 45.00, 1, 5);

INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, id_prestation, id_validateur, id_createur, image_url) VALUES
('2026-06-20 09:00:00', '2026-06-20 17:00:00', 'Lille, 59000', 20, 'valide', 3, 1, 5, 'uploads/events/default.jpg');

INSERT INTO inscription (statut, id_user, id_session) VALUES
('confirmee', 3, 1);

INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, id_inscription, user_id) VALUES
('stripe', 'cs_test_example', 120.00, 'EUR', 'paid', NOW(), 1, 3);

INSERT INTO notification (id_user, type, titre, contenu) VALUES
(1, 'system', 'Back-office', 'Bienvenue sur le panneau admin'),
(3, 'paiement_stripe', '✅ Paiement confirme', 'Votre paiement de 120€ a ete recu.'),
(3, 'annonce', 'Annonce validee', 'Votre annonce a ete validee par l\'administration.'),
(6, 'forum', 'Nouveau sujet', 'Un nouveau sujet a ete cree dans le forum.');

INSERT INTO conseil (titre, contenu, categorie, image_url, is_active) VALUES
('Preparer du bois de palette', 'Poncez, retirez les clous et protegez le bois. Peignez avec des peintures naturelles pour un resultat unique.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', TRUE),
('Entretenir son velo', 'Verifiez pneus, chaine et freins regulierement. Un entretien regulier prolonge la duree de vie de votre velo.', 'Guide', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e', TRUE),
('Recycler ses bocaux', 'Transformez vos bocaux en verre en luminaires ou pots de fleurs uniques.', 'Tutoriel', 'https://images.unsplash.com/photo-1602143407151-7111542de6e8', TRUE);

INSERT INTO projet_upcycling (id_pro, id_objet, titre, description, statut, progression, image_url, is_featured, is_public) VALUES
(4, 1, 'Renovation chaise bistrot', 'Transformation de chaise en piece design.', 'termine', 100, 'https://picsum.photos/600?project1', TRUE, TRUE);

INSERT INTO abonnement_pro (id_pro, formule, date_debut, date_fin, prix, statut) VALUES
(4, 'premium_annuel', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 299.00, 'actif');

INSERT INTO forum_categories (id_category, name, slug, description, sort_order, is_active) VALUES
(1, 'Questions generales', 'questions-generales', 'Echanges communautaires et entraide sur l\'upcycling', 1, 1),
(2, 'Reparation', 'reparation', 'Conseils reparation et maintenance d\'objets', 2, 1),
(3, 'Upcycling', 'upcycling', 'Projets creatifs et reemploi de materiaux', 3, 1),
(4, 'Conteneurs', 'conteneurs', 'Depots, collecte et logistique des conteneurs', 4, 1),
(5, 'Formations', 'formations', 'Ateliers et sessions de formation', 5, 1);

INSERT INTO forum_topics (id_topic, category_id, author_id, title, slug, status, is_pinned, is_locked, is_hidden, views_count, posts_count, last_post_at, created_at) VALUES
(1, 1, 3, 'Comment bien debuter dans l\'upcycling ?', 'comment-bien-debuter-dans-upcycling', 'open', 1, 0, 0, 156, 5, '2026-06-10 15:30:00', '2026-06-01 10:00:00'),
(2, 2, 6, 'Reparer une vieille chaise en bois', 'reparer-une-vieille-chaise-en-bois', 'open', 0, 0, 0, 89, 3, '2026-06-09 18:20:00', '2026-06-03 14:15:00'),
(3, 3, 7, 'Transformation de palettes en mobilier', 'transformation-palettes-mobilier', 'open', 0, 0, 0, 234, 8, '2026-06-10 20:45:00', '2026-06-02 09:30:00'),
(4, 4, 3, 'Question sur les conteneurs de depot', 'question-conteneurs-depot', 'open', 0, 0, 0, 45, 2, '2026-06-08 11:00:00', '2026-06-05 16:20:00'),
(5, 5, 4, 'Formation upcycling pour professionnels', 'formation-upcycling-professionnels', 'open', 0, 0, 0, 67, 4, '2026-06-07 14:30:00', '2026-06-04 08:45:00'),
(6, 1, 8, 'Ou trouver des materiaux gratuits pour upcycler ?', 'ou-trouver-materiaux-gratuits', 'open', 0, 0, 0, 112, 6, '2026-06-09 09:15:00', '2026-06-03 11:00:00'),
(7, 2, 5, 'Reparation d\'un velo ancien', 'reparation-velo-ancien', 'open', 1, 0, 0, 78, 3, '2026-06-08 16:45:00', '2026-06-04 13:30:00'),
(8, 3, 3, 'Creation de luminaires avec des bocaux', 'creation-luminaires-bocaux', 'open', 0, 0, 0, 145, 7, '2026-06-10 12:00:00', '2026-06-01 17:15:00');

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

INSERT INTO forum_reports (post_id, reporter_id, reason, details, status, created_at) VALUES
(2, 3, 'spam', 'Message publicitaire non sollicité', 'pending', NOW()),
(5, 6, 'inappropriate', 'Langage inapproprié', 'pending', NOW()),
(8, 3, 'off_topic', 'Hors sujet par rapport à la discussion', 'reviewed', NOW());

INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason, created_at) VALUES
(1, 'HIDE_POST', 'post', 1, 'Message inapproprié', NOW()),
(5, 'LOCK_TOPIC', 'topic', 1, 'Discussion hors de contrôle', NOW()),
(1, 'handle_report', 'report', 1, 'Signalement traité', NOW()),
(1, 'RESTORE_POST', 'post', 1, 'Message restauré après vérification', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 'UNLOCK_TOPIC', 'topic', 1, 'Sujet rouvert', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO user_warnings (user_id, warning_type, message, issued_by, created_at) VALUES
(3, 'forum', 'Merci de respecter les règles du forum. Évitez le langage inapproprié.', 1, NOW()),
(6, 'forum', 'Attention au spam', 5, DATE_SUB(NOW(), INTERVAL 1 DAY));

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