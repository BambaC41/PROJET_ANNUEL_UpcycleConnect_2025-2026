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
(8, 'pierre.durand@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'pierre_diy', 'Pierre', 'Durand', '0645123789', '23 rue du Travail', 'Bordeaux', '33000', 'France', 'https://picsum.photos/200?7', 'Artisan ebeniste', 'actif', 0, 1, 1, 3, 0),
(9, 'sophie.lefranc@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'sophie_crea', 'Sophie', 'Lefranc', '0612345890', '42 rue des Artistes', 'Lyon', '69002', 'France', 'https://picsum.photos/200?8', 'Creatrice textile et upcycling', 'actif', 0, 1, 1, 2, 0),
(10, 'thomas.leroy@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'thomas_brico', 'Thomas', 'Leroy', '0645789012', '7 rue des Menuisiers', 'Nantes', '44000', 'France', 'https://picsum.photos/200?9', 'Menuisier et upcycleur pro', 'actif', 0, 1, 1, 3, 0),
(11, 'claire.moreau@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'claire_eco', 'Claire', 'Moreau', '0678901234', '15 rue des Ecolo', 'Paris', '75011', 'France', 'https://picsum.photos/200?10', 'Eco-responsable et upcycleuse', 'actif', 0, 1, 1, 2, 0),
(12, 'nicolas.richard@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'nico_artisan', 'Nicolas', 'Richard', '0645123490', '3 rue des Ateliers', 'Bordeaux', '33000', 'France', 'https://picsum.photos/200?11', 'Artisan ferronnier', 'actif', 0, 1, 1, 3, 0),
(13, 'emilie.dubois@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'emilie_diy', 'Emilie', 'Dubois', '0699887766', '22 rue de la Creation', 'Lille', '59000', 'France', 'https://picsum.photos/200?12', 'Passionnee de DIY', 'actif', 0, 1, 1, 2, 0),
(14, 'antoine.girard@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'antoine_bois', 'Antoine', 'Girard', '0622334455', '8 rue du Bois', 'Nantes', '44000', 'France', 'https://picsum.photos/200?13', 'Ebeniste artisanal', 'actif', 0, 1, 1, 3, 0),
(15, 'laura.martin@email.com', '$2a$10$VqrIpSQGvprFNw7NFWsUY.mK45lU.IhqYVbzjsLYPhoEfTyZml9X2', 'laura_recycle', 'Laura', 'Martin', '0678909876', '9 rue du Recyclage', 'Paris', '75013', 'France', 'https://picsum.photos/200?14', 'Upcycleuse et formatrice', 'actif', 0, 1, 1, 4, 0);

INSERT INTO objet (titre, description, etat, type_materiau, poids, volume, photo_url, id_user) VALUES
('Chaise en bois', 'Chaise ancienne a revaloriser', 'bon', 'bois', 7.50, 0.40, 'https://picsum.photos/400?wood1', 4),
('Lampe vintage', 'Lampe a reparer, abat-jour manquant', 'moyen', 'metal', 2.10, 0.15, 'https://picsum.photos/400?lamp1', 4),
('Panneaux bois brut', 'Lots de chutes pour atelier', 'bon', 'bois', 15.00, 1.20, 'https://picsum.photos/400?wood2', 4),
('Table basse', 'Table en bois massif a restaurer', 'a_renover', 'bois', 12.00, 0.50, 'https://picsum.photos/400?table', 4),
('Velo ancien', 'Velo des annees 70 a customiser', 'correct', 'metal', 15.00, 1.20, 'https://picsum.photos/400?velo', 4),
('Porte manteau', 'Porte manteau en metal industriel', 'bon', 'metal', 5.00, 0.30, 'https://picsum.photos/400?coat', 6),
('Brouette', 'Brouette en metal pour jardin', 'moyen', 'metal', 8.00, 0.60, 'https://picsum.photos/400?wheel', 7),
('Tableau ancien', 'Tableau a restaurer cadre en bois', 'a_renover', 'bois', 4.00, 0.20, 'https://picsum.photos/400?painting', 8),
('Comptoir bois', 'Comptoir en bois massif', 'bon', 'bois', 25.00, 1.80, 'https://picsum.photos/400?counter', 9),
('Etagere metal', 'Etagere industrielle en metal', 'bon', 'metal', 12.00, 0.70, 'https://picsum.photos/400?shelf', 10),
('Miroir ancien', 'Miroir avec cadre en bois', 'correct', 'bois', 6.00, 0.35, 'https://picsum.photos/400?mirror', 11),
('Commode', 'Commode en bois massif 3 tiroirs', 'a_renover', 'bois', 30.00, 1.00, 'https://picsum.photos/400?chest', 12),
('Banc jardin', 'Banc en bois pour exterieur', 'moyen', 'bois', 18.00, 0.90, 'https://picsum.photos/400?bench', 13),
('Machine a coudre', 'Machine a coudre ancienne Singer', 'bon', 'metal', 25.00, 0.50, 'https://picsum.photos/400?sewing', 14),
('Palette bois', 'Palette bois 120x80 cm', 'bon', 'bois', 20.00, 1.00, 'https://picsum.photos/400?palette', 15);

INSERT INTO conteneur (code, adresse, statut, date_installation, derniere_maintenance) VALUES
('C-001', '12 Avenue de France, Paris', 'actif', '2026-01-10', '2026-04-01'),
('C-002', '45 Rue Nationale, Lyon', 'actif', '2026-02-08', '2026-04-20'),
('C-003', '78 Rue de la Paix, Lille', 'actif', '2026-03-15', '2026-05-10'),
('C-004', '23 Boulevard Voltaire, Nantes', 'actif', '2026-01-20', '2026-04-15'),
('C-005', '56 Rue Sainte-Catherine, Bordeaux', 'actif', '2026-02-25', '2026-05-05'),
('C-006', '34 Avenue des Champs-Elysees, Paris', 'actif', '2026-03-01', '2026-06-01'),
('C-007', '12 Rue de la Republique, Marseille', 'actif', '2026-01-05', '2026-03-20'),
('C-008', '89 Rue du Faubourg, Toulouse', 'actif', '2026-02-14', '2026-04-28');

INSERT INTO demande_depot (statut, requested_at, validated_at, deposited_at, id_user, id_objet, id_conteneur) VALUES
('retiree', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), 4, 1, 1),
('validee', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), NULL, 4, 2, 2),
('validee', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), NULL, 4, 3, 1),
('validee', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), NULL, 6, 4, 2),
('en_attente', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, NULL, 7, 5, 3),
('retiree', DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY), 8, 6, 4),
('validee', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), NULL, 9, 7, 5),
('en_attente', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL, 10, 8, 6),
('retiree', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 50 DAY), 11, 9, 7),
('validee', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), NULL, 12, 10, 8),
('en_attente', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, 13, 11, 1),
('validee', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), NULL, 14, 12, 2),
('retiree', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), 15, 13, 3),
('validee', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), NULL, 3, 14, 4),
('en_attente', DATE_SUB(NOW(), INTERVAL 4 DAY), NULL, NULL, 5, 15, 5);

