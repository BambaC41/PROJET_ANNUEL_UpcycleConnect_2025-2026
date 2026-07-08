package app

import (
	"api/db"
	"api/model"
	"database/sql"
	"encoding/json"
	"log"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/crypto/bcrypt"
)

func CheckUserPremium(userID int) bool {
	var count int
	err := db.DB.QueryRow(`
		SELECT COUNT(*) 
		FROM abonnement_pro 
		WHERE id_pro = ? 
		  AND statut = 'actif' 
		  AND formule IN ('premium_mensuel', 'premium_annuel', 'premium')
		  AND (date_fin IS NULL OR date_fin >= CURDATE())
	`, userID).Scan(&count)

	if err != nil {
		log.Printf("Error checking premium for user %d: %v", userID, err)
		return false
	}

	result := count > 0
	log.Printf("User %d premium status: %v (count=%d)", userID, result, count)
	return result
}

func GetUserSubscription(userID int) map[string]interface{} {
	var idAbonnement int
	var formule, statut, dateDebut, dateFin string
	var prix float64

	err := db.DB.QueryRow(`
		SELECT id_abonnement, formule, statut, DATE_FORMAT(date_debut, '%Y-%m-%d'), 
		       DATE_FORMAT(date_fin, '%Y-%m-%d'), prix
		FROM abonnement_pro 
		WHERE id_pro = ? AND statut = 'actif' AND (date_fin IS NULL OR date_fin >= CURDATE())
		ORDER BY id_abonnement DESC LIMIT 1
	`, userID).Scan(&idAbonnement, &formule, &statut, &dateDebut, &dateFin, &prix)

	if err == sql.ErrNoRows {
		log.Printf("User %d: no active subscription found", userID)
		return map[string]interface{}{
			"formule":    "gratuit",
			"statut":     "actif",
			"prix":       0,
			"is_premium": false,
		}
	}
	if err != nil {
		log.Printf("Error getting subscription for user %d: %v", userID, err)
		return map[string]interface{}{
			"formule":    "gratuit",
			"statut":     "inconnu",
			"prix":       0,
			"is_premium": false,
		}
	}

	log.Printf("User %d subscription found: formule=%s, statut=%s, dateFin=%s", userID, formule, statut, dateFin)

	return map[string]interface{}{
		"formule":    formule,
		"statut":     statut,
		"date_debut": dateDebut,
		"date_fin":   dateFin,
		"prix":       prix,
		"is_premium": true,
	}
}

func GetMaxAnnoncesByUserID(userID int, roleID int) int {

	if roleID == RoleUser {
		return 999
	}

	if !CheckUserPremium(userID) {
		return 5
	}

	return 999
}

func CanUserCreateAnnonce(userID int, roleID int) bool {
	maxAnnonces := GetMaxAnnoncesByUserID(userID, roleID)

	var currentCount int
	err := db.DB.QueryRow(`
		SELECT COUNT(*) 
		FROM annonce 
		WHERE id_user = ? 
		  AND statut IN ('en_attente', 'validee')
	`, userID).Scan(&currentCount)
	if err != nil {
		return false
	}

	return currentCount < maxAnnonces
}

func GetRemainingAnnoncesCount(userID int, roleID int) int {
	maxAnnonces := GetMaxAnnoncesByUserID(userID, roleID)

	var currentCount int
	err := db.DB.QueryRow(`
		SELECT COUNT(*) 
		FROM annonce 
		WHERE id_user = ? 
		  AND statut IN ('en_attente', 'validee')
	`, userID).Scan(&currentCount)
	if err != nil {
		return maxAnnonces
	}

	remaining := maxAnnonces - currentCount
	if remaining < 0 {
		return 0
	}
	return remaining
}

func UpdateUpcyclingScore(userID int) error {
	var score int

	var depots int
	db.DB.QueryRow("SELECT COUNT(*) FROM demande_depot WHERE id_user = ? AND statut = 'deposee'", userID).Scan(&depots)
	score += depots * 15

	var annonces int
	db.DB.QueryRow("SELECT COUNT(*) FROM annonce WHERE id_user = ? AND statut = 'validee'", userID).Scan(&annonces)
	score += annonces * 10

	var inscriptions int
	db.DB.QueryRow("SELECT COUNT(*) FROM inscription WHERE id_user = ? AND statut = 'confirmee'", userID).Scan(&inscriptions)
	score += inscriptions * 20

	var projets int
	db.DB.QueryRow("SELECT COUNT(*) FROM projet_upcycling WHERE id_pro = ?", userID).Scan(&projets)
	score += projets * 25

	_, err := db.DB.Exec("UPDATE utilisateur SET upcycling_score = ? WHERE id_user = ?", score, userID)
	return err
}

