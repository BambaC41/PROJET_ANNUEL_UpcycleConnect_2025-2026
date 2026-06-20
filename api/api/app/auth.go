package app

import (
	"api/db"
	"net/http"
	"os"
	"strings"
	"unicode"

	"github.com/golang-jwt/jwt/v5"
)

var jwtKey = []byte(getJWTSecret())

func getJWTSecret() string {
	secret := strings.TrimSpace(os.Getenv("JWT_SECRET"))
	if secret != "" {
		return secret
	}
	return "upcycle_dev_only_change_me_2026"
}

const (
	RoleAdmin    = 1
	RoleUser     = 2
	RolePro      = 3
	RoleSalarie  = 4
	RoleEmployee = RoleSalarie
)

type Claims struct {
	UserID int    `json:"user_id"`
	RoleID int    `json:"role_id"`
	Email  string `json:"email"`
	jwt.RegisteredClaims
}

func getClaimsFromRequest(r *http.Request) (*Claims, error) {
	authHeader := r.Header.Get("Authorization")
	if authHeader == "" {
		return nil, http.ErrNoCookie
	}
	if !strings.HasPrefix(authHeader, "Bearer ") {
		return nil, jwt.ErrTokenMalformed
	}
	tokenString := strings.TrimPrefix(authHeader, "Bearer ")
	claims := &Claims{}
	token, err := jwt.ParseWithClaims(tokenString, claims, func(token *jwt.Token) (interface{}, error) {
		return jwtKey, nil
	})
	if err != nil || !token.Valid {
		return nil, err
	}
	return claims, nil
}
func requireAuth(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, err := getClaimsFromRequest(r)
	if err != nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return nil, false
	}
	return claims, true
}
func requireAdmin(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID != RoleAdmin {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}

func requireAdminOrSalarie(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID != RoleAdmin && claims.RoleID != RoleSalarie {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}

func requireForumMember(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID < RoleAdmin || claims.RoleID > RoleSalarie {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}

func requireForumModerator(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID != RoleAdmin && claims.RoleID != RoleSalarie {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}

func isForumModerator(claims *Claims) bool {
	return claims != nil && (claims.RoleID == RoleAdmin || claims.RoleID == RoleSalarie)
}

func requireSalarie(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID != RoleSalarie {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}

func isValidPassword(password string) bool {
	if len(password) < 12 {
		return false
	}
	var hasLower, hasUpper, hasDigit, hasSpecial bool
	for _, c := range password {
		switch {
		case unicode.IsLower(c):
			hasLower = true
		case unicode.IsUpper(c):
			hasUpper = true
		case unicode.IsDigit(c):
			hasDigit = true
		case unicode.IsPunct(c) || unicode.IsSymbol(c):
			hasSpecial = true
		}
	}
	return hasLower && hasUpper && hasDigit && hasSpecial
}

func requireApprovedPro(w http.ResponseWriter, r *http.Request) (*Claims, bool) {
	claims, ok := requireAuth(w, r)
	if !ok {
		return nil, false
	}
	if claims.RoleID != RolePro {
		http.Error(w, "Only approved PRO can access this route", http.StatusForbidden)
		return nil, false
	}
	user, err := db.GetUserByID(claims.UserID)
	if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return nil, false
	}
	if user == nil {
		http.Error(w, "User not found", http.StatusUnauthorized)
		return nil, false
	}
	if user.IsBanned {
		http.Error(w, "Forbidden", http.StatusForbidden)
		return nil, false
	}
	if !user.IsApproved {
		http.Error(w, "PRO account pending admin approval", http.StatusForbidden)
		return nil, false
	}
	return claims, true
}
