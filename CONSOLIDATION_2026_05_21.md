# UpcycleConnect - Passe de Consolidation Métier & UX (21 mai 2026)

## Objectif
Améliorer les pages existantes sans refonte complète, pour rendre le site **beaucoup plus crédible** devant un jury en présentant une vision cohérente de la Mission 1.

## ✅ Fichiers Créés

### 1. `annonce_detail.php`
- Page de détail d'une annonce
- Affichage complet : titre, image cadrée (aspect-ratio 1:1), description, prix
- Statuts : Disponible / Réservé / Vendu / Indisponible
- Informations vendeur
- Lien retour vers galerie

### 2. `particulier_score.php`
- **Upcycling Score** - page principale accessible
- Affichage du score total avec badges (Débutant / Recycleur actif / Ambassadeur)
- Détail des contributions : annonces validées, dépôts réalisés, formations
- Calcul d'impact écologique : poids détourné, CO₂ évité
- Progression vers prochain badge
- Conseil pour augmenter le score

### 3. `conseil_detail.php`
- Page de détail d'un conseil
- Image cadrée (aspect-ratio 16:9, object-fit cover)
- Contenu complet avec auteur et date
- Navigation retour

### 4. `forum.php`
- Vrai forum de communauté accessible aux particuliers/pros
- Création de sujets avec titre et contenu
- Consultation des sujets avec compte des réponses
- Page de détail d'un sujet avec réponses
- Ajout de réponses à un sujet
- Statuts: visible/caché (géré côté modération salarié)

## ✅ Fichiers Améliorés

### 1. `particulier_annonces.php`
**Avant** : Simple table peu attrayante
**Après** :
- Grille de cartes pour "Mes annonces"
- Grille de cartes pour "Galerie des objets upcyclables" (renommé de "Annonces publiques validées")
- Images cadrées (aspect-ratio 16:9, object-fit cover)
- Badges de statut clairs : Disponible/Réservé/Vendu
- Bouton "Voir détails" vers annonce_detail.php
- Design cohérent avec cartes au survol

### 2. `pro_billing.php`
**Avant** : Simple liste d'abonnements
**Après** :
- Section "Plans d'abonnement" avec cartes 3 plans : Gratuit / Premium 29€/mois / Premium 290€/an
- Affichage de l'abonnement actuel avec badge de statut
- Section "Campagnes publicitaires" avec bouton création
- Tableau de factures/paiements filtrable
- Badges de statut colorés (Payé/En attente)
- KPIs en haut

### 3. `admin_finance.php`
**Avant** : Simple table d'affichage
**Après** :
- KPIs : Chiffre d'affaires / Paiements payés / Paiements en attente / Abonnements actifs
- Tableau des sources de revenus avec colonnes : Source / HT / TVA 20% / TTC / Détails
- Sources alignées avec annexe : Abonnements / Commissions annonces / Campagnes / Formations
- Tableau des paiements avec filtrage
- Design professionnel avec couleurs indicatrices

### 4. `pro_projects.php`
**Avant** : Simple form et liste texte
**Après** :
- Formulaire enrichi : titre / description / statut / progression / poids valorisé
- Grille de cartes pour "Mes projets"
- Chaque carte : titre, statut badge, barre de progression colorée, poids valorisé
- Actions : Modifier / Supprimer
- Statuts : Brouillon / En cours / Terminé / Publié / Archivé

### 5. `admin_catalog.php`
**Avant** : Simple table sans création
**Après** :
- Formulaire de création de prestation : titre / type / catégorie / prix / description / actif checkbox
- Tableau avec actions : Activer/Désactiver pour chaque prestation
- Badges de type et statut
- Design plus intuitif

### 6. `pro_annonces.php`
**Renommé en "Marketplace"**
- Grille de cartes au lieu de table
- Chaque carte : image (16:9), titre, description courte, badge statut, prix
- Actions : Réserver (don) / Acheter (vente) / Indisponible / Détails
- Filtres : Recherche / Mode (Don/Vente)
- Statuts clairs : Disponible / Réservé / Vendu

## 📋 Amélioration Générales UX/Design

### Images
- **Toutes les images** : aspect-ratio 16:9, object-fit cover, border-radius 12px
- **Fichiers concernés** : particulier_annonces.php, pro_annonces.php, conseil_detail.php, pro_projects.php
- Placeholder gris pour images manquantes

### Cartes
- **Grilles cohérentes** : CSS grid, auto-fit, gap 16px
- **Hover effect** : translateY(-4px), box-shadow augmentée
- **Badges** : inline-block, padding 4-8px, border-radius 12-20px, couleurs distinctes

### Badges de statut
- ✅ Disponible / ✅ Actif : `#d4edda` (vert)
- ⏳ En attente / ⏳ Pending : `#fff3cd` (orange)
- ❌ Réservé / ❌ En modération : `#d1ecf1` (bleu)
- ❌ Vendu / ❌ Inactif : `#f8d7da` (rouge)

### Tablettes et responsive
- Media queries existantes respectées
- Grilles se réajustent sur mobile

## 🌍 Multilingue

### Fichier : `i18n/fr.php`
Ajout de clés pour :
- Annonces : gallery_title, gallery_subtitle, see_details, available, reserved, sold
- Upcycling Score : title, subtitle, achievements, beginner, active_recycler, ambassador
- Forum : title, subtitle, create_topic, recent_topics, replies
- Conseils : details, back
- Pro Billing : title, plans, current, campaigns, invoices, subscribe
- Pro Projects : title, create, progress, my_projects, edit, delete
- Admin : finance, catalog, add_prestation, prestations, activate, deactivate
- Common : loading, error, success, back, cancel, save, delete, edit, create, no_data

