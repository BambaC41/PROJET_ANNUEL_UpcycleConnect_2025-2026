# 📚 Documentation API — UpcycleConnect

Une API complète pour gérer la plateforme collaborative d'upcycling **UpcycleConnect**.

---

## 🚀 Configuration Initiale

### 📦 Prérequis
- Base de données MySQL créée et initialisée
- Go installé sur votre machine
- Postman ou un client HTTP pour tester les routes

### 🔐 Initialisation de l'Admin

Exécutez cette requête SQL pour créer le premier administrateur :

```sql
INSERT INTO utilisateur (
    email,
    password_hash,
    pseudo,
    prenom,
    nom,
    statut,
    is_approved,
    id_role
) VALUES (
    'admin@upcycleconnect.fr',
    '$2a$10$R7qQxVw8i4M1M5bCqM8d8uG0k1Vb2zKjv2mX3Qn6eG1f0s9U7M2wS',
    'admin_upcycleconnect',
    'Super',
    'Admin',
    'actif',
    TRUE,
    1
);
```

**Identifiants par défaut :**
| Champ | Valeur |
|-------|--------|
| **Email** | admin@upcycleconnect.fr |
| **Mot de passe** | Password123! |

### ▶️ Démarrage de l'API

```bash
go run .
```

**Résultat attendu :**
```
Server running on http://localhost:8080
```

---

## 🌐 Configuration Générale

### Base URL
```
http://localhost:8080
```

### 🔑 Authentification

Toutes les routes protégées requièrent un token JWT dans le header :

```
Authorization: Bearer <JWT_TOKEN>
```

### 👥 Système de Rôles

| ID | Rôle | Description |
|-----|------|-------------|
| 1 | 🔴 **ADMIN** | Accès complet à l'administration |
| 2 | 🟠 **STAFF** | Modération et approbation des PROs |
| 3 | 🟢 **USER** | Utilisateur standard |
| 4 | 🔵 **PRO** | Prestataire (services et événements) |

---

## 📋 Routes API

### 🔐 Authentification

#### POST `/register`

**Description :** Créer un compte utilisateur public.

**Accès :** Public  
**Rôles créables :** USER, PRO

**Comportement spécial :**
- Les USER sont automatiquement approuvés
- Les PRO doivent être approuvés par un STAFF avant de se connecter

**URL :**
```http
POST http://localhost:8080/register
```

**Exemple 1 : Inscription USER**
```json
{
  "email": "user@test.com",
  "password": "Password123!",
  "pseudo": "user1",
  "prenom": "Jean",
  "nom": "Dupont",
  "photo_profil": "",
  "bio": "Utilisateur test",
  "role_id": 3
}
```

**Exemple 2 : Inscription PRO**
```json
{
  "email": "pro@test.com",
  "password": "Password123!",
  "pseudo": "atelier_pro",
  "prenom": "Camille",
  "nom": "Bernard",
  "photo_profil": "",
  "bio": "Professionnel réparation",
  "role_id": 4
}
```

**Réponse (201) :**
```json
{
  "message": "user created"
}
```

---

#### POST `/login`

**Description :** S'authentifier et obtenir un token JWT.

**Accès :** Public

**Retourne :**
- `token` : Token JWT pour les requêtes authentifiées
- `role_id` : ID du rôle utilisateur
- `user_id` : ID unique de l'utilisateur
- `is_approved` : État d'approbation (PRO seulement)

**⚠️ Restriction :** Les PROs non approuvés ne peuvent pas se connecter.

**URL :**
```http
POST http://localhost:8080/login
```

**Exemples :**

```json
{
  "email": "admin@upcycleconnect.fr",
  "password": "Password123!"
}
```

```json
{
  "email": "user@test.com",
  "password": "Password123!"
}
```

```json
{
  "email": "pro@test.com",
  "password": "Password123!"
}
```

