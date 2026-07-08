# UpcycleConnect

Plateforme web d’upcycling connectant particuliers, professionnels, salariés et administrateurs.

## Présentation du projet

UpcycleConnect est une application web complète dédiée à l’upcycling et à l’économie circulaire. Elle permet aux utilisateurs de :
- Publier des annonces (dons ou ventes) d’objets à récupérer ou à transformer,
- Déposer des objets dans des conteneurs dédiés,
- S’inscrire à des ateliers, formations et événements,
- Suivre son impact environnemental via un score d’upcycling,
- Échanger sur un forum communautaire,
- Gérer ses documents (attestations, factures) et ses paiements.

L’application est pensée pour quatre types d’acteurs :
- **Particuliers** : dépôt d’annonces, réservation d’objets, inscriptions aux événements,
- **Professionnels** : gestion d’annonces, récupération en conteneurs, suivi de projets,
- **Salariés (animateurs/formateurs)** : création et animation d’événements, rédaction de conseils, modération,
- **Administrateurs** : supervision de l’ensemble des données, validation des contenus, gestion des utilisateurs.

## Stack technique

- **Frontend** : PHP natif (HTML/CSS/JS) avec architecture MVC légère et espaces dédiés par rôle.
- **Backend API** : Go (Golang) avec gestion JWT, routes RESTful, et logique métier.
- **Base de données** : MySQL, avec un schéma complet couvrant utilisateurs, annonces, objets, événements, paiements, forum, audit, etc.
- **Authentification** : JWT (JSON Web Token) avec gestion des rôles.
- **Paiements** : Intégration Stripe pour les paiements (dons, ventes, commissions, inscriptions).
- **Notifications** : Système de notifications internes (base de données) et emails transactionnels via Brevo (SMTP/API).
- **Hébergement** : Serveur VPS OVH, conteneurisation Docker, Cloudflare Tunnel pour HTTPS.

## Architecture

L’application est organisée en trois couches principales :

1. **Front PHP** : pages dynamiques pour chaque rôle (`particulier.php`, `pro.php`, `salarie.php`, `admin.php`) avec des sous-pages dédiées (annonces, conteneurs, conseils, événements, documents, etc.).
2. **API Go** : service REST qui expose les endpoints pour la gestion des utilisateurs, annonces, conteneurs, événements, paiements, scores, etc.
3. **Base de données MySQL** : stockage de toutes les données métier, avec des tables pour les utilisateurs, annonces, objets, demandes de dépôt, événements, inscriptions, paiements, notifications, forum, audit, etc.

Les échanges entre le front et l’API se font via des appels HTTP avec token JWT pour l’authentification.

## Rôles et espaces

| Rôle | ID | Espace principal | Fonctions clés |
|------|----|------------------|----------------|
| **Administrateur** | 1 | `admin.php` | Validation des annonces, gestion des utilisateurs, supervision des dépôts, finance, audit, modération du forum. |
| **Particulier** | 2 | `particulier.php` | Publication d’annonces, demandes de dépôt en conteneur, inscription aux événements, consultation de conseils, suivi de son score. |
| **Professionnel** | 3 | `pro.php` | Gestion des annonces, récupération d’objets via conteneurs, projets d’upcycling, abonnements premium, marketplace. |
| **Salarié (animateur/formateur)** | 4 | `salarie.php` | Création d’événements, rédaction de conseils et tutoriels, modération du forum. |

## Fonctionnalités principales

### Gestion des annonces
- Création d’annonces (don ou vente) avec photo, description, prix.
- Validation par l’administrateur.
- Commission de 5 % sur les ventes, paiement en ligne.
- Réservation de dons par les professionnels.

### Conteneurs et dépôts
- Demande de dépôt d’objets dans des conteneurs physiques.
- Génération de codes d’accès et de codes-barres.
- Suivi des statuts (en attente, validé, retiré).

### Événements et formations
- Catalogue d’ateliers, formations et conférences.
- Inscription en ligne, paiement Stripe.
- Génération d’attestations d’inscription et de factures.

### Forum communautaire
- Catégories et sujets.
- Publication de messages.
- Modération (masquage, signalement, bannissement).
- Notifications en cas de nouvelle réponse.

### Conseils et tutoriels
- Rédaction d’articles par les salariés.
- Validation par l’administrateur.
- Affichage public avec catégories.

### Gestion des utilisateurs et sécurité
- Inscription et connexion sécurisée avec JWT.
- Récupération de mot de passe via email.
- Bannissement, suspension, gestion des rôles.
- Journal d’audit des actions administratives.

### Paiements et facturation
- Intégration Stripe pour les achats, commissions et inscriptions.
- Génération de documents (factures, attestations) au format HTML.
- Suivi des transactions et du chiffre d’affaires.

### Notifications
- Notifications internes (base de données) pour les actions importantes (validation, paiement, événement…).
- Envoi d’emails transactionnels via Brevo (SMTP).

## Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | `admin@upcycleconnect.fr` | `Upcycle2026!` |
| Particulier | `user1@test.com` | `Upcycle2026!` |
| Professionnel | `pro1@test.com` | `Upcycle2026!` |
| Salarié | `emp1@test.com` | `Upcycle2026!` |

Ces comptes sont préconfigurés pour tester l’ensemble des fonctionnalités.

## Parcours de démonstration recommandé

1. **Connexion en tant que particulier** (`user1@test.com`) :
   - Suivre le tutoriel de première connexion.
   - Publier une annonce (don ou vente).
   - Faire une demande de dépôt en conteneur.
   - S’inscrire à un événement depuis le catalogue.
   - Effectuer un paiement (simulation Stripe).
   - Consulter ses documents et notifications.

2. **Connexion en tant qu’administrateur** (`admin@upcycleconnect.fr`) :
   - Valider l’annonce et le dépôt.
   - Gérer les utilisateurs et les événements.
   - Consulter la finance et l’audit.

3. **Connexion en tant que professionnel** (`pro1@test.com`) :
   - Créer une annonce.
   - Récupérer un objet via un conteneur.
   - Créer un projet d’upcycling.

4. **Connexion en tant que salarié** (`emp1@test.com`) :
   - Créer un événement.
   - Rédiger un conseil.
   - Modérer le forum (si besoin).

Ce parcours couvre les cas d’usage principaux de la plateforme.

**UpcycleConnect** – Donnez une seconde vie aux objets, connectez les acteurs de l’upcycling.
