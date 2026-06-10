package app

import (
	"api/db"
	"encoding/json"
	"errors"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"
)

func MyPaiementsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	paiements, err := db.GetMyPaiements(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(paiements)
}

func PaiementsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	_, ok := requireAdmin(w, r)
	if !ok {
		return
	}

	paiements, err := db.GetAllPaiements()
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(paiements)
}

func handleInscriptionPay(w http.ResponseWriter, r *http.Request, inscriptionID int) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	var payload struct {
		CardNumber string `json:"card_number"`
		CardHolder string `json:"card_holder"`
		Expiry     string `json:"expiry"`
		CVC        string `json:"cvc"`
	}
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&payload)
	}
	if err := validateCardPayload(payload.CardNumber, payload.Expiry, payload.CVC); err != nil {
		http.Error(w, "carte non valide: "+err.Error(), http.StatusBadRequest)
		return
	}

	id, err := db.CreatePaiementForInscription(inscriptionID, claims.UserID)
	if err != nil {
		switch err.Error() {
		case "inscription not found":
			http.Error(w, err.Error(), http.StatusNotFound)
		case "payment already exists":
			http.Error(w, err.Error(), http.StatusBadRequest)
		default:
			http.Error(w, "Database error", http.StatusInternalServerError)
		}
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]any{
		"message":     "payment created",
		"id_paiement": id,
	})
}

func validateCardPayload(number string, expiry string, cvc string) error {
	n := strings.ReplaceAll(strings.ReplaceAll(strings.TrimSpace(number), " ", ""), "-", "")
	if n == "" {
		return nil
	}
	if ok, _ := regexp.MatchString(`^\d{13,19}$`, n); !ok {
		return errors.New("numero invalide")
	}
	if !passesLuhn(n) {
		return errors.New("numero invalide")
	}
	if ok, _ := regexp.MatchString(`^(0[1-9]|1[0-2])\/\d{2}$`, strings.TrimSpace(expiry)); !ok {
		return errors.New("date d'expiration invalide")
	}
	parts := strings.Split(expiry, "/")
	month := parts[0]
	year := "20" + parts[1]
	expTs, err := time.Parse("2006-01", year+"-"+month)
	if err != nil {
		return err
	}
	if expTs.Before(time.Now().AddDate(0, -1, 0)) {
		return errors.New("carte expiree")
	}
	if ok, _ := regexp.MatchString(`^\d{3,4}$`, strings.TrimSpace(cvc)); !ok {
		return errors.New("cvc invalide")
	}
	return nil
}

func passesLuhn(number string) bool {
	sum := 0
	alt := false
	for i := len(number) - 1; i >= 0; i-- {
		d := int(number[i] - '0')
		if alt {
			d *= 2
			if d > 9 {
				d -= 9
			}
		}
		sum += d
		alt = !alt
	}
	return sum%10 == 0
}

func InscriptionPaymentHandler(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/inscriptions/")
	parts := strings.Split(strings.Trim(path, "/"), "/")

	if len(parts) != 2 || parts[1] != "pay" {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	id, err := strconv.Atoi(parts[0])
	if err != nil {
		http.Error(w, "Invalid inscription ID", http.StatusBadRequest)
		return
	}

	handleInscriptionPay(w, r, id)
}