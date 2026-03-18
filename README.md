# 🌍 Architecture Globale & Accès — UpcycleConnect

Ce document récapitule l'ensemble de l'infrastructure du projet **UpcycleConnect** (Projet Annuel 2025-2026). 
L'architecture est construite en plusieurs couches : de la virtualisation (Proxmox), à l'émulation réseau (EVE-NG), jusqu'à l'orchestration des conteneurs (Portainer/Docker).

---

## 🏗️ 1. Couche Infrastructure & Réseau

Cette couche représente les fondations de notre environnement de laboratoire et de production.

| Service | Rôle | URL / Accès | Identifiants par défaut |
| :--- | :--- | :--- | :--- |
| **Proxmox VE** | Hyperviseur (Bare-metal) hébergeant les VMs | `https:/92.222.243.38:8006` | `root` / *mdp_clasique* |
| **EVE-NG** | Émulateur réseau (Laboratoire) | `http://92.222.243.38` | `admin` / *mdp_clasique* |
| **Serveur Ubuntu** | Machine Virtuelle Linux hébergeant Docker | `ssh adix@192.168.229.135` (Local) ou `92.222.243.38` (Public) | `adix` / *votre_mdp* |

---

## 🐳 2. Couche Orchestration (Docker)

La gestion de nos conteneurs applicatifs est centralisée et simplifiée grâce à Portainer.

| Service | Rôle | URL / Accès | Port |
| :--- | :--- | :--- | :--- |
| **Portainer** | Interface Web de gestion Docker | `https://92.222.243.38:9443` (ou port `9000`) | `9443` |

*Depuis Portainer, vous avez accès à la stack `upcycle-final` qui orchestre l'ensemble des services applicatifs ci-dessous.*

---

## 🚀 3. Couche Applicative (Services UpcycleConnect)

Ces services tournent à l'intérieur de la VM Ubuntu, orchestrés par Docker.

| Service | Technologie | URL Publique / Adresse | Port externe | État Sécurité |
| :--- | :--- | :--- | :--- | :--- |
| **Site Client** | Nginx / HTML | `https://92.222.243.38` | `443` | 🔒 HTTPS (Auto-signé) |
| **Panel Admin** | PHP 8.2 / Apache | `https://92.222.243.38:8081` | `8081` | 🔒 HTTPS (Auto-signé) |
| **API Backend** | Golang 1.22 | `http://92.222.243.38:8080` | `8080` | 🔓 HTTP |
| **Base de Données**| MariaDB | `upcycle-db` (Réseau Docker interne) | `3306` | 🛡️ Protégé (Interne) |

---

## 🔑 4. Comptes de Test (Pré-injectés en BDD)

La base de données s'auto-initialise avec le script `init.sql`. Voici les comptes disponibles pour tester le Panel Admin et l'API :

| Rôle | Pseudo | Email | Mot de passe (clair) |
| :--- | :--- | :--- | :--- |
| **Admin** | recycleur92 | `user1@test.com` | *A définir selon vos tests* |
| **Staff** | eco_sarah | `user2@test.com` | *A définir selon vos tests* |
| **User** | atelierbois | `user3@test.com` | *A définir selon vos tests* |
| **Pro** | atelier_pro | `pro1@test.com` | *A définir selon vos tests* |

---

## 🛠️ 5. Commandes de Maintenance Utiles

En cas de besoin de dépannage directement en SSH sur le serveur Ubuntu :

**Mettre à jour l'infrastructure applicative depuis Portainer/Docker :**
```bash
docker-compose pull
docker-compose up -d
