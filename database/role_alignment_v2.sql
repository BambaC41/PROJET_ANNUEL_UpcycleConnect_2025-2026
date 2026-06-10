-- Alignement des rôles sur le modèle 4 rôles (à exécuter sur une base existante avant qa_smoke).
-- 1=ADMIN, 2=USER/Particulier, 3=PRO, 4=SALARIE — plus de STAFF ni id_role=5.
USE upcycleconnect;

SET FOREIGN_KEY_CHECKS = 0;

UPDATE utilisateur SET id_role = 4 WHERE email = 'emp1@test.com';
UPDATE utilisateur SET id_role = 3 WHERE email = 'pro1@test.com';
UPDATE utilisateur SET id_role = 2 WHERE email = 'user1@test.com';

DELETE FROM utilisateur WHERE email = 'staff@upcycleconnect.fr';

DELETE FROM role WHERE id_role > 4;

UPDATE role SET code = 'USER', libelle = 'Particulier' WHERE id_role = 2;
UPDATE role SET code = 'PRO', libelle = 'Professionnel' WHERE id_role = 3;
UPDATE role SET code = 'SALARIE', libelle = 'Salarié animateur/formateur' WHERE id_role = 4;

-- Réassigner d'éventuels comptes encore sur d'anciens id_role
UPDATE utilisateur SET id_role = 2 WHERE id_role = 3 AND email NOT IN ('pro1@test.com');
UPDATE utilisateur SET id_role = 3 WHERE id_role = 4 AND email NOT IN ('emp1@test.com');
UPDATE utilisateur SET id_role = 4 WHERE id_role = 5;

SET FOREIGN_KEY_CHECKS = 1;

SELECT id_role, code, libelle FROM role ORDER BY id_role;
SELECT email, id_role FROM utilisateur ORDER BY id_user;
