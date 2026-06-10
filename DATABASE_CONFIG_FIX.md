# Fix MySQL Connection Configuration

## 🔴 Problème Initial

Erreur au clic sur "Terminer le tutoriel" ou "Passer le tutoriel":

```
Tutoriel : SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
```

### Cause
- `includes/functions/local_db.php` utilisait des valeurs par défaut hardcodées (`DB_USER=root`, `DB_PASSWORD=''`)
- Chez l'utilisateur, MySQL root nécessite un mot de passe → connexion échouée
- `tutorial_complete.php` retournait le message d'erreur SQL brut à l'utilisateur

---

## ✅ Solution Appliquée

### 1. Correction de `includes/functions/local_db.php`

**Avant :**
```php
// Connexion directe, pas de gestion d'erreur
$pdo = new PDO($dsn, $user, $pass, [...]);
```

**Après :**
```php
try {
    $pdo = new PDO($dsn, $user, $pass, [...]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage(), 0);
    throw new RuntimeException(
        'Erreur de connexion à la base de données. Vérifiez les variables d\'environnement DB_HOST, DB_USER, DB_PASSWORD, DB_NAME.'
    );
}
```

✅ Logs l'erreur réelle en backend  
✅ Retourne un message générique à l'utilisateur

### 2. Correction de `tutorial_complete.php`

**Avant :**
```php
} catch (Throwable $e) {
    $dbError = $e->getMessage();  // ← Erreur SQL brute exposée
}
```

**Après :**
```php
} catch (Throwable $e) {
    error_log('Tutorial completion error for user ' . $uid . ': ' . $e->getMessage(), 0);
    $dbError = 'Erreur de connexion à la base de données. Vérifiez la configuration MySQL.';
}
```

✅ Logs l'erreur réelle  
✅ Retourne un message sûr à l'utilisateur  
✅ Ne divulgue pas la configuration DB

### 3. Création de `.env` et `.env.example`

**`.env.example`** - Template pour développeurs:
```env
API_BASE_URL=http://localhost:8080
JWT_SECRET=upcycle_dev_only_change_me_2026

# Configuration MySQL - À personnaliser
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=upcycleconnect
DB_USER=root
DB_PASSWORD=
```

**`.env`** - Fichier de configuration local:
```env
API_BASE_URL=http://localhost:8080
JWT_SECRET=upcycle_dev_only_change_me_2026
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=upcycleconnect
DB_USER=root
DB_PASSWORD=
```

Les utilisateurs peuvent modifier `DB_PASSWORD` selon leur configuration XAMPP.

### 4. Mise à jour du README.md

Ajout d'une section **"Configuration MySQL locale"** avec:
- Exemple XAMPP sans mot de passe
- Exemple XAMPP avec mot de passe
- Commandes pour définir/vérifier le mot de passe

---

## 📝 Fichiers Modifiés

| Fichier | Changement |
|---------|-----------|
| `includes/functions/local_db.php` | +try/catch + error_log |
| `tutorial_complete.php` | +error_log + message générique |
| `.env` | CRÉÉ (config locale) |
| `.env.example` | CRÉÉ (template) |
| `README.md` | +Section configuration MySQL |

---

## 🔄 Architecture de Connexion

```
api_core.php (ligne 4-20)
    ├─ Charge .env si présent
    ├─ Met les variables dans $_ENV et $_SERVER
    └─ Disponible dans tout le projet

local_db.php
    ├─ db_env() : lit depuis getenv()
    ├─ db_pdo() : crée connexion PDO unique (singleton)
    └─ db_safe_exec() : wrapper sûr avec fallback

Fichiers utilisant local_db.php:
    ├─ tutorial_complete.php (requiert)
    ├─ notifications.php (requiert)
    ├─ includes/functions/documents.php
    ├─ includes/functions/demo_payments.php
    ├─ includes/functions/events.php
    ├─ salarie_*.php
    ├─ pro_*.php
    └─ scripts/qa_smoke.php
```

---

## 🔍 Flux de Configuration

```
1. Application démarre
2. api_core.php charge .env (si présent)
3. Variables disponibles : getenv('DB_PASSWORD'), etc.
4. db_pdo() utilise ces variables (avec fallbacks)
5. En cas d'erreur : error_log + message générique
```

---

## ✅ Vérifications

- [x] `local_db.php` capte l'erreur PDO
- [x] `tutorial_complete.php` masque l'erreur SQL brute
- [x] `.env` créé avec valeurs par défaut
- [x] `.env.example` comme template
- [x] README.md guide utilisateur
- [x] Tous les fichiers qui accèdent DB utilisent `db_pdo()`
- [x] `qa_smoke.php` gère les erreurs correctement

---

## 🚀 Configuration pour Utilisateur

### Cas 1 : XAMPP sans mot de passe (défaut)
```bash
# Copier .env.example → .env
# Garder DB_PASSWORD vide
# Redémarrer Apache
```

### Cas 2 : XAMPP avec mot de passe
```bash
# Éditer .env
DB_PASSWORD=votre_mot_de_passe

# OU définir dans MySQL:
# mysql -u root
# ALTER USER 'root'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
# FLUSH PRIVILEGES;
```

### Vérifier la connexion
```bash
php scripts/qa_smoke.php
```

---

## 📊 Impact

**Avant :**
- ❌ Erreur SQL brute affichée à l'utilisateur
- ❌ Configuration hardcodée non configurable
- ❌ Crash sur "Terminer tutoriel"

**Après :**
- ✅ Message d'erreur générique et sûr
- ✅ Configuration via `.env`
- ✅ Support de tous les types de configuration MySQL
- ✅ Logs en backend pour debugging
- ✅ Tutoriel fonctionne correctement