**Réponse (200) :**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "role_id": 4,
  "user_id": 12,
  "is_approved": true
}
```

---

### 👤 Profil Utilisateur

#### GET `/me`

**Description :** Récupérer les informations de l'utilisateur connecté.

**Accès :** Authentifié

**URL :**
```http
GET http://localhost:8080/me
```

**Headers :**
```
Authorization: Bearer <TOKEN>
```

---

#### PUT `/me/update`

**Description :** Modifier le profil de l'utilisateur connecté.

**Accès :** Authentifié

**URL :**
```http
PUT http://localhost:8080/me/update
```

**Headers :**
```
Authorization: Bearer <TOKEN>
```

**Body :**
```json
{
  "pseudo": "newPseudo",
  "prenom": "Jean",
  "nom": "Dupont",
  "telephone": "0600000000",
  "adresse_rue": "1 rue test",
  "adresse_ville": "Paris",
  "adresse_code_postal": "75000",
  "adresse_pays": "France",
  "photo_profil": "",
  "bio": "Nouvelle bio"
}
```

**Réponse (200) :**
```json
{
  "message": "profile updated"
}
```

---

#### GET `/profile/{id}`

**Description :** Consulter le profil public d'un utilisateur.

**Accès :** Public

**Données retournées :** pseudo, bio, photo_profil

**URL :**
```http
GET http://localhost:8080/profile/1
```

---

#### GET `/profiles`

**Description :** Lister tous les profils publics utilisateurs.

**Accès :** Public

**Utilisation :** Afficher une page communauté ou une liste de membres.

**URL :**
```http
GET http://localhost:8080/profiles
```

**Réponse (200) :**
```json
[
  {
    "id_user": 1,
    "pseudo": "user1",
    "photo_profil": "",
    "bio": "Utilisateur test"
  },
  {
    "id_user": 2,
    "pseudo": "user2",
    "photo_profil": "",
    "bio": "Deuxième utilisateur"
  }
]
```

---

### 👨‍💼 Gestion des Utilisateurs (Admin/Staff)

#### GET `/users`

**Description :** Lister tous les utilisateurs.

**Accès :** ADMIN, STAFF

**URL :**
```http
GET http://localhost:8080/users
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

---

#### GET `/users/{id}`

**Description :** Récupérer les détails d'un utilisateur spécifique.

**Accès :** ADMIN, STAFF

**URL :**
```http
GET http://localhost:8080/users/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

---

#### PUT `/users/{id}`

**Description :** Modifier les informations d'un utilisateur.

**Accès :** ADMIN uniquement

**URL :**
```http
PUT http://localhost:8080/users/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN>
```

**Body :**
```json
{
  "email": "user@test.com",
  "pseudo": "user1",
  "prenom": "Jean",
  "nom": "Dupont",
  "telephone": "0600000000",
  "adresse_rue": "1 rue test",
  "adresse_ville": "Paris",
  "adresse_code_postal": "75000",
  "adresse_pays": "France",
  "photo_profil": "",
  "bio": "Bio modifiée",
  "statut": "actif",
  "id_role": 3
}
```

---

#### DELETE `/users/{id}`

**Description :** Supprimer un utilisateur de la plateforme.

**Accès :** ADMIN uniquement

**URL :**
```http
DELETE http://localhost:8080/users/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN>
```

---

### 🚫 Modération

#### PUT `/users/{id}/ban`

**Description :** Bannir un utilisateur de la plateforme.

**Accès :** ADMIN uniquement

**URL :**
```http
PUT http://localhost:8080/users/2/ban
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN>
```

**Body :**
```json
{
  "ban_reason": "comportement inapproprié",
  "ban_until": "2026-03-25 23:59:59"
}
```

| Champ | Description |
|-------|-------------|
| `ban_reason` | Raison du bannissement |
| `ban_until` | Date/heure de fin du bannissement |

**Réponse (200) :**
```json
{
  "message": "user banned"
}
```

**Vérification :**
```http
GET http://localhost:8080/users/2
```
Vous devriez voir `"is_banned": true` et `"ban_reason": "comportement inapproprié"`.

---

#### PUT `/users/{id}/unban`

**Description :** Retirer le bannissement d'un utilisateur.

**Accès :** ADMIN uniquement

**URL :**
```http
PUT http://localhost:8080/users/2/unban
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN>
```

**Réponse (200) :**
```json
{
  "message": "user unbanned"
}
```

**Vérification :** `"is_banned": false` et `"ban_reason": null`

---

### ✅ Approbation des PROs

#### PUT `/users/{id}/approve`

**Description :** Approuver un compte PRO en attente.

**Accès :** STAFF uniquement

**URL :**
```http
PUT http://localhost:8080/users/2/approve
```

**Headers :**
```
Authorization: Bearer <TOKEN_STAFF>
```

**Réponse (200) :**
```json
{
  "message": "user approved"
}
```

---

#### GET `/pros/pending`

**Description :** Lister les comptes PRO en attente d'approbation.

**Accès :** STAFF uniquement

**URL :**
```http
GET http://localhost:8080/pros/pending
```

**Headers :**
```
Authorization: Bearer <TOKEN_STAFF>
```

**Réponse (200) :**
```json
[
  {
    "id_user": 6,
    "email": "pro@test.com",
    "pseudo": "atelier_pro"
  }
]
```

---

#### POST `/admin/users`

**Description :** Créer un utilisateur via l'interface admin.

**Accès :** ADMIN uniquement

Permet de créer les 4 types de comptes : ADMIN, STAFF, USER, PRO.

**URL :**
```http
POST http://localhost:8080/admin/users
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN>
```

**Exemple : Créer un STAFF**
```json
{
  "email": "staff@upcycleconnect.fr",
  "password": "Password123!",
  "pseudo": "staff_upcycleconnect",
  "prenom": "Equipe",
  "nom": "Staff",
  "photo_profil": "",
  "bio": "Compte staff UpcycleConnect",
  "role_id": 2
}
```

**Exemple : Créer un ADMIN**
```json
{
  "email": "admin2@upcycleconnect.fr",
  "password": "Password123!",
  "pseudo": "admin2_upcycleconnect",
  "prenom": "Second",
  "nom": "Admin",
  "photo_profil": "",
  "bio": "Compte admin secondaire",
  "role_id": 1
}
```

**Réponse (201) :**
```json
{
  "message": "admin user created"
}
```

---

### 🏷️ Catégories

#### GET `/categories`

**Description :** Lister toutes les catégories.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/categories
```

