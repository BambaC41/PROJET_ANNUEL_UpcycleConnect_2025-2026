SELECT DATABASE();
SHOW TABLES;

DESCRIBE utilisateur;
DESCRIBE annonce;
DESCRIBE conseil;
DESCRIBE session;
DESCRIBE paiement;
DESCRIBE notification;
DESCRIBE document_genere;
DESCRIBE facture;
DESCRIBE projet_upcycling;
DESCRIBE projet_etape;
DESCRIBE abonnement_pro;
DESCRIBE campagne_publicitaire;
DESCRIBE forum_categories;
DESCRIBE forum_topics;
DESCRIBE forum_posts;
DESCRIBE forum_reports;
DESCRIBE forum_moderation_logs;
DESCRIBE audit_log;

SELECT id_role, code, libelle FROM role ORDER BY id_role;

SELECT email, id_role, is_approved, is_banned, tutorial_completed
FROM utilisateur
ORDER BY id_user;

SELECT COUNT(*) AS roles_count FROM role;
-- Attendu : roles_count = 4 (ADMIN, USER/Particulier, PRO, SALARIE)
SELECT COUNT(*) AS users_count FROM utilisateur;
SELECT COUNT(*) AS annonces_count FROM annonce;
SELECT COUNT(*) AS demandes_count FROM demande_depot;
SELECT COUNT(*) AS notifications_count FROM notification;
SELECT COUNT(*) AS documents_count FROM document_genere;
SELECT COUNT(*) AS factures_count FROM facture;
SELECT COUNT(*) AS projets_count FROM projet_upcycling;
SELECT COUNT(*) AS forum_categories_count FROM forum_categories;
SELECT COUNT(*) AS forum_topics_count FROM forum_topics;
SELECT COUNT(*) AS forum_posts_count FROM forum_posts;
SELECT COUNT(*) AS forum_reports_count FROM forum_reports;
SELECT COUNT(*) AS audit_count FROM audit_log;

SELECT statut, COUNT(*) AS n FROM annonce GROUP BY statut;
SELECT statut, COUNT(*) AS n FROM demande_depot GROUP BY statut;
SELECT statut, COUNT(*) AS n FROM session GROUP BY statut;
SELECT statut, COUNT(*) AS n FROM inscription GROUP BY statut;
SELECT status, COUNT(*) AS n FROM paiement GROUP BY status;

