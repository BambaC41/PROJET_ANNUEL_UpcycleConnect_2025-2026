package db

import (
	"api/model"
	"database/sql"
	"errors"

	_ "github.com/go-sql-driver/mysql"
)

var DB *sql.DB

func InitDB() error {
	var err error
	DB, err = sql.Open("mysql", "root:root@tcp(localhost:3306)/db?parseTime=true")
	if err != nil {
		return err
	}

	return DB.Ping()
}

func GetUsers() ([]model.User, error) {
	rows, err := DB.Query(`
	SELECT id_user, email, password_hash,
	       COALESCE(prenom, ''),
	       COALESCE(nom, ''),
	       COALESCE(telephone, ''),
	       COALESCE(adresse_rue, ''),
	       COALESCE(adresse_ville, ''),
	       COALESCE(adresse_code_postal, ''),
	       COALESCE(adresse_pays, ''),
	       COALESCE(statut, ''),
	       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
	       id_role
	FROM utilisateur
`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var users []model.User

	for rows.Next() {
		var user model.User
		err := rows.Scan(
			&user.ID,
			&user.Email,
			&user.PasswordHash,
			&user.Prenom,
			&user.Nom,
			&user.Telephone,
			&user.AdresseRue,
			&user.AdresseVille,
			&user.AdresseCodePostal,
			&user.AdressePays,
			&user.Statut,
			&user.CreatedAt,
			&user.RoleID,
		)
		if err != nil {
			return nil, err
		}
		users = append(users, user)
	}

	return users, nil
}

func GetUserByID(id int) (*model.User, error) {
	var user model.User

	err := DB.QueryRow(`
	SELECT id_user, email, password_hash,
	       COALESCE(prenom, ''),
	       COALESCE(nom, ''),
	       COALESCE(telephone, ''),
	       COALESCE(adresse_rue, ''),
	       COALESCE(adresse_ville, ''),
	       COALESCE(adresse_code_postal, ''),
	       COALESCE(adresse_pays, ''),
	       COALESCE(statut, ''),
	       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
	       id_role
	FROM utilisateur
	WHERE id_user = ?
`, id).Scan(
		&user.ID,
		&user.Email,
		&user.PasswordHash,
		&user.Prenom,
		&user.Nom,
		&user.Telephone,
		&user.AdresseRue,
		&user.AdresseVille,
		&user.AdresseCodePostal,
		&user.AdressePays,
		&user.Statut,
		&user.CreatedAt,
		&user.RoleID,
	)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	return &user, nil
}

func GetUserByEmail(email string) (*model.User, error) {
	var user model.User

	err := DB.QueryRow(`
	SELECT id_user, email, password_hash,
	       COALESCE(prenom, ''),
	       COALESCE(nom, ''),
	       COALESCE(telephone, ''),
	       COALESCE(adresse_rue, ''),
	       COALESCE(adresse_ville, ''),
	       COALESCE(adresse_code_postal, ''),
	       COALESCE(adresse_pays, ''),
	       COALESCE(statut, ''),
	       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
	       id_role
	FROM utilisateur
	WHERE email = ?
`, email).Scan(
		&user.ID,
		&user.Email,
		&user.PasswordHash,
		&user.Prenom,
		&user.Nom,
		&user.Telephone,
		&user.AdresseRue,
		&user.AdresseVille,
		&user.AdresseCodePostal,
		&user.AdressePays,
		&user.Statut,
		&user.CreatedAt,
		&user.RoleID,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	return &user, nil
}

func CreateUser(req model.RegisterRequest, passwordHash string) error {
	_, err := DB.Exec(`
		INSERT INTO utilisateur (email, password_hash, prenom, nom, id_role, statut)
		VALUES (?, ?, ?, ?, ?, ?)
	`, req.Email, passwordHash, req.Prenom, req.Nom, 2, "actif")

	return err
}

func DeleteUser(id int) error {
	result, err := DB.Exec(`DELETE FROM utilisateur WHERE id_user = ?`, id)
	if err != nil {
		return err
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return err
	}

	if rowsAffected == 0 {
		return errors.New("user not found")
	}

	return nil
}
