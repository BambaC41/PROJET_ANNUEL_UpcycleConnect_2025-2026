package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

func EventsHandler(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		events, err := db.GetEvents()
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(events)

	case http.MethodPost:
		claims, ok := requireAdminOrSalarie(w, r)
		if !ok {
			return
		}

		var e model.Event
		if err := json.NewDecoder(r.Body).Decode(&e); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if claims.RoleID == RoleSalarie {
			e.Statut = "en_attente"
			e.IDValidateur = 0
			e.IDCreateur = claims.UserID
		}

		id, err := db.CreateEvent(e)
		if err != nil {
			http.Error(w, err.Error(), http.StatusBadRequest)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(map[string]any{
			"message":    "event created",
			"id_session": id,
		})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}
func EventByIDHandler(w http.ResponseWriter, r *http.Request) {

	path := strings.TrimPrefix(r.URL.Path, "/events/")

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

	if len(parts) == 2 && parts[1] == "register" {

		handleEventRegister(w, r, id)

		return

	}

	if len(parts) > 1 {

		http.Error(w, "Invalid path", http.StatusBadRequest)

		return

	}

	switch r.Method {

	case http.MethodGet:

		event, err := db.GetEventByID(id)

		if err != nil {

			http.Error(w, "Database error", http.StatusInternalServerError)

			return

		}

		if event == nil {

			http.Error(w, "Event not found", http.StatusNotFound)

			return

		}

		w.Header().Set("Content-Type", "application/json")

		json.NewEncoder(w).Encode(event)

	case http.MethodPut:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}
		var e model.Event
		if err := json.NewDecoder(r.Body).Decode(&e); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if claims.RoleID == RoleSalarie {
			creatorID, err := db.GetEventCreatorID(id)
			if err != nil {
				http.Error(w, err.Error(), http.StatusNotFound)
				return
			}
			if creatorID != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			existing, err := db.GetEventByID(id)
			if err != nil || existing == nil {
				http.Error(w, "Event not found", http.StatusNotFound)
				return
			}
			if existing.Statut != "en_attente" {
				http.Error(w, "Only pending events can be edited", http.StatusForbidden)
				return
			}
			e.Statut = "en_attente"
			e.IDValidateur = 0
			e.IDCreateur = claims.UserID
		} else if _, ok := requireAdmin(w, r); !ok {
			return
		}
		if err := db.UpdateEvent(id, e); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "event updated"})

	case http.MethodDelete:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}
		if claims.RoleID == RoleSalarie {
			creatorID, err := db.GetEventCreatorID(id)
			if err != nil {
				http.Error(w, err.Error(), http.StatusNotFound)
				return
			}
			if creatorID != claims.UserID {
				http.Error(w, "Forbidden", http.StatusForbidden)
				return
			}
			existing, err := db.GetEventByID(id)
			if err != nil || existing == nil {
				http.Error(w, "Event not found", http.StatusNotFound)
				return
			}
			if existing.Statut != "en_attente" {
				http.Error(w, "Only pending events can be deleted", http.StatusForbidden)
				return
			}
		} else if _, ok := requireAdmin(w, r); !ok {
			return
		}
		if err := db.DeleteEvent(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "event deleted"})

	default:

		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)

	}

}
