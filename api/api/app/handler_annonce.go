package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

func AnnoncesHandler(w http.ResponseWriter, r *http.Request) {

	switch r.Method {
	case http.MethodGet:

		annonces, err := db.GetAnnoncesValidees()
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(annonces)

	case http.MethodPost:

		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}

		// VÉRIFICATION DES LIMITES D'ANNONCES POUR LES PROS
		if claims.RoleID == RolePro {
			if !CanUserCreateAnnonce(claims.UserID, claims.RoleID) {
				remaining := GetRemainingAnnoncesCount(claims.UserID, claims.RoleID)
				http.Error(w, "Limite d'annonces atteinte. Compte gratuit: 5 annonces max. Il vous reste "+strconv.Itoa(remaining)+" annonces disponibles. Passez à l'abonnement Premium pour plus d'annonces.", http.StatusForbidden)
				return
			}
		}

		var a model.Annonce
		if err := json.NewDecoder(r.Body).Decode(&a); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}

		if strings.TrimSpace(a.Titre) == "" {
			http.Error(w, "titre obligatoire", http.StatusBadRequest)
			return
		}

		if a.Mode != "don" && a.Mode != "vente" {
			http.Error(w, "mode invalide (don ou vente)", http.StatusBadRequest)
			return
		}

		if a.Mode == "don" {
			zero := 0.0
			a.Prix = &zero
		}

		if a.Mode == "vente" {
			if a.Prix == nil || *a.Prix <= 0 {
				http.Error(w, "prix invalide pour une vente", http.StatusBadRequest)
				return
			}
		}
		a.UserID = claims.UserID
		a.Statut = "en_attente"
		a.ValidateurID = nil
		a.ValidatedAt = nil

		err := db.CreateAnnonce(a)
		if err != nil {
			http.Error(w, "Insert error", http.StatusInternalServerError)
			return
		}

		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(map[string]string{
			"message": "annonce créée",
		})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func handleAnnonceModeration(w http.ResponseWriter, r *http.Request, annonceID int) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAdmin(w, r)
	if !ok {
		return
	}

	var req struct {
		Statut string `json:"statut"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	statut := strings.TrimSpace(req.Statut)
	if statut != "validee" && statut != "rejetee" {
		http.Error(w, "statut invalide (validee ou rejetee)", http.StatusBadRequest)
		return
	}

	annonce, err := db.GetAnnonceByID(annonceID)
	if err == nil && annonce != nil && statut == "validee" {
		go UpdateUpcyclingScore(annonce.UserID)
	}

	if err := db.ModerateAnnonce(annonceID, claims.UserID, statut); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{
		"message":    "annonce moderee",
		"id_annonce": annonceID,
		"statut":     statut,
	})
}

func AnnonceByIDHandler(w http.ResponseWriter, r *http.Request) {
	// Extraire l'ID de l'URL /annonces/123
	path := strings.TrimPrefix(r.URL.Path, "/annonces/")
	// Enlever un éventuel trailing slash
	path = strings.TrimSuffix(path, "/")

	if path == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	// Vérifier si c'est une route de modération /annonces/123/validate
	parts := strings.Split(path, "/")
	idStr := parts[0]

	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}

	// Route de modération
	if len(parts) == 2 && parts[1] == "validate" {
		handleAnnonceModeration(w, r, id)
		return
	}

	switch r.Method {
	case http.MethodGet:
		annonce, err := db.GetAnnonceByID(id)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		if annonce == nil {
			http.Error(w, "Annonce not found", http.StatusNotFound)
			return
		}

		// Vérifier les droits d'accès
		if annonce.Statut != "validee" {
			claims, err := getClaimsFromRequest(r)
			if err != nil {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			if claims.RoleID != RoleAdmin && claims.UserID != annonce.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(annonce)

	case http.MethodPut:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}

		var a model.Annonce
		if err := json.NewDecoder(r.Body).Decode(&a); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}

		if strings.TrimSpace(a.Titre) == "" {
			http.Error(w, "titre obligatoire", http.StatusBadRequest)
			return
		}

		if a.Mode != "don" && a.Mode != "vente" {
			http.Error(w, "mode invalide", http.StatusBadRequest)
			return
		}

		if a.Mode == "don" {
			zero := 0.0
			a.Prix = &zero
		}

		if a.Mode == "vente" {
			if a.Prix == nil || *a.Prix <= 0 {
				http.Error(w, "prix invalide", http.StatusBadRequest)
				return
			}
		}

		// Vérifier que l'annonce appartient bien à l'utilisateur
		existing, err := db.GetAnnonceByID(id)
		if err != nil || existing == nil {
			http.Error(w, "Annonce non trouvée", http.StatusNotFound)
			return
		}
		if existing.UserID != claims.UserID {
			http.Error(w, "Vous ne pouvez pas modifier cette annonce", http.StatusForbidden)
			return
		}

		// Conserver l'ID et l'UserID
		a.ID = id
		a.UserID = claims.UserID
		a.Statut = "en_attente" // Repasse en attente après modification
		a.ValidateurID = nil
		a.ValidatedAt = nil

		err = db.UpdateAnnonce(id, claims.UserID, a)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{
			"message": "annonce modifiée",
		})

	case http.MethodDelete:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}

		// Vérifier que l'annonce appartient à l'utilisateur
		existing, err := db.GetAnnonceByID(id)
		if err != nil || existing == nil {
			http.Error(w, "Annonce non trouvée", http.StatusNotFound)
			return
		}
		if existing.UserID != claims.UserID {
			http.Error(w, "Vous ne pouvez pas supprimer cette annonce", http.StatusForbidden)
			return
		}

		// Empêcher suppression d'une annonce validée
		if existing.Statut == "validee" {
			http.Error(w, "Une annonce validée ne peut pas être supprimée", http.StatusBadRequest)
			return
		}

		err = db.DeleteAnnonce(id, claims.UserID)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{
			"message": "annonce supprimée",
		})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func AdminPendingAnnoncesHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	_, ok := requireAdmin(w, r)
	if !ok {
		return
	}

	annonces, err := db.GetPendingAnnonces()
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(annonces)
}

func MyAnnoncesHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	annonces, err := db.GetAnnoncesByUserID(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(annonces)
}
