package app

import (

	"api/db"

	"encoding/json"

	"net/http"

	"strconv"

	"strings"

)

func MyInscriptionsHandler(w http.ResponseWriter, r *http.Request) {

	if r.Method != http.MethodGet {

		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)

		return

	}

	claims, ok := requireAuth(w, r)

	if !ok {

		return

	}

	inscriptions, err := db.GetMyInscriptions(claims.UserID)

	if err != nil {

		http.Error(w, "Database error", http.StatusInternalServerError)

		return

	}

	w.Header().Set("Content-Type", "application/json")

	json.NewEncoder(w).Encode(inscriptions)

}

func InscriptionByIDHandler(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/inscriptions/")
	parts := strings.Split(strings.Trim(path, "/"), "/")

	if len(parts) == 0 || parts[0] == "" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	id, err := strconv.Atoi(parts[0])
	if err != nil {
		http.Error(w, "Invalid inscription ID", http.StatusBadRequest)
		return
	}

	if len(parts) == 2 && parts[1] == "pay" {
		handleInscriptionPay(w, r, id)
		return
	}

	if len(parts) > 1 {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	switch r.Method {
	case http.MethodDelete:
		claims, ok := requireAuth(w, r)
		if !ok {
			return
		}

		err := db.CancelInscription(id, claims.UserID)
		if err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{
			"message": "inscription cancelled",
		})

	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func handleEventRegister(w http.ResponseWriter, r *http.Request, sessionID int) {

	if r.Method != http.MethodPost {

		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)

		return

	}

	claims, ok := requireAuth(w, r)

	if !ok {

		return

	}

	if claims.RoleID != RoleUser && claims.RoleID != RolePro {

		http.Error(w, "Forbidden", http.StatusForbidden)

		return

	}

	inscriptionID, err := db.CreateInscription(claims.UserID, sessionID)

	if err != nil {

		switch err.Error() {

		case "event not found":

			http.Error(w, err.Error(), http.StatusNotFound)

		case "event not active", "already registered", "event full":

			http.Error(w, err.Error(), http.StatusBadRequest)

		default:

			http.Error(w, "Database error", http.StatusInternalServerError)

		}

		return

	}

	w.Header().Set("Content-Type", "application/json")

	w.WriteHeader(http.StatusCreated)

	json.NewEncoder(w).Encode(map[string]any{

		"message":        "registration created",

		"id_inscription": inscriptionID,

		"id_session":     sessionID,

	})

}