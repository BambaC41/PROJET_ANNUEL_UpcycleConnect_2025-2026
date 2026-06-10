package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"errors"
	"net/http"
	"strconv"
	"strings"
)

func conseilStatut(isActive bool) string {
	if isActive {
		return "publie"
	}
	return "brouillon"
}

func enrichConseil(c *model.Conseil) {
	if c == nil {
		return
	}
	c.Statut = conseilStatut(c.IsActive)
}

func validateConseilPayload(c model.Conseil) error {
	if strings.TrimSpace(c.Titre) == "" || strings.TrimSpace(c.Contenu) == "" {
		return errors.New("titre et contenu requis")
	}
	return nil
}

func applyEmployeeConseilCreate(claims *Claims, c *model.Conseil) {
	c.IsActive = false
	c.IDAuteur = claims.UserID
}

func applyStaffConseilCreate(claims *Claims, c *model.Conseil) {
	if c.IDAuteur == 0 {
		c.IDAuteur = claims.UserID
	}
}

func ConseilsHandler(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		conseils, err := db.GetConseils()
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		for i := range conseils {
			enrichConseil(&conseils[i])
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(conseils)

	case http.MethodPost:
		claims, ok := requireAdminOrSalarie(w, r)
		if !ok {
			return
		}
		var c model.Conseil
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if err := validateConseilPayload(c); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}
		if claims.RoleID == RoleSalarie {
			applyEmployeeConseilCreate(claims, &c)
		} else {
			applyStaffConseilCreate(claims, &c)
		}
		id, err := db.CreateConseil(c)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		enrichConseil(&c)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(map[string]any{
			"message":     "conseil created",
			"id_conseil":  id,
			"is_active":   c.IsActive,
			"statut":      c.Statut,
			"id_auteur":   c.IDAuteur,
		})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func ConseilByIDHandler(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/conseils/")
	id, err := strconv.Atoi(strings.Trim(idStr, "/"))
	if err != nil {
		http.Error(w, "Invalid conseil ID", http.StatusBadRequest)
		return
	}

	switch r.Method {
	case http.MethodGet:
		conseil, err := db.GetConseilByID(id)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		if conseil == nil {
			http.Error(w, "Conseil not found", http.StatusNotFound)
			return
		}
		enrichConseil(conseil)
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(conseil)

	case http.MethodPut:
		claims, ok := requireAdminOrSalarie(w, r)
		if !ok {
			return
		}
		var c model.Conseil
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if err := validateConseilPayload(c); err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}

		existing, err := db.GetConseilByIDAny(id)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		if existing == nil {
			http.Error(w, "Conseil not found", http.StatusNotFound)
			return
		}

		if claims.RoleID == RoleSalarie {
			if existing.IDAuteur != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			if existing.IsActive {
				http.Error(w, "Published conseils cannot be edited", http.StatusForbidden)
				return
			}
			c.IsActive = false
			c.IDAuteur = claims.UserID
		} else {
			c.IDAuteur = existing.IDAuteur
			if c.IDAuteur == 0 {
				c.IDAuteur = claims.UserID
			}
		}

		if err := db.UpdateConseil(id, c); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		enrichConseil(&c)
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"message":    "conseil updated",
			"statut":     c.Statut,
			"is_active":  c.IsActive,
		})

	case http.MethodDelete:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}
		if claims.RoleID == RoleSalarie {
			existing, err := db.GetConseilByIDAny(id)
			if err != nil {
				http.Error(w, "Database error", http.StatusInternalServerError)
				return
			}
			if existing == nil {
				http.Error(w, "Conseil not found", http.StatusNotFound)
				return
			}
			if existing.IDAuteur != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			if existing.IsActive {
				http.Error(w, "Published conseils cannot be deleted", http.StatusForbidden)
				return
			}
		} else if _, ok := requireAdmin(w, r); !ok {
			return
		}
		if err := db.DeleteConseil(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "conseil deleted"})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func AdminConseilsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}

	filter := model.ConseilFilter{
		Status:   strings.TrimSpace(r.URL.Query().Get("status")),
		Query:    strings.TrimSpace(r.URL.Query().Get("q")),
		DateFrom: strings.TrimSpace(r.URL.Query().Get("date_from")),
		DateTo:   strings.TrimSpace(r.URL.Query().Get("date_to")),
		Page:     parsePositiveInt(r.URL.Query().Get("page"), 1),
		PerPage:  parsePositiveInt(r.URL.Query().Get("per_page"), 25),
	}
	if aid := strings.TrimSpace(r.URL.Query().Get("author")); aid != "" {
		if id, err := strconv.Atoi(aid); err == nil {
			filter.AuthorID = id
		}
	}

	conseils, total, err := db.SearchConseilsAdmin(filter)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	for i := range conseils {
		enrichConseil(&conseils[i])
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{
		"items":    conseils,
		"total":    total,
		"page":     filter.Page,
		"per_page": filter.PerPage,
	})
}

func parsePositiveInt(raw string, defaultVal int) int {
	if raw == "" {
		return defaultVal
	}
	n, err := strconv.Atoi(raw)
	if err != nil || n < 1 {
		return defaultVal
	}
	return n
}