## 📊 SQL & Schéma

### Pas de nouveau schéma requis
Toutes les amélioration réutilisent les tables existantes :
- `annonce` (statut, id_reserve_par, id_acheteur) ✓
- `forum_topic`, `forum_message` ✓
- `conseil` ✓
- `abonnement_pro`, `campagne_publicitaire` ✓
- `projet_upcycling` ✓
- `prestation` ✓
- `paiement` ✓

### Colonnes utilisées
- **annonce** : id_annonce, titre, description, mode, prix, statut, photo_url, id_user, id_reserve_par, id_acheteur, created_at, validated_at
- **forum_topic** : id_topic, id_user, titre, contenu, statut, created_at, updated_at
- **forum_message** : id_message, id_topic, id_user, contenu, statut, created_at
- **conseil** : id_conseil, titre, contenu, categorie, image_url, is_active, id_auteur, created_at
- **abonnement_pro** : id_abonnement, id_pro, formule, date_debut, date_fin, prix, statut, created_at
- **campagne_publicitaire** : id_campagne, id_pro, titre, description, budget, date_debut, date_fin, statut
- **projet_upcycling** : id_projet, id_pro, titre, description, statut, progression, image_url, poids_valorise (si colonne)
- **prestation** : id_prestation, titre, description, type, prix, is_active, id_categorie

## 🧪 Tests à Faire

### 1. Particulier - Annonces
- [ ] Créer annonce (don/vente)
- [ ] Voir galerie
- [ ] Cliquer sur "Voir détails" → annonce_detail.php
- [ ] Vérifier images et statuts

### 2. Particulier - Score
- [ ] Cliquer "Upcycling Score" (navbar ou dashboard)
- [ ] Voir score, badges, contributions
- [ ] Vérifier progression barre

### 3. Pro - Marketplace
- [ ] Voir grille annonces avec images
- [ ] Réserver don (si disponible)
- [ ] Acheter vente → paiement_checkout_demo
- [ ] Vérifier statuts vendues/réservées restent visibles

### 4. Pro - Billing
- [ ] Voir plans (Gratuit/Premium 29€/Premium 290€)
- [ ] Voir abonnement actuel
- [ ] Voir campagnes
- [ ] Voir factures/paiements

### 5. Pro - Projects
- [ ] Créer projet
- [ ] Voir grille cartes
- [ ] Vérifier barre progression colorée
- [ ] Modifier/Supprimer projet

### 6. Admin - Finance
- [ ] KPIs affichés
- [ ] Table sources revenus avec HT/TVA/TTC
- [ ] Table paiements avec statuts

### 7. Admin - Catalog
- [ ] Voir formulaire création
- [ ] Voir table prestations
- [ ] Activer/Désactiver une prestation

### 8. Forum
- [ ] Créer topic
- [ ] Voir liste topics (avec count réponses)
- [ ] Ouvrir topic → voir messages
- [ ] Ajouter réponse

### 9. Conseils
- [ ] Voir conseil_detail.php
- [ ] Vérifier image cadrée
- [ ] Retour à particulier_conseils.php

## 📈 Résumé des Améliorations

| Domaine | Avant | Après |
|---------|-------|-------|
| **Annonces** | Table simple | Grilles cartes, images cadrées, statuts visibles, page détail |
| **Score** | Dashboard uniquement | Page dédiée, badges, calcul impact, progression |
| **Conseils** | Liste simple | Page détail, image cadrée, contenu complet |
| **Forum** | Page modération salarié | Forum public particulier/pro + modération salarié |
| **Pro Billing** | Liste simple | Plans affichés, KPIs, sources revenus, factures |
| **Pro Projects** | Form simple | Cartes, barre progression, actions claires |
| **Admin Finance** | Table simple | KPIs, sources revenus avec HT/TVA/TTC |
| **Admin Catalog** | List uniquement | Formulaire création, action activer/désactiver |

## 🚀 Impact Jury

**Avant** : Pages basiques, peu de cohérence UX, pas d'accès à fonctionnalités clés (score, forum, détail conseils).

**Après** :
- ✅ Pages professionnelles avec cartes, images, badges
- ✅ Tous les flux métier accessibles (annonces → détail, score accessible, forum public)
- ✅ Données financières claires (sources revenus, factures, abonnements)
- ✅ Gestion projets/catalog complète
- ✅ Cohérence visuelle & design responsive

## 📝 Notes Techniques

### Pas de migrations SQL
- Réutilisation des colonnes existantes
- Pas d'ajout de tables
- Schemas vérifiés compatibles

### API Fallback
- `api_get_annonce()` → fallback BD locale
- `api_get_mon_score()` → fallback BD locale
- `api_get_conseil()` → fallback BD locale

### Fonctions d'aide existantes
- `formatPriceEur()` ✓
- `formatDateFr()` ✓
- `e()` (escape) ✓
- `vc_media_url()` ✓

## 🔍 Points d'Attention

1. **Pro_annonces.php** : Correction CSS/HTML en cours (conversion table → grille)
2. **Forum modération salarié** : Reste dans `salarie_forum.php` (modération séparée)
3. **Stripe/OneSignal** : Reste en mode démo
4. **API Go** : Utilisée si disponible, fallback PDO
5. **Images** : Placeholder si absentes, pas d'images embarquées

## ✨ Prochaines Étapes (Optionnel)

- [ ] Ajouter recherche avancée annonces
- [ ] Importer/exporter factures CSV
- [ ] Notifications push (OneSignal)
- [ ] Dashboard analytics personnalisé
- [ ] Export PDF factures détaillés
