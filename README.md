# ♻️ UpcycleConnect - Panel d'Administration

Bienvenue sur le dépôt du **Panel d'Administration (Back-Office)** de la plateforme collaborative d'upcycling **UpcycleConnect**.

Ce projet est une interface web en PHP natif (HTML/CSS/JS) conçue pour gérer l'ensemble des données de la plateforme via une communication directe avec une API REST (backend développé en Go).

---

## ✨ Fonctionnalités Principales

### 📊 Dashboard Global (`admin.php`)

- **KPIs Dynamiques** : Nombre total d'utilisateurs, de professionnels, d'événements et revenus mensuels.
- **Vues rapides** : Aperçu des derniers utilisateurs inscrits, des prestations du catalogue et des événements à venir.

### 👥 Gestion des Utilisateurs (`users.php`)

- **Système de rôles** : `ADMIN`, `STAFF`, `USER`, `PRO`.
- **Modération avancée** : Bannissement temporaire avec motif (`ban` / `unban`).
- **Filtres et Recherche** : Recherche par nom/email, filtrage par rôle, et vue des "Pros en attente" d'approbation.
- **Actions** : Création de comptes (Staff/Admin), édition complète des profils, suppression, et réinitialisation de mot de passe.

### 🛠️ Catalogue et Prestations (`prestations.php` & `prestation_categories.php`)

- **Prestations** : Liste, création, modification, suppression et assignation de statuts (Actif/Inactif).
- **Catégories** : Gestion complète des catégories de prestations.
- **Filtres** : Recherche par titre ou description et filtrage par catégorie.

### 📅 Événements (`events.php`)

- **Gestion du planning** : Création d'événements liés aux prestations.
- **Détails** : Gestion des dates (début/fin), lieu, capacité maximale et statuts (Actif, En attente, Annulé, etc.).

### 🔐 Authentification

- Connexion sécurisée via l'API.
- Stockage du token JWT en session PHP (`$_SESSION['token']`).
- Protection des routes : redirection automatique si non authentifié.

---

## 🚀 Technologies Utilisées

- **Frontend** : HTML5, CSS3 natif (Design System sur mesure dans `style.css`), JavaScript natif (Modales, requêtes asynchrones, alertes).
- **Backend (Front-for-Back)** : PHP 8.2+
- **Serveur Web** : Apache
- **Conteneurisation** : Docker (`php:8.2-apache` avec configuration SSL/HTTPS)

---

## ⚙️ Installation et Configuration

### 1. Variables d'Environnement

L'application utilise un système natif de variables d'environnement. Créez un fichier `.env` à la racine du projet (au même niveau que `admin.php`) :

```env
# Fichier .env (Environnement Local)
API_BASE_URL="http://localhost:8080"
```

_Note : En production ou via Docker Compose, la variable `API_BASE_URL` peut être injectée directement, le code lui donnera la priorité._

### 2. Lancement en Local (avec MAMP/XAMPP/WAMP)

1. Clonez ce dépôt dans votre dossier serveur (ex: `c:\MAMP\htdocs\UpcycleConnect-Admin`).
2. Assurez-vous que l'API Go est lancée en parallèle (ex: sur le port 8080).
3. Accédez à `http://localhost/UpcycleConnect-Admin/login.php`.

### 3. Lancement via Docker

Le projet inclut un `Dockerfile` configuré pour un serveur Apache sécurisé (HTTPS).

```bash
# Construction de l'image
docker build -t upcycle-admin .

# Lancement du conteneur (port 443 pour le HTTPS)
docker run -d -p 443:443 --name admin-panel upcycle-admin
```

_Assurez-vous que les certificats SSL sont générés et montés correctement selon les chemins définis dans le `Dockerfile`._

---

## 📂 Architecture du Projet

```text
📁 PROJET_ANNUEL_UpcycleConnect/
├── 📄 .env                    # Fichier de configuration local (URL de l'API)
├── 📄 Dockerfile              # Configuration du conteneur Docker avec Apache + SSL
├── 📄 admin.php               # Dashboard principal
├── 📄 login.php               # Page de connexion
├── 📄 users.php               # Gestion des utilisateurs
├── 📄 prestations.php         # Gestion des prestations (Catalogue)
├── 📄 prestation_categories.php # Gestion des catégories
├── 📄 events.php              # Gestion des événements
├── 📁 includes/
│   ├── 📄 head.php            # Balises <head> communes
│   ├── 📄 header.php          # Barre de navigation supérieure
│   ├── 📄 sidebar.php         # Menu de navigation latéral
│   ├── 📄 footer.php          # Pied de page
│   └── 📁 functions/
│       ├── 📄 api_core.php    # Moteur cURL centralisé et parseur .env
│       ├── 📄 auth.php        # Fonctions d'authentification (login)
│       ├── 📄 users.php       # Appels API pour les utilisateurs
│       ├── 📄 prestations.php # Appels API pour les prestations et catégories
│       └── 📄 events.php      # Appels API pour les événements
├── 📁 scripts/
│   └── 📄 *.js                # Scripts JS pour les modales et auto-sauvegarde
└── 📁 styles/
    └── 📄 style.css           # Feuille de style principale
```

---

## 🔒 Sécurité

- **Tokens JWT** : L'API distribue des tokens JWT lors de la connexion. Ce token est transmis dans les headers HTTP (`Authorization: Bearer <token>`) par la fonction `callAPI()` à chaque requête.
- **Prévention XSS** : Affichage sécurisé des données avec `htmlspecialchars()` systématique dans les vues PHP.
- **HTTPS** : Configuration forcée sur le port 443 via le Dockerfile.

---

_Projet réalisé dans le cadre du projet annuel UpcycleConnect 2025-2026._---

## 🔒 Sécurité

- **Tokens JWT** : L'API distribue des tokens JWT lors de la connexion. Ce token est transmis dans les headers HTTP (`Authorization: Bearer <token>`) par la fonction `callAPI()` à chaque requête.
- **Prévention XSS** : Affichage sécurisé des données avec `htmlspecialchars()` systématique dans les vues PHP.
- **HTTPS** : Configuration forcée sur le port 443 via le Dockerfile.

---

_Projet réalisé dans le cadre du projet annuel UpcycleConnect 2025-2026._