---

#### GET `/categories/{id}`

**Description :** Récupérer une catégorie spécifique.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/categories/1
```

**Réponse (200) :**
```json
{
  "id": 1,
  "nom": "Atelier",
  "description": "Ateliers créatifs"
}
```

---

#### POST `/categories`

**Description :** Créer une nouvelle catégorie.

**Accès :** ADMIN, STAFF

**URL :**
```http
POST http://localhost:8080/categories
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Body :**
```json
{
  "nom": "Atelier",
  "description": "Ateliers créatifs"
}
```

---

#### PUT `/categories/{id}`

**Description :** Modifier une catégorie.

**Accès :** ADMIN, STAFF

**URL :**
```http
PUT http://localhost:8080/categories/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Body :**
```json
{
  "nom": "Atelier modifié",
  "description": "Description modifiée"
}
```

**Réponse (200) :**
```json
{
  "message": "category updated"
}
```

---

#### DELETE `/categories/{id}`

**Description :** Supprimer une catégorie.

**Accès :** ADMIN, STAFF

**URL :**
```http
DELETE http://localhost:8080/categories/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Réponse (200) :**
```json
{
  "message": "category deleted"
}
```

---

### 🛠️ Prestations

#### GET `/prestations`

**Description :** Lister toutes les prestations disponibles.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/prestations
```

---

#### GET `/prestations/{id}`

**Description :** Récupérer les détails d'une prestation.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/prestations/1
```

---

#### POST `/prestations`

**Description :** Créer une nouvelle prestation.

**Accès :** PRO approuvé uniquement

**Conditions :**
- ✅ Être authentifié
- ✅ Avoir `role_id = 4` (PRO)
- ✅ Avoir `is_approved = true`

**URL :**
```http
POST http://localhost:8080/prestations
```

**Headers :**
```
Authorization: Bearer <TOKEN_PRO_APPROUVE>
```

**Body :**
```json
{
  "titre": "Atelier couture",
  "description": "atelier couture",
  "type": "atelier",
  "prix": 20,
  "is_active": true,
  "id_categorie": 1
}
```

**Réponse (201) :**
```json
{
  "message": "prestation created"
}
```

---

#### PUT `/prestations/{id}`

**Description :** Modifier une prestation.

**Accès :** ADMIN, STAFF

**URL :**
```http
PUT http://localhost:8080/prestations/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Body :**
```json
{
  "titre": "Atelier couture avancé",
  "description": "atelier couture avancé",
  "type": "atelier",
  "prix": 25,
  "is_active": true,
  "id_categorie": 1
}
```

**Réponse (200) :**
```json
{
  "message": "prestation updated"
}
```

---

#### DELETE `/prestations/{id}`

**Description :** Supprimer une prestation.

**Accès :** ADMIN, STAFF

**URL :**
```http
DELETE http://localhost:8080/prestations/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Réponse (200) :**
```json
{
  "message": "prestation deleted"
}
```

---

### 📅 Événements

#### GET `/events`

