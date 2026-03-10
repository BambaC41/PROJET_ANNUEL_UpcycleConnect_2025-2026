package model

type User struct {
	ID                int    `json:"id_user"`
	Email             string `json:"email"`
	PasswordHash      string `json:"-"`
	Pseudo            string `json:"pseudo"`
	Prenom            string `json:"prenom"`
	Nom               string `json:"nom"`
	Telephone         string `json:"telephone"`
	AdresseRue        string `json:"adresse_rue"`
	AdresseVille      string `json:"adresse_ville"`
	AdresseCodePostal string `json:"adresse_code_postal"`
	AdressePays       string `json:"adresse_pays"`
	PhotoProfil       string `json:"photo_profil"`
	Bio               string `json:"bio"`
	Statut            string `json:"statut"`
	CreatedAt         string `json:"created_at"`
	RoleID            int    `json:"id_role"`
}
type RegisterRequest struct {
	Email       string `json:"email"`
	Password    string `json:"password"`
	Pseudo      string `json:"pseudo"`
	Prenom      string `json:"prenom"`
	Nom         string `json:"nom"`
	PhotoProfil string `json:"photo_profil"`
	Bio         string `json:"bio"`
	RoleID      int    `json:"role_id"`
}
type LoginRequest struct {
	Email    string `json:"email"`
	Password string `json:"password"`
}
type UpdateUserRequest struct {
	Email             string `json:"email"`
	Pseudo            string `json:"pseudo"`
	Prenom            string `json:"prenom"`
	Nom               string `json:"nom"`
	Telephone         string `json:"telephone"`
	AdresseRue        string `json:"adresse_rue"`
	AdresseVille      string `json:"adresse_ville"`
	AdresseCodePostal string `json:"adresse_code_postal"`
	AdressePays       string `json:"adresse_pays"`
	PhotoProfil       string `json:"photo_profil"`
	Bio               string `json:"bio"`
	Statut            string `json:"statut"`
	RoleID            int    `json:"id_role"`
}