INSERT INTO code_barre (barcode_value, statut, id_demande, created_at) VALUES 
('3765203685931', 'actif', 1, NOW()),
('3769123456789', 'actif', 2, NOW()),
('3767127856336', 'actif', 3, NOW()),
('3769777510835', 'actif', 4, NOW()),
('3765234567891', 'actif', 5, NOW()),
('3765345678912', 'actif', 6, NOW()),
('3765456789123', 'actif', 7, NOW()),
('3765567891234', 'actif', 8, NOW()),
('3765678912345', 'actif', 9, NOW()),
('3765789123456', 'actif', 10, NOW());

INSERT INTO annonce (mode, prix, statut, validated_at, created_at, id_user, id_objet, id_validateur, commission_payee) VALUES
('don', 0, 'validee', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY), 4, 1, 1, 0),
('vente', 35.00, 'validee', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), 4, 2, 1, 1),
('vente', 49.00, 'rejetee', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), 5, 3, 1, 0),
('vente', 25.00, 'validee', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), 6, 4, 1, 0),
('don', 0, 'validee', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), 7, 5, 1, 0),
('vente', 55.00, 'en_attente', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), 8, 6, NULL, 0),
('don', 0, 'validee', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), 9, 7, 1, 0),
('vente', 120.00, 'en_attente', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 10, 8, NULL, 0),
('vente', 45.00, 'validee', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), 11, 9, 1, 1),
('don', 0, 'validee', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 12, 10, 1, 0),
('vente', 75.00, 'rejetee', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), 13, 11, 1, 0),
('vente', 30.00, 'validee', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), 14, 12, 1, 1);

INSERT INTO categorie_prestation (nom, description) VALUES
('Atelier', 'Ateliers creatifs et recyclage'),
('Reparation', 'Services de reparation'),
('Formation', 'Formations professionnelles'),
('Conference', 'Conferences et evenements'),
('Consulting', 'Conseil en upcycling');

INSERT INTO prestation (titre, description, type, prix, is_active, id_categorie, id_user) VALUES
('Atelier couture', 'Apprendre a reparer des vetements', 'atelier', 20.00, 1, 1, NULL),
('Reparation velo', 'Service reparation velo', 'service', 30.00, 1, 2, NULL),
('Formation bois recycle', 'Formation intensive artisan', 'formation', 120.00, 1, 3, NULL),
('Upcycling meubles', 'Apprenez a transformer vos vieux meubles', 'atelier', 45.00, 1, 1, 5),
('Conference upcycling', 'Conference sur l upcycling en entreprise', 'conference', 85.00, 1, 4, NULL),
('Consulting upcycling', 'Conseil en upcycling pour entreprises', 'consulting', 150.00, 1, 5, NULL),
('Atelier palettes', 'Creation de meubles avec des palettes', 'atelier', 35.00, 1, 1, 5),
('Formation textile', 'Recyclage textile et creation', 'formation', 95.00, 1, 3, 15);

