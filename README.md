# 🗄️ Base de Données — UpcycleConnect

Bienvenue sur la branche dédiée à la **Base de Données** du projet **UpcycleConnect**. 
Cette branche contient tout le nécessaire pour déployer et initialiser automatiquement une base de données **MariaDB** pré-configurée grâce à **Docker**.

---

## 🎯 Objectif de cette branche

L'objectif est de fournir une image Docker autonome de notre base de données. 
Au premier démarrage du conteneur, les tables se créent automatiquement et des données de test (utilisateurs, rôles, prestations) sont injectées pour permettre de tester l'API et le Panel Admin immédiatement.

---

## 📂 Contenu du dossier

- **`Script_SQL.txt`** : Le script SQL complet d'initialisation. Il contient :
  - La création de la base de données `upcycleconnect`.
  - La création de toutes les tables relationnelles (utilisateur, rôle, objet, conteneur, demande_depot, prestation, etc.).
  - L'insertion des données de base (les 4 rôles principaux) et d'utilisateurs de test.
- **`Dockerfile`** : Le fichier de configuration Docker qui utilise l'image officielle `mariadb:latest` et copie le script SQL dans le dossier d'auto-initialisation (`/docker-entrypoint-initdb.d/`).

---

## 🏗️ Structure de la Base de Données

Le modèle de données s'articule autour de plusieurs axes :
1. **Gestion des Utilisateurs & Rôles** (`utilisateur`, `role`) : Admin, Staff, User, Pro.
2. **Gestion des Dépôts** (`objet`, `conteneur`, `demande_depot`, `code_acces`, `code_barre`, `retrait`).
3. **Économie Circulaire** (`annonce` pour la revente).
4. **Événements & Ateliers** (`categorie_prestation`, `prestation`, `session`, `inscription`, `paiement`).

---

## 🚀 Comment construire et lancer l'image ?

Assurez-vous d'avoir **Docker** installé sur votre machine ou votre serveur.

### 1. Construire l'image locale
Placez-vous dans le dossier contenant le `Dockerfile` et lancez la commande suivante :
```bash
docker build -t upcycle-db:latest .
