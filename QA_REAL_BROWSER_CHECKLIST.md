# UpcycleConnect — Checklist navigateur (≈ 5 minutes)

À exécuter sur **XAMPP** après import de `database/upcycleconnect_v2.sql` et avec l’API Go **optionnelle** (le PHP doit fonctionner avec fallback PDO).

## Particulier (`user1@test.com` / `Upcycle2026!`)

1. `login.php?switch=1` puis connexion.
2. `particulier.php` : terminer le tutoriel (**Terminer** ou **Passer le tutoriel**) — pas d’alerte bloquante ; recharger : overlay absent.
3. `particulier_annonces.php` : créer une annonce don puis une vente (prix &gt; 0) — message de succès.
4. `particulier_conteneurs.php` : demande de dépôt — message de succès.
5. `particulier_catalogue.php` : inscription événement **gratuit** puis **payant** si disponible.
6. `particulier_planning.php` : pour inscription payante, **Payer en mode démonstration** → `paiement_success.php` (pas `paiement_cancel.php` sans raison claire).
7. `notifications.php` : au moins une notification après tutoriel / paiement.
8. `particulier_documents.php` : document après paiement démo.

## Professionnel (`pro1@test.com` / `Upcycle2026!`)

1. `pro_billing.php` : paiement **abonnement premium** et **campagne publicitaire** — succès, notification, entrées en base.
2. `pro_annonces.php` : réserver un **don** ; **acheter** une **vente** validée — succès ou message explicite si indisponible.
3. `pro_documents.php` : reçu / document visible après paiement.
4. Vérifier espacement (cartes, pas de contenu collé sous la navbar).

## Salarié (`emp1@test.com` / `Upcycle2026!`)

1. `salarie_conseils.php` : créer un conseil avec **upload image** — liste mise à jour, toast de succès.
2. `salarie_events.php` : créer un événement — statut **en_attente**, liste à jour.
3. `salarie_forum.php` : nouveau topic — message de succès.
4. `salarie_planning.php` : semaine précédente / suivante sur **une ligne** (pas de chevauchement).

## Admin (`admin@upcycleconnect.fr` / `Upcycle2026!`)

1. `notifications.php` : notifications présentes (y compris seeds).
2. Valider / refuser contenus selon les écrans admin prévus.
3. `admin_users.php` : liste et filtres utilisables.

## Technique (local)

- `php -l` sur les fichiers modifiés.
- `php scripts/qa_smoke.php` (avec driver PDO MySQL activé).
