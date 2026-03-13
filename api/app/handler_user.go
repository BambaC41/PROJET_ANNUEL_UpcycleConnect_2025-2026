package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/crypto/bcrypt"
)

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
	if req.Email == "" || req.Password == "" || req.Pseudo == "" ||
		req.RoleID == 0 {
		http.Error(w, "Missing required fields", http.StatusBadRequest)
		return
	}
	if req.RoleID != RoleUser && req.RoleID != RolePro {
		http.Error(w, "Public register only allows role 3 or 4",
			http.StatusForbidden)
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
	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password),
		bcrypt.DefaultCost)
	if err != nil {
		http.Error(w, "Password hash error", http.StatusInternalServerError)
		return
	}
	if err := db.CreateUser(req, string(hashedPassword)); err != nil {
		http.Error(w, "Insert error", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusCreated)
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
	if req.Email == "" || req.Password == "" || req.Pseudo == "" ||
		req.RoleID == 0 {
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
	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password),
		bcrypt.DefaultCost)
	if err != nil {
		http.Error(w, "Password hash error", http.StatusInternalServerError)
		return
	}
	if err := db.CreateUser(req, string(hashedPassword)); err != nil {
		http.Error(w, "Insert error", http.StatusInternalServerError)
		return
	}
	w.WriteHeader(http.StatusCreated)

	json.NewEncoder(w).Encode(map[string]string{"message": "admin user created"})
}
func normalizeBanUntil(input string) (string, error) {
	layouts := []string{
		time.RFC3339,
		"2006-01-02 15:04:05",
		"2006-01-02",
	}
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
	if err := bcrypt.CompareHashAndPassword([]byte(user.PasswordHash),
		[]byte(req.Password)); err != nil {
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
	json.NewEncoder(w).Encode(map[string]string{"token": tokenString})
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
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(user)
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
	if _, ok := requireAdminOrStaff(w, r); !ok {
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
		if _, ok := requireAdminOrStaff(w, r); !ok {
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
		if req.RoleID < 1 || req.RoleID > 4 {
			http.Error(w, "Invalid role_id", http.StatusBadRequest)
			return
		}
		if err := db.UpdateUser(id, req); err != nil {
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
	json.NewEncoder(w).Encode(map[string]string{
		"message": "user banned",
	})
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
	json.NewEncoder(w).Encode(map[string]string{
		"message": "user unbanned",
	})
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

	UserByIDHandler(w, r)
}
