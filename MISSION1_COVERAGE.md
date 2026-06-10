# Mission 1 — Couverture fonctionnelle (état au 2026-05-12)

Synthèse rapide pour la soutenance : ce qui est **OK**, **partiel** ou **non fait**, avec les fichiers principaux.

| Zone | État | Fichiers / pages |
|------|------|-------------------|
| Espace particulier | **OK** (partiel UX historique) | `particulier.php`, `particulier_catalogue.php`, `particulier_planning.php`, `particulier_conteneurs.php`, `particulier_annonces.php`, `includes/particulier_bootstrap.php`, `includes/particulier_nav.php` |
| Espace pro | **OK** (partiel selon API Go) | `pro.php`, `pro_annonces.php`, `pro_conteneurs.php`, `pro_billing.php`, `includes/pro_bootstrap.php`, `includes/pro_nav.php` |
| Espace salarié | **Partiel** | `salarie.php`, `salarie_events.php`, `salarie_planning.php`, `salarie_conseils.php`, `salarie_forum.php`, `includes/employee_bootstrap.php`, `includes/employee_nav.php` |
| Back-office admin | **OK** (partiel selon API) | `admin.php`, `admin_users.php`, `admin_events.php`, `admin_event_edit.php`, `admin_conteneurs.php`, `includes/admin_bootstrap.php`, `includes/header.php`, `includes/sidebar.php` |
| Annonces (validation / flux) | **Partiel** | `admin_annonces.php`, `particulier_annonces.php`, `pro_annonces.php` |
| Conteneurs / code accès / QR | **OK** (PDF + QR locaux) | `particulier_conteneurs.php`, `pro_conteneurs.php`, `admin_conteneurs.php`, `includes/functions/qr.php`, `includes/third_party/qrcode_arase.php`, `document_download.php` |
| Planning | **Partiel** | `particulier_planning.php`, `admin_planning.php`, `salarie_planning.php` |
| Catalogue / prestations / événements | **OK** (partiel si API indisponible) | `particulier_catalogue.php`, `admin_catalog.php`, `admin_events.php`, `includes/functions/events.php` |
| Paiements Stripe / mode démo | **OK** (démo uniquement) | `paiement_checkout_demo.php`, `paiement_demo.php`, `includes/functions/demo_payments.php`, `paiement_success.php`, `paiement_cancel.php` |
| Documents PDF | **OK** (générateur PHP minimal) | `includes/functions/pdf_simple.php`, `includes/functions/documents.php`, `document_download.php`, `storage/documents/` |
| Notifications | **OK** | `notifications.php`, `includes/notifications.php`, `includes/dashboard_notifications.php`, `includes/functions/bootstrap_notify.php`, `includes/flash_toast.php` |
| Multilingue | **Partiel** | `includes/i18n.php`, `i18n/fr.php`, `i18n/en.php` (couverture inégale selon les pages) |
| API Go | **Partiel** | Dossier `api/api/` — utilisée quand disponible ; sinon **fallback PDO / PHP** sur plusieurs écrans admin (ex. listes conteneurs / demandes). |
| Limites restantes | **À prévoir en soutenance** | **Stripe réel** : nécessite clés et webhook. **OneSignal / push** : non intégré. **QR dans le corps PDF** : le PDF texte inclut la valeur à scanner ; pas d’image bitmap embarquée (léger compromis sans librairie image PDF). |

### Stripe et OneSignal

- **Stripe** : le parcours bout en bout existe en **mode démonstration** (`paiement_checkout_demo.php` → `paiement_demo.php`). Une intégration réelle impose des clés (`STRIPE_*`), création de session côté serveur et gestion des statuts.
- **OneSignal** (ou équivalent) : non branché ; les notifications sont **en base** + **toasts PHP session**.

### SQL

- **Aucune migration obligatoire** pour ces correctifs : réutilisation des colonnes existantes (`document_genere.file_path`, statuts métier).
- Si vous réimportez `database/upcycleconnect_v2.sql` pour une base vierge, exécutez aussi vos jeux de données de démo après import.
