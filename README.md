# 🌍 Architecture & Accès — UpcycleConnect

Ce document récapitule l'ensemble de l'infrastructure Dockerisée du projet **UpcycleConnect** (Projet Annuel 2025-2026). 
L'infrastructure est déployée sur un serveur Ubuntu et gérée via des conteneurs Docker (orchestrés avec Portainer / Docker Compose).

---

## 📊 Tableau Récapitulatif des Accès

| Service | Technologie | URL / Adresse IP | Port | État Sécurité |
| :--- | :--- | :--- | :--- | :--- |
| **Site Client** | Nginx / HTML | `https://92.222.243.38` | `443` | 🔒 HTTPS (Auto-signé) |
| **Panel Admin** | PHP 8.2 / Apache | `https://92.222.243.38:8081` | `8081` | 🔒 HTTPS (Auto-signé) |
| **API Backend** | Golang 1.22 | `http://92.222.243.38:8080` | `8080` | 🔓 HTTP |
| **Base de Données** | MariaDB | `upcycle-db` (Réseau Docker interne) | `3306` | 🛡️ Protégé (Interne) |

*(Note : Si vous testez en réseau local depuis votre machine virtuelle VMware, remplacez l'IP `92.222.243.38` par l'IP de votre VM, ex: `192.168.229.135`).*

---

## 🖥️ Détails par Service

### 1. Site Client (Front-End)
- **Rôle** : Vitrine publique du projet pour les utilisateurs finaux.
- **Accès** : [https://92.222.243.38](https://92.222.243.38)
- **Configuration** : Redirection des ports `443:443`. Les certificats SSL (`nginx-selfsigned.crt` et `.key`) sont montés via un volume Docker.

### 2. Panel d'Administration (Front-Admin)
- **Rôle** : Interface de gestion (CRUD) pour les administrateurs et le staff.
- **Accès** : [https://92.222.243.38:8081/login.php](https://92.222.243.38:8081/login.php)
- **Configuration** : Le port externe `8081` pointe vers le port interne sécurisé `443` d'Apache. Le module SSL d'Apache a été activé manuellement dans le `Dockerfile`.

### 3. API Backend (Golang)
- **Rôle** : Cœur logique de l'application. Traite les requêtes du Panel Admin et du Site Client, et interagit avec la base de données.
- **Accès Base** : `http://92.222.243.38:8080`
- **Configuration** : Le système CORS est activé pour autoriser les requêtes provenant du Panel Admin.

### 4. Base de Données (MariaDB)
- **Rôle** : Stockage persistant de toutes les données du projet.
- **Identifiants de connexion** :
  - **Hôte** : `upcycle-db` (ou `localhost` si accès depuis le conteneur BDD lui-même)
  - **Utilisateur** : `root`
  - **Mot de passe** : `eve_password`
  - **Nom de la base** : `upcycleconnect`
- **Initialisation** : La base se construit toute seule au premier démarrage grâce à une image Docker personnalisée contenant le script `init.sql`.

---

## 🔑 Comptes de Test (Pré-injectés en BDD)

Une fois la base de données initialisée, vous pouvez vous connecter au Panel Admin avec ces comptes de test :

| Rôle | Email | Mot de passe (Hash en BDD) |
| :--- | :--- | :--- |
| **Admin** | `user1@test.com` | *Voir configuration PHP* |
| **Staff** | `user2@test.com` | *Voir configuration PHP* |
| **User** | `user3@test.com` | *Voir configuration PHP* |
| **Pro** | `pro1@test.com` | *Voir configuration PHP* |

---

## 🛠️ Commandes de Maintenance Rapides

Pour administrer le serveur en ligne de commande (SSH sur l'IP du serveur) :

**Voir les conteneurs en cours d'exécution :**
```bash
docker ps