INSERT INTO session (date_debut, date_fin, lieu, capacite_max, statut, created_at, id_prestation, id_validateur, id_createur, image_url) VALUES
('2026-06-20 09:00:00', '2026-06-20 17:00:00', 'Lille, 59000', 20, 'valide', '2026-05-20 10:00:00', 3, 1, 5, 'uploads/events/default.jpg'),
('2026-07-05 14:00:00', '2026-07-05 18:00:00', 'Paris, 75001', 15, 'valide', '2026-06-01 09:00:00', 1, 1, 2, 'uploads/events/default.jpg'),
('2026-07-15 09:00:00', '2026-07-15 16:00:00', 'Nantes, 44000', 12, 'en_attente', '2026-06-10 14:00:00', 4, NULL, 5, 'uploads/events/default.jpg'),
('2026-07-25 10:00:00', '2026-07-25 13:00:00', 'Lyon, 69002', 10, 'valide', '2026-06-15 08:00:00', 2, 1, 15, 'uploads/events/default.jpg'),
('2026-08-01 09:00:00', '2026-08-01 18:00:00', 'Bordeaux, 33000', 25, 'valide', '2026-06-20 11:00:00', 5, 1, 2, 'uploads/events/default.jpg'),
('2026-08-10 10:00:00', '2026-08-10 16:00:00', 'Paris, 75011', 8, 'en_attente', '2026-07-01 12:00:00', 6, NULL, 15, 'uploads/events/default.jpg'),
('2026-08-20 09:00:00', '2026-08-20 17:00:00', 'Lille, 59000', 18, 'valide', '2026-07-10 09:00:00', 7, 1, 5, 'uploads/events/default.jpg'),
('2026-09-01 14:00:00', '2026-09-01 18:00:00', 'Nantes, 44000', 10, 'valide', '2026-07-15 10:00:00', 1, 1, 2, 'uploads/events/default.jpg'),
('2026-09-15 09:00:00', '2026-09-15 16:00:00', 'Lyon, 69002', 20, 'valide', '2026-08-01 13:00:00', 3, 1, 5, 'uploads/events/default.jpg'),
('2026-09-25 10:00:00', '2026-09-25 13:00:00', 'Paris, 75001', 12, 'valide', '2026-08-10 08:00:00', 4, 1, 15, 'uploads/events/default.jpg'),
('2026-10-05 09:00:00', '2026-10-05 17:00:00', 'Bordeaux, 33000', 15, 'en_attente', '2026-08-20 11:00:00', 2, NULL, 15, 'uploads/events/default.jpg'),
('2026-10-15 10:00:00', '2026-10-15 16:00:00', 'Lille, 59000', 10, 'valide', '2026-09-01 12:00:00', 5, 1, 2, 'uploads/events/default.jpg'),
('2026-10-25 09:00:00', '2026-10-25 17:00:00', 'Nantes, 44000', 20, 'valide', '2026-09-10 09:00:00', 7, 1, 5, 'uploads/events/default.jpg'),
('2026-11-01 14:00:00', '2026-11-01 18:00:00', 'Lyon, 69002', 8, 'en_attente', '2026-09-20 10:00:00', 6, NULL, 15, 'uploads/events/default.jpg'),
('2026-11-15 09:00:00', '2026-11-15 16:00:00', 'Paris, 75011', 15, 'valide', '2026-10-01 14:00:00', 1, 1, 2, 'uploads/events/default.jpg');

INSERT INTO inscription (statut, created_at, id_user, id_session) VALUES
('confirmee', DATE_SUB(NOW(), INTERVAL 15 DAY), 3, 1),
('confirmee', DATE_SUB(NOW(), INTERVAL 10 DAY), 6, 1),
('confirmee', DATE_SUB(NOW(), INTERVAL 8 DAY), 7, 1),
('confirmee', DATE_SUB(NOW(), INTERVAL 12 DAY), 9, 2),
('confirmee', DATE_SUB(NOW(), INTERVAL 5 DAY), 10, 2),
('confirmee', DATE_SUB(NOW(), INTERVAL 7 DAY), 11, 3),
('en_attente', DATE_SUB(NOW(), INTERVAL 3 DAY), 12, 3),
('confirmee', DATE_SUB(NOW(), INTERVAL 6 DAY), 13, 4),
('confirmee', DATE_SUB(NOW(), INTERVAL 4 DAY), 14, 4),
('confirmee', DATE_SUB(NOW(), INTERVAL 9 DAY), 15, 5),
('confirmee', DATE_SUB(NOW(), INTERVAL 2 DAY), 3, 5),
('confirmee', DATE_SUB(NOW(), INTERVAL 11 DAY), 6, 6),
('confirmee', DATE_SUB(NOW(), INTERVAL 1 DAY), 7, 7),
('confirmee', DATE_SUB(NOW(), INTERVAL 14 DAY), 8, 8),
('confirmee', DATE_SUB(NOW(), INTERVAL 13 DAY), 9, 8),
('confirmee', DATE_SUB(NOW(), INTERVAL 3 DAY), 10, 9),
('confirmee', DATE_SUB(NOW(), INTERVAL 5 DAY), 11, 9),
('confirmee', DATE_SUB(NOW(), INTERVAL 7 DAY), 12, 10),
('confirmee', DATE_SUB(NOW(), INTERVAL 4 DAY), 13, 11),
('confirmee', DATE_SUB(NOW(), INTERVAL 6 DAY), 14, 12);

INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, created_at, id_inscription, user_id) VALUES
('stripe', 'cs_test_001', 120.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), 1, 3),
('stripe', 'cs_test_002', 20.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), 4, 9),
('stripe', 'cs_test_003', 45.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), 6, 11),
('stripe', 'cs_test_004', 30.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), 8, 13),
('stripe', 'cs_test_005', 120.00, 'EUR', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), 10, 15),
('stripe', 'cs_test_006', 85.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), 12, 6),
('stripe', 'cs_test_007', 35.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 13, 7),
('stripe', 'cs_test_008', 20.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 14, 8),
('stripe', 'cs_test_009', 45.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 16, 10),
('stripe', 'cs_test_010', 120.00, 'EUR', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 18, 12),
('stripe', 'cs_test_011', 30.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), 19, 13),
('stripe', 'cs_test_012', 35.00, 'EUR', 'paid', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 20, 14);

