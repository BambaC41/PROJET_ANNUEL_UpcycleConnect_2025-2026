package app

import (
	"encoding/json"
	"fmt"
	"math/rand"
	"net/http"
	"strconv"
	"strings"
	"time"

	"api/db"
	"api/model"
)

func ConteneursHandler(w http.ResponseWriter, r *http.Request) {

	switch r.Method {

	case http.MethodGet:

		conteneurs, err := db.GetConteneurs()

		if err != nil {

			http.Error(w, "database error", http.StatusInternalServerError)

			return

		}

		w.Header().Set("Content-Type", "application/json")

		json.NewEncoder(w).Encode(conteneurs)

	case http.MethodPost:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		var c model.Conteneur
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "invalid json", http.StatusBadRequest)
			return
		}
		if strings.TrimSpace(c.Code) == "" {
			http.Error(w, "code obligatoire", http.StatusBadRequest)
			return
		}
		id, err := db.CreateConteneur(c)
		if err != nil {
			http.Error(w, "insert error", http.StatusBadRequest)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(map[string]any{"id_conteneur": id, "message": "conteneur cree"})

	default:

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

	}

}

func ConteneurByIDHandler(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/conteneurs/")

	id, err := strconv.Atoi(idStr)

	if err != nil {

		http.Error(w, "invalid id", http.StatusBadRequest)

		return

	}

	switch r.Method {

	case http.MethodGet:

		conteneur, err := db.GetConteneurByID(id)

		if err != nil {

			http.Error(w, "database error", http.StatusInternalServerError)

			return

		}

		if conteneur == nil {

			http.Error(w, "conteneur not found", http.StatusNotFound)

			return

		}

		w.Header().Set("Content-Type", "application/json")

		json.NewEncoder(w).Encode(conteneur)

	case http.MethodPut:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		var c model.Conteneur
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "invalid json", http.StatusBadRequest)
			return
		}
		if strings.TrimSpace(c.Code) == "" {
			http.Error(w, "code obligatoire", http.StatusBadRequest)
			return
		}
		if err := db.UpdateConteneur(id, c); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "conteneur mis a jour"})

	case http.MethodDelete:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		if err := db.DeleteConteneur(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "conteneur supprime"})

	default:

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

	}

}

