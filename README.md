# Documentation de test API — UpcycleConnect

## Base URL
http://localhost:8080

### Préparation

#### Base de données
La base de données doit déjà être créée et initialisée.

##### Insérer l’admin de base
Exécuter cette requête SQL dans MySQL pour créer le premier administrateur du site.

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

Identifiants admin

Email  
admin@upcycleconnect.fr

Mot de passe  
Password123!

###### Lancer l’API
```bash
go run .
```

###### Résultat attendu
```text
Server running on http://localhost:8080
```

##### Authentification

Header pour routes protégées

Authorization: Bearer TOKEN

Rôles

| id_role | rôle |
|---|---|
| 1 | ADMIN |
| 2 | STAFF |
| 3 | USER |
| 4 | PRO |

##### Fonctionnalités de l’API

- authentification
- profils utilisateurs
- utilisateurs
- approbation des PRO
- catégories
- prestations
- événements

###### Routes API

POST /register

###### Description
Créer un compte public.

Cette route permet uniquement de créer :
- USER
- PRO

Comportement spécial :
- un USER est créé avec `is_approved = true`
- un PRO est créé avec `is_approved = false`
- un PRO devra être approuvé par un STAFF avant de pouvoir se connecter

URL  
POST http://localhost:8080/register

Body JSON

Exemple inscription USER
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

Exemple inscription PRO
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

###### Réponse attendue
```json
{
  "message": "user created"
}
```

##### POST /login

Description  
Connexion avec email et mot de passe.

Retourne :
- un token JWT
- le `role_id`
- le `user_id`
- le statut `is_approved`

Comportement spécial :
- un PRO non approuvé ne peut pas se connecter

URL  
POST http://localhost:8080/login

Exemple login admin
```json
{
  "email": "admin@upcycleconnect.fr",
  "password": "Password123!"
}
```

Exemple login user
```json
{
  "email": "user@test.com",
  "password": "Password123!"
}
```

Exemple login pro
```json
{
  "email": "pro@test.com",
  "password": "Password123!"
}
```

##### Réponse attendue
```json
{
  "token": "JWT_TOKEN",
  "role_id": 4,
  "user_id": 12,
  "is_approved": true
}
```

Si le compte PRO n’est pas encore approuvé, la connexion est refusée.

##### GET /me

Description  
Voir les informations du compte connecté.

URL  
GET http://localhost:8080/me

Header  
Authorization: Bearer TOKEN

##### PUT /me/update

Description  
Modifier le profil du compte connecté.

URL  
PUT http://localhost:8080/me/update

Header  
Authorization: Bearer TOKEN

Body JSON
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

Réponse attendue
```json
{
  "message": "profile updated"
}
```

###### GET /profile/{id}

Description  
Voir le profil public d’un utilisateur.

Données retournées
- pseudo
- bio
- photo_profil

URL  
GET http://localhost:8080/profile/1

###### GET /profiles

Description  
Voir la liste des profils publics des utilisateurs.

Données retournées
- id_user
- pseudo
- bio
- photo_profil

URL  
GET http://localhost:8080/profiles

Réponse exemple
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

Utilisation  
Cette route permet d’afficher la liste des profils publics dans l’application  
(par exemple une page communauté ou une liste de membres).

GET /users

Description  
Voir tous les utilisateurs.

Accès
- ADMIN
- STAFF

URL  
GET http://localhost:8080/users

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

###### GET /users/{id}

Description  
Voir un utilisateur précis.

Accès
- ADMIN
- STAFF

URL  
GET http://localhost:8080/users/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

PUT /users/{id}

Description  
Modifier un utilisateur.

Accès
- ADMIN

URL  
PUT http://localhost:8080/users/1

Header  
Authorization: Bearer TOKEN_ADMIN

Body JSON
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

###### DELETE /users/{id}

Description  
Supprimer un utilisateur.

Accès
- ADMIN

URL  
DELETE http://localhost:8080/users/1

Header  
Authorization: Bearer TOKEN_ADMIN

###### PUT /users/{id}/ban

Description  
Permet de bannir un utilisateur.

Accès
- ADMIN

URL  
PUT http://localhost:8080/users/2/ban

Header  
Authorization: Bearer TOKEN_ADMIN

Body JSON
```json
{
  "ban_reason": "comportement inapproprié",
  "ban_until": "2026-03-25 23:59:59"
}
```