INSERT INTO notification (id_user, type, titre, contenu, created_at) VALUES
(1, 'system', 'Back-office', 'Bienvenue sur le panneau admin', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(3, 'paiement_stripe', 'Paiement confirme', 'Votre paiement de 120€ a ete recu.', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 'annonce', 'Annonce validee', 'Votre annonce a ete validee par l\'administration.', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(6, 'forum', 'Nouveau sujet', 'Un nouveau sujet a ete cree dans le forum.', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(7, 'conseil', 'Nouveau conseil', 'Un nouveau conseil est disponible.', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(8, 'paiement_stripe', 'Paiement confirme', 'Votre paiement de 35€ a ete recu.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 'inscription', 'Inscription confirmee', 'Votre inscription a l\'atelier est confirmee.', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(10, 'annonce', 'Annonce validee', 'Votre annonce a ete validee.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(11, 'evenement', 'Evenement a venir', 'Un evenement approche.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(12, 'forum', 'Reponse au sujet', 'Une nouvelle reponse a ete postee.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(13, 'paiement_stripe', 'Paiement confirme', 'Votre paiement de 45€ a ete recu.', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(14, 'inscription', 'Inscription confirmee', 'Votre inscription est confirmee.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(15, 'conseil', 'Conseil publie', 'Votre conseil a ete publie.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'evenement', 'Evenement annule', 'Un evenement a ete annule.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(4, 'system', 'Bienvenue', 'Bienvenue sur la plateforme UpcycleConnect.', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(5, 'forum', 'Nouveau message', 'Un nouveau message a ete poste dans le forum.', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 'paiement_stripe', 'Paiement recu', 'Paiement de 85€ recu avec succes.', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 'annonce', 'Annonce rejetee', 'Votre annonce a ete rejetee.', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(8, 'inscription', 'Rappel evenement', 'Rappel : evenement dans 3 jours.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(9, 'conseil', 'Nouveau conseil', 'Un nouveau conseil est disponible.', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO conseil (titre, contenu, categorie, image_url, is_active, id_auteur, created_at) VALUES
('Preparer du bois de palette', 'Poncez, retirez les clous et protegez le bois. Peignez avec des peintures naturelles pour un resultat unique.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', 1, 5, DATE_SUB(NOW(), INTERVAL 30 DAY)),
('Entretenir son velo', 'Verifiez pneus, chaine et freins regulierement. Un entretien regulier prolonge la duree de vie de votre velo.', 'Guide', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e', 1, 5, DATE_SUB(NOW(), INTERVAL 25 DAY)),
('Recycler ses bocaux', 'Transformez vos bocaux en verre en luminaires ou pots de fleurs uniques.', 'Tutoriel', 'https://images.unsplash.com/photo-1602143407151-7111542de6e8', 1, 15, DATE_SUB(NOW(), INTERVAL 20 DAY)),
('Upcycling textile', 'Recyclez vos vieux vetements en accessoires originaux.', 'Tutoriel', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab', 1, 5, DATE_SUB(NOW(), INTERVAL 15 DAY)),
('Creation luminaires', 'Fabriquez des luminaires a partir de materiaux recuperes.', 'Guide', 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d', 1, 15, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('Bois flotte', 'Techniques de travail du bois flotte pour des creations uniques.', 'Tutoriel', 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09', 1, 5, DATE_SUB(NOW(), INTERVAL 8 DAY)),
('Peinture ecologique', 'Fabriquez vos peintures naturelles pour vos projets.', 'Guide', 'https://images.unsplash.com/photo-1581147036324-c1c89a3e3c4b', 1, 15, DATE_SUB(NOW(), INTERVAL 5 DAY)),
('Mobilier palette', 'Idees et plans pour creer des meubles avec des palettes.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', 1, 5, DATE_SUB(NOW(), INTERVAL 3 DAY)),
('Jardin vertical', 'Creez un jardin vertical avec des bouteilles recyclees.', 'Tutoriel', 'https://images.unsplash.com/photo-1602143407151-7111542de6e8', 1, 15, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Recyclage plastique', 'Comment recycler le plastique a la maison.', 'Guide', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab', 1, 5, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Upcycling metal', 'Techniques de transformation du metal recupere.', 'Tutoriel', 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d', 1, 5, DATE_SUB(NOW(), INTERVAL 7 DAY)),
('Creation bielles', 'Fabriquez des bijoux a partir de pieces recyclees.', 'Tutoriel', 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09', 1, 15, DATE_SUB(NOW(), INTERVAL 4 DAY)),
('Deco murale', 'Idees de decoration murale avec des materiaux de recuperation.', 'Guide', 'https://images.unsplash.com/photo-1581147036324-c1c89a3e3c4b', 1, 5, DATE_SUB(NOW(), INTERVAL 6 DAY)),
('Recyclage electronique', 'Transformez vos vieux appareils electroniques en objets decoratifs.', 'Tutoriel', 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4', 1, 15, DATE_SUB(NOW(), INTERVAL 9 DAY)),
('Upcycling papier', 'Creez des objets a partir de vieux papiers et cartons.', 'Tutoriel', 'https://images.unsplash.com/photo-1602143407151-7111542de6e8', 0, 5, DATE_SUB(NOW(), INTERVAL 11 DAY));

INSERT INTO projet_upcycling (id_pro, id_objet, titre, description, statut, progression, image_url, is_featured, is_public, created_at) VALUES
(4, 1, 'Renovation chaise bistrot', 'Transformation de chaise en piece design.', 'termine', 100, 'https://picsum.photos/600?project1', 1, 1, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(6, 4, 'Table basse industrielle', 'Creation d une table basse avec des palettes et tubes metal.', 'termine', 100, 'https://picsum.photos/600?project2', 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(7, 5, 'Velo customise', 'Transformation d un velo en piece murale design.', 'termine', 100, 'https://picsum.photos/600?project3', 1, 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(8, 6, 'Porte manteau industriel', 'Creation d un porte manteau en metal recupere.', 'termine', 100, 'https://picsum.photos/600?project4', 1, 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(9, 7, 'Brouette jardin', 'Transformation d une brouette en bac a fleurs.', 'termine', 100, 'https://picsum.photos/600?project5', 1, 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(10, 8, 'Cadre tableau ancien', 'Restauration et modernisation d un cadre ancien.', 'termine', 100, 'https://picsum.photos/600?project6', 1, 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(11, 9, 'Comptoir bois massif', 'Creation d un comptoir en bois massif pour cuisine.', 'en_cours', 60, 'https://picsum.photos/600?project7', 1, 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(12, 10, 'Etagere industrielle', 'Creation d une etagere en metal avec finition rouille.', 'en_cours', 40, 'https://picsum.photos/600?project8', 0, 1, DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO abonnement_pro (id_pro, formule, date_debut, date_fin, prix, statut) VALUES
(4, 'premium_annuel', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 299.00, 'actif'),
(6, 'premium_mensuel', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 29.00, 'actif'),
(8, 'premium_annuel', DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 299.00, 'actif'),
(10, 'premium_mensuel', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 11 MONTH), 29.00, 'actif'),
(12, 'premium_annuel', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 9 MONTH), 299.00, 'actif');

INSERT INTO forum_categories (id_category, name, slug, description, sort_order, is_active) VALUES
(1, 'Questions generales', 'questions-generales', 'Echanges communautaires et entraide sur l upcycling', 1, 1),
(2, 'Reparation', 'reparation', 'Conseils reparation et maintenance d objets', 2, 1),
(3, 'Upcycling', 'upcycling', 'Projets creatifs et reemploi de materiaux', 3, 1),
(4, 'Conteneurs', 'conteneurs', 'Depots, collecte et logistique des conteneurs', 4, 1),
(5, 'Formations', 'formations', 'Ateliers et sessions de formation', 5, 1),
(6, 'Outillage', 'outillage', 'Discussions sur l outillage et materiels', 6, 1),
(7, 'Fabrication', 'fabrication', 'Creation et fabrication d objets', 7, 1),
(8, 'Echanges', 'echanges', 'Echanges de materiaux et d idees', 8, 1);

INSERT INTO forum_topics (id_topic, category_id, author_id, title, slug, status, is_pinned, is_locked, is_hidden, views_count, posts_count, last_post_at, created_at) VALUES
(1, 1, 3, 'Comment bien debuter dans l upcycling ?', 'comment-bien-debuter-dans-upcycling', 'open', 1, 0, 0, 156, 5, '2026-06-10 15:30:00', '2026-06-01 10:00:00'),
(2, 2, 6, 'Reparer une vieille chaise en bois', 'reparer-une-vieille-chaise-en-bois', 'open', 0, 0, 0, 89, 3, '2026-06-09 18:20:00', '2026-06-03 14:15:00'),
(3, 3, 7, 'Transformation de palettes en mobilier', 'transformation-palettes-mobilier', 'open', 0, 0, 0, 234, 8, '2026-06-10 20:45:00', '2026-06-02 09:30:00'),
(4, 4, 3, 'Question sur les conteneurs de depot', 'question-conteneurs-depot', 'open', 0, 0, 0, 45, 2, '2026-06-08 11:00:00', '2026-06-05 16:20:00'),
(5, 5, 4, 'Formation upcycling pour professionnels', 'formation-upcycling-professionnels', 'open', 0, 0, 0, 67, 4, '2026-06-07 14:30:00', '2026-06-04 08:45:00'),
(6, 1, 8, 'Ou trouver des materiaux gratuits pour upcycler ?', 'ou-trouver-materiaux-gratuits', 'open', 0, 0, 0, 112, 6, '2026-06-09 09:15:00', '2026-06-03 11:00:00'),
(7, 2, 5, 'Reparation d un velo ancien', 'reparation-velo-ancien', 'open', 1, 0, 0, 78, 3, '2026-06-08 16:45:00', '2026-06-04 13:30:00'),
(8, 3, 3, 'Creation de luminaires avec des bocaux', 'creation-luminaires-bocaux', 'open', 0, 0, 0, 145, 7, '2026-06-10 12:00:00', '2026-06-01 17:15:00'),
(9, 6, 9, 'Quel outillage pour debuter ?', 'quel-outillage-pour-debuter', 'open', 0, 0, 0, 56, 3, '2026-06-11 09:00:00', '2026-06-06 08:00:00'),
(10, 7, 10, 'Creation de meubles avec des palettes', 'creation-meubles-palettes', 'open', 0, 0, 0, 98, 5, '2026-06-12 14:00:00', '2026-06-07 10:00:00'),
(11, 8, 11, 'Echange de materiaux', 'echange-materiaux', 'open', 0, 0, 0, 34, 2, '2026-06-10 11:00:00', '2026-06-08 09:30:00'),
(12, 6, 12, 'Affutage et entretien des outils', 'affutage-entretien-outils', 'open', 0, 0, 0, 42, 3, '2026-06-11 16:00:00', '2026-06-09 14:00:00'),
(13, 7, 13, 'Projet etabli bois', 'projet-etabli-bois', 'open', 0, 0, 0, 67, 4, '2026-06-12 10:00:00', '2026-06-10 08:30:00'),
(14, 3, 14, 'Upcycling de vetements', 'upcycling-vetements', 'open', 0, 0, 0, 78, 5, '2026-06-13 12:00:00', '2026-06-11 09:00:00'),
(15, 4, 15, 'Nouveaux conteneurs a Nantes', 'nouveaux-conteneurs-nantes', 'open', 0, 0, 0, 23, 1, '2026-06-12 15:00:00', '2026-06-12 15:00:00'),
(16, 5, 3, 'Formation couture en ligne', 'formation-couture-ligne', 'open', 0, 0, 0, 56, 3, '2026-06-13 14:00:00', '2026-06-13 10:00:00'),
(17, 1, 6, 'Recherche de fournisseurs de bois', 'recherche-fournisseurs-bois', 'open', 0, 0, 0, 45, 2, '2026-06-14 09:00:00', '2026-06-14 08:00:00'),
(18, 2, 7, 'Reparation d une commode ancienne', 'reparation-commode-ancienne', 'open', 0, 0, 0, 34, 2, '2026-06-15 10:00:00', '2026-06-15 09:00:00'),
(19, 3, 8, 'Transformation de verres en luminaires', 'transformation-verres-luminaires', 'open', 0, 0, 0, 56, 3, '2026-06-16 11:00:00', '2026-06-16 10:00:00'),
(20, 6, 9, 'Quelle ponceuse choisir ?', 'quelle-ponceuse-choisir', 'open', 0, 0, 0, 67, 4, '2026-06-17 14:00:00', '2026-06-17 12:00:00'),
(21, 7, 10, 'Construction d une serre en palette', 'construction-serre-palette', 'open', 0, 0, 0, 89, 5, '2026-06-18 16:00:00', '2026-06-18 09:00:00'),
(22, 8, 11, 'Echange de lampes vintage', 'echange-lampes-vintage', 'open', 0, 0, 0, 23, 1, '2026-06-19 10:00:00', '2026-06-19 09:00:00'),
(23, 5, 12, 'Formation menuiserie en ligne', 'formation-menuiserie-ligne', 'open', 0, 0, 0, 45, 2, '2026-06-20 11:00:00', '2026-06-20 10:00:00'),
(24, 1, 13, 'Conseils pour debuter le DIY', 'conseils-debuter-diy', 'open', 1, 0, 0, 112, 6, '2026-06-21 14:00:00', '2026-06-21 08:00:00'),
(25, 2, 14, 'Reparation d une machine a coudre', 'reparation-machine-coudre', 'open', 0, 0, 0, 56, 3, '2026-06-22 16:00:00', '2026-06-22 09:00:00');

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
(14, 3, 8, 'Hâte de voir le résultat, poste des photos quand tu auras fini !', '2026-06-10 20:45:00'),
(15, 4, 3, 'Comment fonctionnent les conteneurs de dépôt ? Est-ce que je peux déposer n\'importe quel objet ?', '2026-06-05 16:20:00'),
(16, 4, 5, 'Les conteneurs sont pour les objets recyclables et upcyclables. Il faut d\'abord faire une demande.', '2026-06-08 11:00:00'),
(17, 5, 4, 'Je suis professionnel, quelles formations sont disponibles pour mon équipe ?', '2026-06-04 08:45:00'),
(18, 5, 1, 'Nous proposons des formations sur mesure pour les pros. Contactez-nous pour plus d\'infos.', '2026-06-07 14:30:00'),
(19, 6, 8, 'Où trouver du bois de palette gratuitement ?', '2026-06-03 11:00:00'),
(20, 6, 9, 'Tu peux en trouver dans les zones industrielles ou demander à des magasins.', '2026-06-09 09:15:00'),
(21, 7, 5, 'J\'ai un vieux vélo à restaurer, par où commencer ?', '2026-06-04 13:30:00'),
(22, 7, 3, 'Commence par le démonter complètement, nettoie chaque pièce, puis remonte.', '2026-06-08 16:45:00'),
(23, 8, 3, 'Je vais créer des luminaires avec des bocaux en verre, des conseils ?', '2026-06-01 17:15:00'),
(24, 8, 7, 'Utilise des ampoules LED pour éviter la chaleur et percer le couvercle pour passer le câble.', '2026-06-10 12:00:00'),
(25, 9, 9, 'Je debute, quel outillage acheter en premier ?', '2026-06-06 08:00:00'),
(26, 9, 10, 'Une ponceuse, un marteau, des tournevis et une scie sauteuse sont un bon début.', '2026-06-11 09:00:00'),
(27, 10, 10, 'Je veux construire une bibliothèque en palettes, des plans ?', '2026-06-07 10:00:00'),
(28, 10, 11, 'J\'ai fait une bibliothèque avec 3 palettes, je peux t\'envoyer les photos.', '2026-06-12 14:00:00'),
(29, 11, 11, 'Je cherche du bois de palette, quelqu\'un en a à donner ?', '2026-06-08 09:30:00'),
(30, 11, 12, 'J\'ai quelques palettes en trop, contacte-moi.', '2026-06-10 11:00:00'),
(31, 12, 12, 'Comment affûter correctement un ciseau à bois ?', '2026-06-09 14:00:00'),
(32, 12, 13, 'Utilise une pierre à eau et respecte l\'angle d\'affûtage.', '2026-06-11 16:00:00'),
(33, 13, 13, 'Je construis un établi de menuisier, des conseils ?', '2026-06-10 08:30:00'),
(34, 13, 14, 'Assure-toi que le plateau soit bien plat et robuste.', '2026-06-12 10:00:00'),
(35, 14, 14, 'Je veux upcycler des vieux jeans, des idées ?', '2026-06-11 09:00:00'),
(36, 14, 15, 'Tu peux en faire des sacs, des coussins ou des pochettes.', '2026-06-13 12:00:00'),
(37, 15, 15, 'De nouveaux conteneurs vont être installés à Nantes, où seront-ils ?', '2026-06-12 15:00:00'),
(38, 16, 3, 'Y a-t-il une formation en ligne pour apprendre la couture ?', '2026-06-13 10:00:00'),
(39, 16, 5, 'Oui, une formation débutant est disponible sur la plateforme.', '2026-06-13 14:00:00'),
(40, 17, 6, 'Où trouver des fournisseurs de bois de récupération ?', '2026-06-14 08:00:00'),
(41, 17, 7, 'Il y a plusieurs scieries qui vendent des chutes.', '2026-06-14 09:00:00'),
(42, 18, 7, 'Ma commode ancienne est abîmée, comment la restaurer ?', '2026-06-15 09:00:00'),
(43, 18, 8, 'Dépose d\'abord les vieux vernis, puis ponce et applique une nouvelle finition.', '2026-06-15 10:00:00'),
(44, 19, 8, 'Je vais faire des luminaires avec de vieux verres, des conseils ?', '2026-06-16 10:00:00'),
(45, 19, 9, 'Perce délicatement le fond des verres et utilise une douille adaptée.', '2026-06-16 11:00:00'),
(46, 20, 9, 'Quelle ponceuse est la meilleure pour le bois ?', '2026-06-17 12:00:00'),
(47, 20, 10, 'La ponceuse excentrique est très polyvalente.', '2026-06-17 14:00:00'),
(48, 21, 10, 'Je veux construire une serre en palettes, est-ce possible ?', '2026-06-18 09:00:00'),
(49, 21, 11, 'Oui, beaucoup de gens en construisent, il faut juste bien traiter le bois.', '2026-06-18 16:00:00'),
(50, 22, 11, 'Je cherche des lampes vintage pour les upcycler, quelqu\'un en a ?', '2026-06-19 09:00:00'),
(51, 22, 12, 'J\'ai 2 vieilles lampes à donner, contacte-moi.', '2026-06-19 10:00:00'),
(52, 23, 12, 'Y a-t-il une formation en ligne pour apprendre la menuiserie ?', '2026-06-20 10:00:00'),
(53, 23, 13, 'Oui, je l\'ai suivie et elle est très complète.', '2026-06-20 11:00:00'),
(54, 24, 13, 'Quels sont les meilleurs conseils pour débuter le DIY ?', '2026-06-21 08:00:00'),
(55, 24, 14, 'Commence par des projets simples et investis dans du bon matériel.', '2026-06-21 14:00:00'),
(56, 25, 14, 'Ma machine à coudre ancienne ne fonctionne plus, comment la réparer ?', '2026-06-22 09:00:00'),
(57, 25, 15, 'Vérifie d\'abord l\'aiguille et le fil, puis nettoie le mécanisme.', '2026-06-22 16:00:00');

INSERT INTO forum_reports (post_id, reporter_id, reason, details, status, created_at) VALUES
(2, 3, 'spam', 'Message publicitaire non sollicité', 'pending', NOW()),
(5, 6, 'inappropriate', 'Langage inapproprié', 'pending', NOW()),
(8, 3, 'off_topic', 'Hors sujet par rapport à la discussion', 'reviewed', NOW()),
(15, 7, 'spam', 'Contenu promotionnel', 'pending', NOW()),
(20, 8, 'inappropriate', 'Propos insultants', 'reviewed', NOW()),
(25, 9, 'off_topic', 'Hors sujet', 'pending', NOW()),
(30, 10, 'spam', 'Publicité non sollicitée', 'pending', NOW()),
(35, 11, 'inappropriate', 'Langage vulgaire', 'reviewed', NOW()),
(40, 12, 'off_topic', 'Sujet non lié à l upcycling', 'pending', NOW()),
(45, 13, 'spam', 'Lien promotionnel', 'pending', NOW());

INSERT INTO forum_moderation_logs (moderator_id, action, target_type, target_id, reason, created_at) VALUES
(1, 'HIDE_POST', 'post', 1, 'Message inapproprié', NOW()),
(5, 'LOCK_TOPIC', 'topic', 1, 'Discussion hors de contrôle', NOW()),
(1, 'handle_report', 'report', 1, 'Signalement traité', NOW()),
(1, 'RESTORE_POST', 'post', 1, 'Message restauré après vérification', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 'UNLOCK_TOPIC', 'topic', 1, 'Sujet rouvert', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'HIDE_POST', 'post', 5, 'Message signalé', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'LOCK_TOPIC', 'topic', 3, 'Trop de hors-sujet', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 'handle_report', 'report', 2, 'Signalement traité', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(5, 'UNLOCK_TOPIC', 'topic', 3, 'Sujet rouvert', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'RESTORE_POST', 'post', 5, 'Message restauré', DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO user_warnings (user_id, warning_type, message, issued_by, created_at) VALUES
(3, 'forum', 'Merci de respecter les règles du forum. Évitez le langage inapproprié.', 1, NOW()),
(6, 'forum', 'Attention au spam', 5, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 'forum', 'Premier avertissement pour comportement inapproprié.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 'forum', 'Spam sur plusieurs sujets.', 5, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(9, 'forum', 'Langage vulgaire signalé par plusieurs membres.', 1, DATE_SUB(NOW(), INTERVAL 7 DAY));

UPDATE forum_topics SET posts_count = 5 WHERE id_topic = 1;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 2;
UPDATE forum_topics SET posts_count = 8 WHERE id_topic = 3;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 4;
UPDATE forum_topics SET posts_count = 4 WHERE id_topic = 5;
UPDATE forum_topics SET posts_count = 6 WHERE id_topic = 6;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 7;
UPDATE forum_topics SET posts_count = 7 WHERE id_topic = 8;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 9;
UPDATE forum_topics SET posts_count = 5 WHERE id_topic = 10;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 11;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 12;
UPDATE forum_topics SET posts_count = 4 WHERE id_topic = 13;
UPDATE forum_topics SET posts_count = 5 WHERE id_topic = 14;
UPDATE forum_topics SET posts_count = 1 WHERE id_topic = 15;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 16;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 17;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 18;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 19;
UPDATE forum_topics SET posts_count = 4 WHERE id_topic = 20;
UPDATE forum_topics SET posts_count = 5 WHERE id_topic = 21;
UPDATE forum_topics SET posts_count = 1 WHERE id_topic = 22;
UPDATE forum_topics SET posts_count = 2 WHERE id_topic = 23;
UPDATE forum_topics SET posts_count = 6 WHERE id_topic = 24;
UPDATE forum_topics SET posts_count = 3 WHERE id_topic = 25;

UPDATE forum_topics SET last_post_at = '2026-06-10 15:30:00' WHERE id_topic = 1;
UPDATE forum_topics SET last_post_at = '2026-06-09 18:20:00' WHERE id_topic = 2;
UPDATE forum_topics SET last_post_at = '2026-06-10 20:45:00' WHERE id_topic = 3;
UPDATE forum_topics SET last_post_at = '2026-06-08 11:00:00' WHERE id_topic = 4;
UPDATE forum_topics SET last_post_at = '2026-06-07 14:30:00' WHERE id_topic = 5;
UPDATE forum_topics SET last_post_at = '2026-06-09 09:15:00' WHERE id_topic = 6;
UPDATE forum_topics SET last_post_at = '2026-06-08 16:45:00' WHERE id_topic = 7;
UPDATE forum_topics SET last_post_at = '2026-06-10 12:00:00' WHERE id_topic = 8;
UPDATE forum_topics SET last_post_at = '2026-06-11 09:00:00' WHERE id_topic = 9;
UPDATE forum_topics SET last_post_at = '2026-06-12 14:00:00' WHERE id_topic = 10;
UPDATE forum_topics SET last_post_at = '2026-06-10 11:00:00' WHERE id_topic = 11;
UPDATE forum_topics SET last_post_at = '2026-06-11 16:00:00' WHERE id_topic = 12;
UPDATE forum_topics SET last_post_at = '2026-06-12 10:00:00' WHERE id_topic = 13;
UPDATE forum_topics SET last_post_at = '2026-06-13 12:00:00' WHERE id_topic = 14;
UPDATE forum_topics SET last_post_at = '2026-06-12 15:00:00' WHERE id_topic = 15;
UPDATE forum_topics SET last_post_at = '2026-06-13 14:00:00' WHERE id_topic = 16;
UPDATE forum_topics SET last_post_at = '2026-06-14 09:00:00' WHERE id_topic = 17;
UPDATE forum_topics SET last_post_at = '2026-06-15 10:00:00' WHERE id_topic = 18;
UPDATE forum_topics SET last_post_at = '2026-06-16 11:00:00' WHERE id_topic = 19;
UPDATE forum_topics SET last_post_at = '2026-06-17 14:00:00' WHERE id_topic = 20;
UPDATE forum_topics SET last_post_at = '2026-06-18 16:00:00' WHERE id_topic = 21;
UPDATE forum_topics SET last_post_at = '2026-06-19 10:00:00' WHERE id_topic = 22;
UPDATE forum_topics SET last_post_at = '2026-06-20 11:00:00' WHERE id_topic = 23;
UPDATE forum_topics SET last_post_at = '2026-06-21 14:00:00' WHERE id_topic = 24;
UPDATE forum_topics SET last_post_at = '2026-06-22 16:00:00' WHERE id_topic = 25;

SET FOREIGN_KEY_CHECKS = 1;