**Description :** Lister tous les événements.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/events
```

---

#### GET `/events/{id}`

**Description :** Récupérer les détails d'un événement.

**Accès :** Public

**URL :**
```http
GET http://localhost:8080/events/1
```

**Réponse (200) :**
```json
{
  "id": 1,
  "date_debut": "2026-03-10 10:00:00",
  "date_fin": "2026-03-10 12:00:00",
  "lieu": "Paris",
  "capacite_max": 10,
  "statut": "actif",
  "id_prestation": 1,
  "id_validateur": 1
}
```

---

#### POST `/events`

**Description :** Créer un nouvel événement.

**Accès :** PRO approuvé uniquement

**Conditions :**
- ✅ Être authentifié
- ✅ Avoir `role_id = 4` (PRO)
- ✅ Avoir `is_approved = true`

**URL :**
```http
POST http://localhost:8080/events
```

**Headers :**
```
Authorization: Bearer <TOKEN_PRO_APPROUVE>
```

**Body :**
```json
{
  "date_debut": "2026-03-10 10:00:00",
  "date_fin": "2026-03-10 12:00:00",
  "lieu": "Paris",
  "capacite_max": 10,
  "statut": "actif",
  "id_prestation": 1
}
```

**Réponse (201) :**
```json
{
  "message": "event created"
}
```

---

#### PUT `/events/{id}`

**Description :** Modifier un événement.

**Accès :** ADMIN, STAFF

**URL :**
```http
PUT http://localhost:8080/events/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Body :**
```json
{
  "date_debut": "2026-03-10 14:00:00",
  "date_fin": "2026-03-10 16:00:00",
  "lieu": "Paris",
  "capacite_max": 15,
  "statut": "actif",
  "id_prestation": 1,
  "id_validateur": 1
}
```

**Réponse (200) :**
```json
{
  "message": "event updated"
}
```

---

#### DELETE `/events/{id}`

**Description :** Supprimer un événement.

**Accès :** ADMIN, STAFF

**URL :**
```http
DELETE http://localhost:8080/events/1
```

**Headers :**
```
Authorization: Bearer <TOKEN_ADMIN_OU_STAFF>
```

**Réponse (200) :**
```json
{
  "message": "event deleted"
}
```

---

### 🏥 Santé de l'API

#### GET `/health`

**Description :** Vérifier l'état de l'API.

Utile pour les tests rapides et le monitoring.

**URL :**
```http
GET http://localhost:8080/health
```

**Réponse (200) :**
```json
{
  "status": "ok"
}
```

---

## 🧪 Plan de Test Recommandé

Suivez cet ordre pour tester l'API de manière systématique :

### Phase 1️⃣ : Initialisation
- [ ] Insérer l'admin de base via SQL
- [ ] Lancer l'API avec `go run .`
- [ ] Tester `GET /health`

### Phase 2️⃣ : Authentification Admin
- [ ] `POST /login` avec l'admin
- [ ] Copier le token généré

### Phase 3️⃣ : Création d'Utilisateurs Admin
- [ ] `POST /admin/users` pour créer un STAFF
- [ ] `POST /admin/users` pour créer un PRO admin
- [ ] `POST /admin/users` pour créer un ADMIN secondaire

### Phase 4️⃣ : Inscription Publique
- [ ] `POST /register` pour créer un USER
- [ ] `POST /register` pour créer un PRO public
- [ ] `POST /login` avec ce PRO (doit être refusé, non approuvé)

### Phase 5️⃣ : Approbation PRO
- [ ] `GET /pros/pending` avec le STAFF (vérifier le PRO)
- [ ] `PUT /users/{id}/approve` avec le STAFF
- [ ] `POST /login` avec le PRO (doit fonctionner maintenant)

### Phase 6️⃣ : Profil Utilisateur
- [ ] `GET /me`
- [ ] `PUT /me/update`
- [ ] `GET /profiles`
- [ ] `GET /profile/{id}`

### Phase 7️⃣ : Gestion des Utilisateurs (Admin)
- [ ] `GET /users`
- [ ] `GET /users/{id}`
- [ ] `PUT /users/{id}`
- [ ] `PUT /users/{id}/ban` avec raison
- [ ] Vérifier que `is_banned = true`
- [ ] `PUT /users/{id}/unban`
- [ ] Vérifier que `is_banned = false`
- [ ] `DELETE /users/{id}`

### Phase 8️⃣ : Catégories
- [ ] `POST /categories`
- [ ] `GET /categories`
- [ ] `GET /categories/{id}`
- [ ] `PUT /categories/{id}`
- [ ] `DELETE /categories/{id}`

### Phase 9️⃣ : Prestations (PRO approuvé)
- [ ] `POST /prestations` avec le PRO approuvé
- [ ] `GET /prestations`
- [ ] `GET /prestations/{id}`
- [ ] `PUT /prestations/{id}` avec un ADMIN
- [ ] `DELETE /prestations/{id}` avec un ADMIN

### Phase 🔟 : Événements (PRO approuvé)
- [ ] `POST /events` avec le PRO approuvé
- [ ] `GET /events`
- [ ] `GET /events/{id}`
- [ ] `PUT /events/{id}` avec un ADMIN
- [ ] `DELETE /events/{id}` avec un ADMIN

---

## 📝 Notes Importantes

### Workflow PRO
```
PRO non approuvé → ❌ Impossible de se connecter
                ](#)