Champs

ban_reason  
raison du bannissement

ban_until  
date de fin du bannissement

Réponse attendue
```json
{
  "message": "user banned"
}
```

###### PUT /users/{id}/unban

Description  
Permet de retirer le bannissement d’un utilisateur.

Accès
- ADMIN

URL  
PUT http://localhost:8080/users/2/unban

Header  
Authorization: Bearer TOKEN_ADMIN

Réponse attendue
```json
{
  "message": "user unbanned"
}
```

###### PUT /users/{id}/approve

Description  
Permet à un STAFF d’approuver un compte PRO en attente.

Accès
- STAFF

URL  
PUT http://localhost:8080/users/2/approve

Header  
Authorization: Bearer TOKEN_STAFF

Réponse attendue
```json
{
  "message": "user approved"
}
```

###### GET /pros/pending

Description  
Voir la liste des comptes PRO en attente d’approbation.

Accès
- STAFF

URL  
GET http://localhost:8080/pros/pending

Header  
Authorization: Bearer TOKEN_STAFF

Réponse exemple
```json
[
  {
    "id_user": 6,
    "email": "pro@test.com",
    "pseudo": "atelier_pro"
  }
]
```

###### Vérification du bannissement

Après avoir banni un utilisateur, vérifier avec :

GET http://localhost:8080/users/2

Dans la réponse JSON on doit voir :

```json
"is_banned": true
```

et

```json
"ban_reason": "comportement inapproprié"
```

###### Vérification du débannissement

Après un appel à :

PUT /users/{id}/unban

refaire :

GET http://localhost:8080/users/{id}

Le résultat attendu est :

```json
"is_banned": false
```

et

```json
"ban_reason": null
```

###### POST /admin/users

Description  
Créer un utilisateur via l’API admin.

Accès
- ADMIN

Cette route permet à un administrateur de créer un compte de type :
- ADMIN
- STAFF
- USER
- PRO

URL  
POST http://localhost:8080/admin/users

Header  
Authorization: Bearer TOKEN_ADMIN

Body JSON

Exemple création STAFF
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

Exemple création ADMIN
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

Exemple création PRO
```json
{
  "email": "pro@upcycleconnect.fr",
  "password": "Password123!",
  "pseudo": "pro_upcycleconnect",
  "prenom": "Prestataire",
  "nom": "Pro",
  "photo_profil": "",
  "bio": "Compte professionnel",
  "role_id": 4
}
```

Réponse attendue
```json
{
  "message": "admin user created"
}
```

###### GET /categories

Description  
Voir la liste des catégories.

GET http://localhost:8080/categories

###### GET /categories/{id}

Description  
Voir une catégorie précise.

URL  
GET http://localhost:8080/categories/1

Réponse exemple
```json
{
  "id": 1,
  "nom": "Atelier",
  "description": "Ateliers créatifs"
}
```

###### POST /categories

Description  
Créer une catégorie.

Accès
- ADMIN
- STAFF

URL  
POST http://localhost:8080/categories

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Body JSON
```json
{
  "nom": "Atelier",
  "description": "Ateliers créatifs"
}
```

###### PUT /categories/{id}

Description  
Modifier une catégorie.

Accès
- ADMIN
- STAFF

URL  
PUT http://localhost:8080/categories/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Body JSON
```json
{
  "nom": "Atelier modifié",
  "description": "Description modifiée"
}
```

Réponse attendue
```json
{
  "message": "category updated"
}
```

##### DELETE /categories/{id}

Description  
Supprimer une catégorie.

Accès
- ADMIN
- STAFF

URL  
DELETE http://localhost:8080/categories/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Réponse attendue
```json
{
  "message": "category deleted"
}
```

###### GET /prestations

Description  
Voir la liste des prestations.

GET http://localhost:8080/prestations

##### GET /prestations/{id}

Description  
Voir une prestation précise.

GET http://localhost:8080/prestations/1

##### POST /prestations

Description  
Créer une prestation.

Accès
- PRO approuvé uniquement

Conditions
- être connecté
- avoir `role_id = 4`
- avoir `is_approved = true`

URL  
POST http://localhost:8080/prestations

Header  
Authorization: Bearer TOKEN_PRO_APPROUVE

Body JSON
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

