# UpcycleConnect - Mission 1 (Web + API + MySQL)

Plateforme web d'upcycling avec:
- front en PHP natif (espaces `particulier`, `pro`, `salarie`, `admin`)
- API REST en Go
- base MySQL
- authentification JWT et gestion des roles

## Stack technique

- PHP 8.1+ (XAMPP/Apache)
- Go 1.22+
- MySQL 8+
- JWT (`Authorization: Bearer ...`)

## Roles applicatifs

- `ADMIN` (1): back-office administratif
- `USER/PARTICULIER` (2)
- `PRO` (3)
- `SALARIE` (4): animateur/formateur (espace `salarie.php`)

## Installation locale (mode recommande pour soutenance)

1. Cloner le projet dans `C:/xampp/htdocs/upcycle`

2. Créer un fichier `.env` à la racine (copier depuis `.env.example` et adapter):

```env
API_BASE_URL=http://localhost:8080
JWT_SECRET=upcycle_dev_only_change_me_2026
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=upcycleconnect
DB_USER=root
DB_PASSWORD=
```

3. Importer la base MySQL (script complet de demo):
- Voir la section **Import MySQL avec le client mysql** ci-dessous.

4. Lancer l'API Go:

```bash
cd api/api
go mod tidy
go run .
```

5. Lancer le site PHP:
- Démarrer Apache (XAMPP)
- Ouvrir [http://localhost/upcycle](http://localhost/upcycle)

### Configuration MySQL locale

#### XAMPP sans mot de passe (défaut)
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=upcycleconnect
DB_USER=root
DB_PASSWORD=
```

#### XAMPP avec mot de passe personnalisé
Si vous avez défini un mot de passe root dans XAMPP MySQL:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=upcycleconnect
DB_USER=root
DB_PASSWORD=votre_mot_de_passe_ici
```

Pour vérifier/définir le mot de passe root MySQL dans XAMPP:
```bash
# Connexion sans mot de passe
mysql -u root

# Dans MySQL, définir un mot de passe (une seule fois):
ALTER USER 'root'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
FLUSH PRIVILEGES;
```

## Import MySQL avec le client mysql

Objectif: reconstruire une base de demo propre (MySQL 8.0.45 compatible), verifier le schema, puis lancer un smoke test CLI.

1. Ouvrir le client mysql:

```bash
mysql -u root -p
```

2. Importer le SQL:

```sql
SOURCE C:/chemin/vers/upcycle/database/upcycleconnect_v2.sql;
```

Important Windows: utiliser des slashs `/` dans le chemin.

3. Verifier la base:

```sql
USE upcycleconnect;
SOURCE C:/chemin/vers/upcycle/database/verify_schema.sql;
```

4. Lancer le smoke test:

```bash
php scripts/qa_smoke.php
```

5. Lancer le site:
- demarrer Apache avec XAMPP
- ouvrir `http://localhost/upcycle` (ou le chemin local du projet)

6. Lancer l'API Go:

```bash
cd api/api
go run .
```

## Comptes de demonstration

Mot de passe commun: `Upcycle2026!`

- `admin@upcycleconnect.fr`
- `user1@test.com`
- `pro1@test.com`
- `emp1@test.com`

Roles attendus:
- `admin@upcycleconnect.fr`: id_role = 1 (ADMIN)
- `user1@test.com`: id_role = 2 (USER / particulier) + `tutorial_completed = 0` pour montrer le tutoriel
- `pro1@test.com`: id_role = 3 (PRO) + `is_approved = 1`
- `emp1@test.com`: id_role = 4 (SALARIE)

## Fonctionnalites couvertes

- Authentification/login/register/logout
- Redirection par role
- Guards de pages privees par role
- Espaces:
  - `particulier_*`
  - `pro_*`
  - `salarie_*`
  - `admin_*`
- Catalogue/prestations/evenements/inscriptions
- Annonces et demandes de depot
- Paiements (mode demo), factures et documents generes (stockage DB)
- Notifications internes
- Forum (topics/messages/signalements)
- Journal d'audit admin
- Tutoriel premiere connexion particulier (overlay + finalisation)
- Multilingue FR/EN (socle sur pages publiques + navigation)

## Repartition PHP / API Go

- **API Go**: auth, profils, annonces, conteneurs, evenements, inscriptions, paiements API existants, score.
- **PHP local (Mission 1 soutenance)**: notifications internes en base, generation de documents HTML imprimables, paiement demo, audit/gestion forum back-office.

## Endpoints API Go principaux

- `GET /health`
- `POST /login`
- `POST /register`
- `GET /me`
- `PUT /me/update`
- `GET/POST /annonces`, `GET/PUT/DELETE /annonces/{id}`
- `GET/POST /conteneurs`, `GET/PUT/DELETE /conteneurs/{id}`
- `GET/POST /demandes-depot`, `GET/PUT/DELETE /demandes-depot/{id}`
- `GET /events`, `GET/PUT/DELETE /events/{id}`
- `POST /events/{id}/register`
- `GET /me/inscriptions`, `GET /me/paiements`, `GET /me/score`
- `GET/POST /conseils`

## Verifications rapides

Depuis la racine:

```bash
php -l login.php
php -l register.php
php -l pro.php
php -l includes/pro_bootstrap.php
php -l includes/employee_bootstrap.php
```

Lint PHP recursif (PowerShell):

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Depuis `api/api`:

```bash
go build ./...
```

## Limites actuelles

- Paiements: mode demo actif (structure compatible Stripe en base)
- OneSignal: non branche (notifications internes en base en place)
- Certaines pages legacy (`users.php`, `events.php`, `prestations.php`) restent presentes pour compatibilite
- Docker: non priorise pour la mission 1 (site + SQL + QA)

## Parcours de demonstration recommande

1. Connexion `user1@test.com`
2. Tutoriel premiere connexion (dashboard particulier)
3. Depot annonce particulier
4. Demande depot conteneur
5. Inscription evenement depuis le catalogue
6. Paiement demo (planning particulier)
7. Consultation document/facture (`particulier_documents.php`)
8. Verification notification (`notifications.php`)
9. Connexion admin (`admin@upcycleconnect.fr`)
10. Validation annonce / depot / evenement
11. Consultation finance, documents et audit
12. Connexion pro (`pro1@test.com`)
13. Recuperation objet (conteneurs pro)
14. Creation projet upcycling
15. Connexion salarie (`emp1@test.com`)
16. Creation evenement, publication forum et moderation
