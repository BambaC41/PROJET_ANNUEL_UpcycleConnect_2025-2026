# FLOW D'EXÉCUTION - AVANT vs APRÈS

## 🔴 AVANT LA CORRECTION (Crash)

```
User Login → login.php
    ↓
Session created (role_id = 3 = particulier)
    ↓
particulier.php
    ↓
include particulier_bootstrap.php
    ↓
[LINE 36] function e($value) ← DÉCLARÉE ✓
[LINE 41] function formatDateFr($date) ← DÉCLARÉE ✓
    ↓
Include some views/functions that require view_context.php
    ↓
include view_context.php
    ↓
[LINE 23] function formatDateFr($date) ← TENTATIVE DE REDÉCLARATION ❌
    ↓
💥 FATAL ERROR: Cannot redeclare formatDateFr()
    ↓
Application CRASH - Page non disponible
```

---

## 🟢 APRÈS LA CORRECTION (OK)

```
User Login → login.php
    ↓
Session created (role_id = 3 = particulier)
    ↓
particulier.php
    ↓
include particulier_bootstrap.php
    ↓
require_once view_context.php [AJOUTÉ]
    ↓
include view_context.php
    ↓
[LINE 23] if (!function_exists('formatDateFr')) ← CHECK: n'existe pas ✓
          function formatDateFr($date) ← DÉCLARÉE ✓
    ↓
[LINE 37] if (!function_exists('e')) ← CHECK: n'existe pas ✓
          function e($value) ← DÉCLARÉE ✓
    ↓
Retour à particulier_bootstrap.php
    ↓
[LINE 44] if (!function_exists('formatDateFr')) ← CHECK: existe déjà ✗
          (la fonction n'est PAS redéclarée)
    ↓
[LINE 37] if (!function_exists('e')) ← CHECK: existe déjà ✗
          (la fonction n'est PAS redéclarée)
    ↓
Continue script execution normally
    ↓
✅ Page renders successfully
    ↓
formatDateFr('2025-05-12') → '12/05/2025 00:00' ✓
e('<script>') → '&lt;script&gt;' ✓
```

---

## 📊 CHARGE D'INCLUSIONS

### Include Graph
```
        login.php
           ↓
        Session Start
           ↓
      particulier.php
           ↓
   particulier_bootstrap.php
   /        |         |         \
  /         |         |          \
view_context.php  annonce.php  paiements.php  ...
         |                            
      api_core.php              

Avant: formatDateFr() déclarée en 2 places → CONFLIT
Après: formatDateFr() déclarée EN UNE PLACE via if (!function_exists)
```

---

## 💾 ÉTAT DES FONCTIONS APRÈS CORRECTION

| Fonction | Fichier | Status |
|----------|---------|--------|
| formatDateFr | view_context.php | ✅ Déclarée (1ère) |
| formatDateFr | particulier_bootstrap.php | ⏭️ Skipped (existe déjà) |
| formatDateFr | pro_bootstrap.php | ⏭️ Skipped (existe déjà) |
| formatDateFr | admin_bootstrap.php | ⏭️ Skipped (existe déjà) |
| formatDateFr | employee_bootstrap.php | ⏭️ Skipped (existe déjà) |
| **RÉSULTAT** | **1 seule déclaration** | ✅ |
| e() | view_context.php | ✅ Déclarée (1ère) |
| e() | particulier_bootstrap.php | ⏭️ Skipped (existe déjà) |
| e() | pro_bootstrap.php | ⏭️ Skipped (existe déjà) |
| e() | admin_bootstrap.php | ⏭️ Skipped (existe déjà) |
| e() | employee_bootstrap.php | ⏭️ Skipped (existe déjà) |
| **RÉSULTAT** | **1 seule déclaration** | ✅ |

---

## 🎯 RÉSULTAT FINAL

✅ **Aucune redéclaration**  
✅ **Fonctions accessibles partout**  
✅ **Zero crash**  
✅ **Performance inchangée** (require_once déjà optimisé)