Réponse attendue
```json
{
  "message": "prestation created"
}
```

##### PUT /prestations/{id}

Description  
Modifier une prestation.

Accès
- ADMIN
- STAFF

URL  
PUT http://localhost:8080/prestations/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Body JSON
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

Réponse attendue
```json
{
  "message": "prestation updated"
}
```

##### DELETE /prestations/{id}

Description  
Supprimer une prestation.

Accès
- ADMIN
- STAFF

URL  
DELETE http://localhost:8080/prestations/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Réponse attendue
```json
{
  "message": "prestation deleted"
}
```

##### GET /events

Description  
Voir la liste des événements.

GET http://localhost:8080/events

##### GET /events/{id}

Description  
Voir un événement précis.

URL  
GET http://localhost:8080/events/1

Réponse exemple
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

##### POST /events

Description  
Créer un événement.

Accès
- PRO approuvé uniquement

Conditions
- être connecté
- avoir `role_id = 4`
- avoir `is_approved = true`

URL  
POST http://localhost:8080/events

Header  
Authorization: Bearer TOKEN_PRO_APPROUVE

Body JSON
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

Réponse attendue
```json
{
  "message": "event created"
}
```

###### PUT /events/{id}

Description  
Modifier un événement.

Accès
- ADMIN
- STAFF

URL  
PUT http://localhost:8080/events/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Body JSON
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

Réponse attendue
```json
{
  "message": "event updated"
}
```

###### DELETE /events/{id}

Description  
Supprimer un événement.

Accès
- ADMIN
- STAFF

URL  
DELETE http://localhost:8080/events/1

Header  
Authorization: Bearer TOKEN_ADMIN_OU_STAFF

Réponse attendue
```json
{
  "message": "event deleted"
}
```

###### GET /health

Description  
Permet de vérifier que l’API fonctionne correctement.

Cette route est utilisée pour les tests rapides ou les systèmes de monitoring.

URL  
GET http://localhost:8080/health

Réponse attendue
```json
{
  "status": "ok"
}
```

Utilisation  
Permet de vérifier que :
- l’API est bien lancée
- le serveur HTTP fonctionne

---

###### Vérification complémentaire des routes admin
Après connexion admin, il est recommandé de tester aussi :

- POST /admin/users
- GET /categories/{id}
- PUT /categories/{id}
- DELETE /categories/{id}
- GET /prestations/{id}
- PUT /prestations/{id}
- DELETE /prestations/{id}
- GET /events/{id}
- PUT /events/{id}
- DELETE /events/{id}
- PUT /users/{id}/ban
- PUT /users/{id}/unban

---

###### Vérification complémentaire du workflow PRO
Après inscription d’un PRO, il est recommandé de tester :

- POST /register avec `role_id = 4`
- POST /login avec ce PRO avant approbation → doit être refusé
- GET /pros/pending avec un STAFF
- PUT /users/{id}/approve avec un STAFF
- POST /login avec ce PRO après approbation → doit fonctionner
- POST /prestations avec ce PRO → doit fonctionner
- POST /events avec ce PRO → doit fonctionner

---

###### Ordre recommandé de test

- insérer l’admin de base en SQL
- lancer l’API
- POST /login avec l’admin
- copier le token admin
- POST /admin/users pour créer un staff, un pro ou un admin
- POST /register pour créer un user public
- POST /register pour créer un pro public
- tester POST /login avec le pro non approuvé
- tester GET /pros/pending avec le staff
- tester PUT /users/{id}/approve avec le staff
- retester POST /login avec le pro approuvé
- tester GET /me
- tester PUT /me/update
- tester GET /profiles
- tester GET /profile/{id}
- tester GET /users
- tester GET /users/{id}
- tester PUT /users/{id}
- tester PUT /users/{id}/ban
- tester PUT /users/{id}/unban
- tester DELETE /users/{id}
- créer une catégorie
- tester GET /categories/{id}
- tester PUT /categories/{id}
- tester DELETE /categories/{id}
- créer une prestation avec un PRO approuvé
- tester GET /prestations/{id}
- tester PUT /prestations/{id}
- tester DELETE /prestations/{id}
- créer un événement avec un PRO approuvé
- tester GET /events/{id}
- tester PUT /events/{id}
- tester DELETE /events/{id}
- tester GET /health
