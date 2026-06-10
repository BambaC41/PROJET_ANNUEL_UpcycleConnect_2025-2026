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
func AnnonceByIDHandler(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/annonces/")
	parts := strings.Split(strings.Trim(path, "/"), "/")
	if len(parts) == 0 || parts[0] == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	id, err := strconv.Atoi(parts[0])
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}

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

		a.Statut = "en_attente"
		a.ValidateurID = nil
		a.ValidatedAt = nil
		a.UserID = claims.UserID

		err := db.UpdateAnnonce(id, claims.UserID, a)
		if err != nil {
			http.Error(w, err.Error(), http.StatusForbidden)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{
			"message": "annonce modifiée",
		})

	case http.MethodDelete:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}

		err := db.DeleteAnnonce(id, claims.UserID)
		if err != nil {
			http.Error(w, err.Error(), http.StatusForbidden)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{
			"message": "annonce deleted",
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