func GetUpcyclingScore(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	var score int
	err := db.DB.QueryRow("SELECT upcycling_score FROM utilisateur WHERE id_user = ?", claims.UserID).Scan(&score)
	if err != nil {
		score = 0
	}

	var details struct {
		AnnoncesValidees int     `json:"annonces_validees"`
		DepotsRealises   int     `json:"depots_realises"`
		Inscriptions     int     `json:"inscriptions"`
		PoidsTotalKg     float64 `json:"poids_total_kg"`
	}

	db.DB.QueryRow(`
		SELECT 
			COALESCE((SELECT COUNT(*) FROM annonce WHERE id_user = ? AND statut = 'validee'), 0),
			COALESCE((SELECT COUNT(*) FROM demande_depot WHERE id_user = ? AND statut = 'deposee'), 0),
			COALESCE((SELECT COUNT(*) FROM inscription WHERE id_user = ? AND statut = 'confirmee'), 0),
			COALESCE((
				SELECT SUM(o.poids) FROM demande_depot d 
				LEFT JOIN objet o ON d.id_objet = o.id_objet 
				WHERE d.id_user = ? AND d.statut = 'deposee'
			), 0)
	`, claims.UserID, claims.UserID, claims.UserID, claims.UserID).Scan(
		&details.AnnoncesValidees, &details.DepotsRealises, &details.Inscriptions, &details.PoidsTotalKg,
	)

	response := map[string]interface{}{
		"score_global":      score,
		"annonces_validees": details.AnnoncesValidees,
		"depots_realises":   details.DepotsRealises,
		"inscriptions":      details.Inscriptions,
		"poids_total_kg":    details.PoidsTotalKg,
		"total_score":       score,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func RegisterHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req model.RegisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if req.Email == "" || req.Password == "" || req.Pseudo == "" || req.RoleID == 0 {
		http.Error(w, "Missing required fields", http.StatusBadRequest)
		return
	}
	if req.RoleID != RoleUser && req.RoleID != RolePro {
		http.Error(w, "Public register only allows role 2 or 3", http.StatusForbidden)
		return
	}
	if !isValidPassword(req.Password) {
		http.Error(w, "Password must contain 12 chars, 1 lowercase, 1 uppercase, 1 digit, 1 special char", http.StatusBadRequest)
		return
	}
	existingUser, err := db.GetUserByEmail(req.Email)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	if existingUser != nil {
		http.Error(w, "Email already exists", http.StatusConflict)
		return
	}
	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	if err != nil {
		http.Error(w, "Password hash error", http.StatusInternalServerError)
		return
	}
	if err := db.CreateUser(req, string(hashedPassword)); err != nil {
		http.Error(w, "Insert error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	if req.RoleID == RolePro {
		json.NewEncoder(w).Encode(map[string]string{"message": "pro account created and pending admin approval"})
		return
	}
	json.NewEncoder(w).Encode(map[string]string{"message": "user created"})
}

func AdminCreateUserHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	var req model.RegisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if req.Email == "" || req.Password == "" || req.Pseudo == "" || req.RoleID == 0 {
		http.Error(w, "Missing required fields", http.StatusBadRequest)
		return
	}
	if req.RoleID < 1 || req.RoleID > 4 {
		http.Error(w, "Invalid role_id", http.StatusBadRequest)
		return
	}
	if !isValidPassword(req.Password) {
		http.Error(w, "Password must contain 12 chars, 1 lowercase, 1 uppercase, 1 digit, 1 special char", http.StatusBadRequest)
		return
	}
	existingUser, err := db.GetUserByEmail(req.Email)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	if existingUser != nil {
		http.Error(w, "Email already exists", http.StatusConflict)
		return
	}
	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	if err != nil {
		http.Error(w, "Password hash error", http.StatusInternalServerError)
		return
	}
	if err := db.CreateUser(req, string(hashedPassword)); err != nil {
		http.Error(w, "Insert error", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "admin user created"})
}

func normalizeBanUntil(input string) (string, error) {
	layouts := []string{time.RFC3339, "2006-01-02 15:04:05", "2006-01-02"}
	for _, layout := range layouts {
		if t, err := time.Parse(layout, input); err == nil {
			if layout == "2006-01-02" {
				t = t.Add(23*time.Hour + 59*time.Minute + 59*time.Second)
			}
			return t.Format("2006-01-02 15:04:05"), nil
		}
	}
	return "", http.ErrNotSupported
}

func isBanExpired(banUntil string) bool {
	if banUntil == "" {
		return false
	}
	layouts := []string{time.RFC3339, "2006-01-02 15:04:05", "2006-01-02"}
	for _, layout := range layouts {
		if t, err := time.Parse(layout, banUntil); err == nil {
			return time.Now().After(t)
		}
	}
	return false
}

func LoginHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req model.LoginRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	user, err := db.GetUserByEmail(req.Email)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	if user == nil {
		http.Error(w, "Invalid credentials", http.StatusUnauthorized)
		return
	}
	if err := bcrypt.CompareHashAndPassword([]byte(user.PasswordHash), []byte(req.Password)); err != nil {
		http.Error(w, "Invalid credentials", http.StatusUnauthorized)
		return
	}
	if user.IsBanned {
		if isBanExpired(user.BanUntil) {
			if err := db.UnbanUser(user.ID); err != nil {
				http.Error(w, "Database error", http.StatusInternalServerError)
				return
			}
			user.IsBanned = false
			user.BanReason = ""
			user.BanUntil = ""
		} else {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusForbidden)
			json.NewEncoder(w).Encode(map[string]string{
				"error":      "account is banned",
				"ban_reason": user.BanReason,
				"ban_until":  user.BanUntil,
			})
			return
		}
	}


	expirationTime := time.Now().Add(24 * time.Hour)
	claims := &Claims{
		UserID: user.ID,
		RoleID: user.RoleID,
		Email:  user.Email,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(expirationTime),
		},
	}
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, err := token.SignedString(jwtKey)
	if err != nil {
		http.Error(w, "Token error", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]any{
		"token":       tokenString,
		"role_id":     user.RoleID,
		"user_id":     user.ID,
		"is_approved": user.IsApproved,
	})
}

func MeHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}
	user, err := db.GetUserByID(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	if user == nil {
		http.Error(w, "User not found", http.StatusNotFound)
		return
	}

	response := map[string]interface{}{
		"id_user":             user.ID,
		"email":               user.Email,
		"pseudo":              user.Pseudo,
		"prenom":              user.Prenom,
		"nom":                 user.Nom,
		"telephone":           user.Telephone,
		"adresse_rue":         user.AdresseRue,
		"adresse_ville":       user.AdresseVille,
		"adresse_code_postal": user.AdresseCodePostal,
		"adresse_pays":        user.AdressePays,
		"photo_profil":        user.PhotoProfil,
		"bio":                 user.Bio,
		"statut":              user.Statut,
		"created_at":          user.CreatedAt,
		"id_role":             user.RoleID,
		"is_banned":           user.IsBanned,
		"is_approved":         user.IsApproved,
		"tutorial_completed":  user.TutorialCompleted,
		"is_premium":          CheckUserPremium(claims.UserID),
		"remaining_annonces":  GetRemainingAnnoncesCount(claims.UserID, claims.RoleID),
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func UpdateMeHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}
	var req model.UpdateUserRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if err := db.UpdateOwnProfile(claims.UserID, req); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "profile updated"})
}

func PublicProfileHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	idStr := strings.TrimPrefix(r.URL.Path, "/profile/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	user, err := db.GetUserByID(id)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	if user == nil {
		http.Error(w, "User not found", http.StatusNotFound)
		return
	}
	if user.RoleID == RolePro && !user.IsApproved {
		http.Error(w, "User not found", http.StatusNotFound)
		return
	}
	response := map[string]string{
		"pseudo":       user.Pseudo,
		"bio":          user.Bio,
		"photo_profil": user.PhotoProfil,
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func UsersHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	users, err := db.GetUsers()
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(users)
}

func UserByIDHandler(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	switch r.Method {
	case http.MethodGet:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		user, err := db.GetUserByID(id)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		if user == nil {
			http.Error(w, "User not found", http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(user)
	case http.MethodPut:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		var req model.UpdateUserRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if req.RoleID != nil && (*req.RoleID < 1 || *req.RoleID > 4) {
			http.Error(w, "Invalid role_id", http.StatusBadRequest)
			return
		}
		var passwordHash string
		if req.Password != "" {
			if !isValidPassword(req.Password) {
				http.Error(w, "Password must contain 12 chars, 1 lowercase, 1 uppercase, 1 digit, 1 special char", http.StatusBadRequest)
				return
			}
			hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
			if err != nil {
				http.Error(w, "Password hash error", http.StatusInternalServerError)
				return
			}
			passwordHash = string(hashedPassword)
		}
		if err := db.UpdateUser(id, req, passwordHash); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "user updated"})
	case http.MethodDelete:
		if _, ok := requireAdmin(w, r); !ok {
			return
		}
		if err := db.DeleteUser(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "user deleted"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}

func HealthHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"status":  "ok",
		"message": "API is running",
	})
}

func PublicProfilesHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	profiles, err := db.GetPublicProfiles()
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(profiles)
}

func BanUserHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	idStr := strings.TrimSuffix(strings.TrimPrefix(r.URL.Path, "/users/"), "/ban")
	idStr = strings.TrimSuffix(idStr, "/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	var req model.BanUserRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if req.BanReason == "" || req.BanUntil == "" {
		http.Error(w, "ban_reason and ban_until are required", http.StatusBadRequest)
		return
	}
	normalizedBanUntil, err := normalizeBanUntil(req.BanUntil)
	if err != nil {
		http.Error(w, "ban_until must be RFC3339, YYYY-MM-DD HH:MM:SS or YYYY-MM-DD", http.StatusBadRequest)
		return
	}
	if err := db.BanUser(id, req.BanReason, normalizedBanUntil); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "user banned"})
}

func UnbanUserHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	idStr := strings.TrimSuffix(strings.TrimPrefix(r.URL.Path, "/users/"), "/unban")
	idStr = strings.TrimSuffix(idStr, "/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	if err := db.UnbanUser(id); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "user unbanned"})
}

func PendingProsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	users, err := db.GetPendingPros()
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(users)
}

func ApproveProHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}
	if _, ok := requireAdmin(w, r); !ok {
		return
	}
	idStr := strings.TrimSuffix(strings.TrimPrefix(r.URL.Path, "/users/"), "/approve")
	idStr = strings.TrimSuffix(idStr, "/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	if err := db.ApprovePro(id); err != nil {
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "pro approved"})
}

func UsersRouter(w http.ResponseWriter, r *http.Request) {
	path := r.URL.Path
	if strings.HasSuffix(path, "/ban") {
		BanUserHandler(w, r)
		return
	}
	if strings.HasSuffix(path, "/unban") {
		UnbanUserHandler(w, r)
		return
	}
	if strings.HasSuffix(path, "/approve") {
		ApproveProHandler(w, r)
		return
	}
	UserByIDHandler(w, r)
}

func GetMySubscriptionHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	if claims.RoleID != RolePro {
		http.Error(w, "Accès réservé aux professionnels", http.StatusForbidden)
		return
	}

	subscription := GetUserSubscription(claims.UserID)
	writeJSON(w, http.StatusOK, subscription)
}

func CancelSubscriptionHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	if claims.RoleID != RolePro {
		http.Error(w, "Accès réservé aux professionnels", http.StatusForbidden)
		return
	}

	_, err := db.DB.Exec(`
		UPDATE abonnement_pro 
		SET statut = 'resilie' 
		WHERE id_pro = ? AND statut = 'actif'
	`, claims.UserID)

	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	writeJSON(w, http.StatusOK, map[string]string{"message": "Abonnement résilié avec succès"})
}

func GetMyProjectsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	rows, err := db.DB.Query(`
		SELECT id_projet, titre, description, statut, progression, is_public, image_url, 
		       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'),
		       DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s')
		FROM projet_upcycling
		WHERE id_pro = ?
		ORDER BY id_projet DESC
	`, claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}
	defer rows.Close()

	var projets []map[string]interface{}
	for rows.Next() {
		var p struct {
			ID          int     `json:"id_projet"`
			Titre       string  `json:"titre"`
			Description string  `json:"description"`
			Statut      string  `json:"statut"`
			Progression int     `json:"progression"`
			IsPublic    bool    `json:"is_public"`
			ImageURL    *string `json:"image_url"`
			CreatedAt   string  `json:"created_at"`
			UpdatedAt   string  `json:"updated_at"`
		}
		err := rows.Scan(&p.ID, &p.Titre, &p.Description, &p.Statut, &p.Progression,
			&p.IsPublic, &p.ImageURL, &p.CreatedAt, &p.UpdatedAt)
		if err != nil {
			continue
		}
		projets = append(projets, map[string]interface{}{
			"id_projet": p.ID, "titre": p.Titre, "description": p.Description,
			"statut": p.Statut, "progression": p.Progression, "is_public": p.IsPublic,
			"image_url": p.ImageURL, "created_at": p.CreatedAt, "updated_at": p.UpdatedAt,
		})
	}

	writeJSON(w, http.StatusOK, projets)
}