func DemandesDepotHandler(w http.ResponseWriter, r *http.Request) {

	switch r.Method {

	case http.MethodPost:

		claims, ok := requireAuth(w, r)

		if !ok {

			return

		}

		var req model.CreateDemandeDepotRequest

		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {

			http.Error(w, "invalid json", http.StatusBadRequest)

			return

		}

		if req.ConteneurID <= 0 {

			http.Error(w, "id_conteneur obligatoire", http.StatusBadRequest)

			return

		}

		if strings.TrimSpace(req.Titre) == "" {

			http.Error(w, "titre obligatoire", http.StatusBadRequest)

			return

		}

		objID, demID, err := db.CreateObjetAndDemandeDepot(claims.UserID, req)

		if err != nil {

			http.Error(w, "insert error", http.StatusInternalServerError)

			return

		}

		w.Header().Set("Content-Type", "application/json")

		w.WriteHeader(http.StatusCreated)

		json.NewEncoder(w).Encode(map[string]any{

			"message": "demande créée",

			"id_objet": objID,

			"id_demande": demID,
		})
	case http.MethodGet:
		_, ok := requireAdmin(w, r)
		if !ok {
			return
		}

		demandes, err := db.GetAllDemandesDepot()
		if err != nil {
			http.Error(w, "database error", http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(demandes)

	default:

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

	}

}

func MyDemandesDepotHandler(w http.ResponseWriter, r *http.Request) {

	if r.Method != http.MethodGet {

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

		return

	}

	claims, ok := requireAuth(w, r)

	if !ok {

		return

	}

	demandes, err := db.GetDemandesDepotByUserID(claims.UserID)

	if err != nil {

		http.Error(w, "database error", http.StatusInternalServerError)

		return

	}

	w.Header().Set("Content-Type", "application/json")

	json.NewEncoder(w).Encode(demandes)

}

func DemandeDepotByIDHandler(w http.ResponseWriter, r *http.Request) {

	path := strings.TrimPrefix(r.URL.Path, "/demandes-depot/")

	parts := strings.Split(strings.Trim(path, "/"), "/")

	if len(parts) == 0 || parts[0] == "" {

		http.Error(w, "invalid path", http.StatusBadRequest)

		return

	}

	id, err := strconv.Atoi(parts[0])

	if err != nil {

		http.Error(w, "invalid id", http.StatusBadRequest)

		return

	}

	if len(parts) == 2 && parts[1] == "codes" {

		handleGetDepotCodes(w, r, id)

		return

	}

	if len(parts) == 2 && parts[1] == "valider" {

		handleValidateDemandeDepot(w, r, id)

		return

	}

	switch r.Method {

	case http.MethodGet:

		claims, ok := requireAuth(w, r)

		if !ok {

			return

		}

		demande, err := db.GetDemandeDepotByID(id)

		if err != nil {

			http.Error(w, "database error", http.StatusInternalServerError)

			return

		}

		if demande == nil {

			http.Error(w, "demande not found", http.StatusNotFound)

			return

		}

		if claims.RoleID != RoleAdmin {
			userDemandes, err := db.GetDemandesDepotByUserID(claims.UserID)
			if err != nil {
				http.Error(w, "database error", http.StatusInternalServerError)
				return
			}

			allowed := false
			for _, d := range userDemandes {
				if d.IDDemande == id {
					allowed = true
					break
				}
			}

			if !allowed {
				http.Error(w, "forbidden", http.StatusForbidden)
				return
			}
		}

		w.Header().Set("Content-Type", "application/json")

		json.NewEncoder(w).Encode(demande)

	case http.MethodDelete:

		claims, ok := requireAuth(w, r)

		if !ok {

			return

		}

		err := db.DeleteDemandeDepot(id, claims.UserID)

		if err != nil {

			http.Error(w, err.Error(), http.StatusForbidden)

			return

		}

		w.Header().Set("Content-Type", "application/json")

		json.NewEncoder(w).Encode(map[string]string{

			"message": "demande supprimée",
		})

	default:

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

	}

}

func handleGetDepotCodes(w http.ResponseWriter, r *http.Request, id int) {

	if r.Method != http.MethodGet {

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

		return

	}

	_, ok := requireAuth(w, r)

	if !ok {

		return

	}

	codes, err := db.GetDepotCodes(id)

	if err != nil {

		http.Error(w, "database error", http.StatusInternalServerError)

		return

	}

	if codes == nil {

		http.Error(w, "codes not found", http.StatusNotFound)

		return

	}

	w.Header().Set("Content-Type", "application/json")

	json.NewEncoder(w).Encode(codes)

}

func handleValidateDemandeDepot(w http.ResponseWriter, r *http.Request, id int) {

	if r.Method != http.MethodPut {

		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)

		return

	}

	_, ok := requireAdmin(w, r)

	if !ok {

		return

	}

	codeAcces := generateAccessCode()

	barcodeValue := generateBarcode()

	err := db.ValidateDemandeDepot(id, codeAcces, barcodeValue)

	if err != nil {

		http.Error(w, "validation error", http.StatusInternalServerError)

		return

	}

	w.Header().Set("Content-Type", "application/json")

	json.NewEncoder(w).Encode(map[string]any{

		"message": "demande validée",

		"id_demande": id,

		"code_acces": codeAcces,

		"barcode_value": barcodeValue,

		"new_status": "validee",
	})

}

func generateAccessCode() string {

	r := rand.New(rand.NewSource(time.Now().UnixNano()))

	return fmt.Sprintf("%04d-%04d", r.Intn(10000), r.Intn(10000))

}

func generateBarcode() string {

	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	base12 := fmt.Sprintf("%012d", r.Int63n(1000000000000))
	checksum := ean13Checksum(base12)
	return fmt.Sprintf("%s%d", base12, checksum)

}

func ean13Checksum(base12 string) int {
	sum := 0
	for i, c := range base12 {
		digit := int(c - '0')
		if i%2 == 0 {
			sum += digit
		} else {
			sum += digit * 3
		}
	}
	return (10 - (sum % 10)) % 10
}
