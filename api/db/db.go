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
	DB, err = sql.Open("mysql", "root:root@tcp(localhost:3306)/upcycleconnect?parseTime=true")
	if err != nil {
		return err
	}
	return DB.Ping()
}
func GetUsers() ([]model.User, error) {
	rows, err := DB.Query(`
		SELECT id_user, email, password_hash,
		       COALESCE(pseudo, ''),
		       COALESCE(prenom, ''),
		       COALESCE(nom, ''),
		       COALESCE(telephone, ''),
		       COALESCE(adresse_rue, ''),
		       COALESCE(adresse_ville, ''),
		       COALESCE(adresse_code_postal, ''),
		       COALESCE(adresse_pays, ''),
		       COALESCE(photo_profil, ''),
		       COALESCE(bio, ''),
		       COALESCE(statut, ''),
		       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
		       id_role
		FROM utilisateur
		ORDER BY id_user DESC
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
			&user.Pseudo,
			&user.Prenom,
			&user.Nom,
			&user.Telephone,
			&user.AdresseRue,
			&user.AdresseVille,
			&user.AdresseCodePostal,
			&user.AdressePays,
			&user.PhotoProfil,
			&user.Bio,
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
		       COALESCE(pseudo, ''),
		       COALESCE(prenom, ''),
		       COALESCE(nom, ''),
		       COALESCE(telephone, ''),
		       COALESCE(adresse_rue, ''),
		       COALESCE(adresse_ville, ''),
		       COALESCE(adresse_code_postal, ''),
		       COALESCE(adresse_pays, ''),
		       COALESCE(photo_profil, ''),
		       COALESCE(bio, ''),
		       COALESCE(statut, ''),
		       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
		       id_role
		FROM utilisateur
		WHERE id_user = ?

	`, id).Scan(
		&user.ID,
		&user.Email,
		&user.PasswordHash,
		&user.Pseudo,
		&user.Prenom,
		&user.Nom,
		&user.Telephone,
		&user.AdresseRue,
		&user.AdresseVille,
		&user.AdresseCodePostal,
		&user.AdressePays,
		&user.PhotoProfil,
		&user.Bio,
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
 COALESCE(pseudo, ''),
 COALESCE(prenom, ''),
 COALESCE(nom, ''),
 COALESCE(telephone, ''),
 COALESCE(adresse_rue, ''),
 COALESCE(adresse_ville, ''),
 COALESCE(adresse_code_postal, ''),
 COALESCE(adresse_pays, ''),
 COALESCE(photo_profil, ''),
 COALESCE(bio, ''),
 COALESCE(statut, ''),
 COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
 id_role
 FROM utilisateur
 WHERE email = ?
 `, email).Scan(
		&user.ID, &user.Email, &user.PasswordHash, &user.Pseudo,
		&user.Prenom, &user.Nom,
		&user.Telephone, &user.AdresseRue, &user.AdresseVille,
		&user.AdresseCodePostal,
		&user.AdressePays, &user.PhotoProfil, &user.Bio, &user.Statut,
		&user.CreatedAt, &user.RoleID,
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
 INSERT INTO utilisateur (email, password_hash, pseudo, prenom, nom, 
photo_profil, bio, id_role, statut)
 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
 `, req.Email, passwordHash, req.Pseudo, req.Prenom, req.Nom,
		req.PhotoProfil, req.Bio, req.RoleID, "actif")
	return err
}
func UpdateUser(id int, req model.UpdateUserRequest) error {
	result, err := DB.Exec(`
 UPDATE utilisateur
 SET email = ?, pseudo = ?, prenom = ?, nom = ?, telephone = ?,
 adresse_rue = ?, adresse_ville = ?, adresse_code_postal = ?, 
adresse_pays = ?,
 photo_profil = ?, bio = ?, statut = ?, id_role = ?
 WHERE id_user = ?
 `, req.Email, req.Pseudo, req.Prenom, req.Nom, req.Telephone,
		req.AdresseRue, req.AdresseVille, req.AdresseCodePostal,
		req.AdressePays,
		req.PhotoProfil, req.Bio, req.Statut, req.RoleID, id)
	if err != nil {
		return err
	}
	affected, err := result.RowsAffected()

	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("user not found")
	}
	return nil
}
func UpdateOwnProfile(id int, req model.UpdateUserRequest) error {
	result, err := DB.Exec(`
 UPDATE utilisateur
 SET pseudo = ?, prenom = ?, nom = ?, telephone = ?,
 adresse_rue = ?, adresse_ville = ?, adresse_code_postal = ?, 
adresse_pays = ?,
 photo_profil = ?, bio = ?
 WHERE id_user = ?
 `, req.Pseudo, req.Prenom, req.Nom, req.Telephone,
		req.AdresseRue, req.AdresseVille, req.AdresseCodePostal,
		req.AdressePays,
		req.PhotoProfil, req.Bio, id)
	if err != nil {
		return err
	}
	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("user not found")
	}
	return nil
}
func DeleteUser(id int) error {
	result, err := DB.Exec(`DELETE FROM utilisateur WHERE id_user = ?`, id)
	if err != nil {
		return err
	}
	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("user not found")
	}
	return nil
}
func GetCategories() ([]model.Category, error) {
	rows, err := DB.Query(`SELECT id_categorie, nom, COALESCE(description, 
10
'') FROM categorie_prestation ORDER BY id_categorie DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var categories []model.Category
	for rows.Next() {
		var c model.Category
		if err := rows.Scan(&c.ID, &c.Nom, &c.Description); err != nil {
			return nil, err
		}
		categories = append(categories, c)
	}
	return categories, nil
}
func GetCategoryByID(id int) (*model.Category, error) {
	var c model.Category
	err := DB.QueryRow(`SELECT id_categorie, nom, COALESCE(description, '') 
FROM categorie_prestation WHERE id_categorie = ?`, id).Scan(&c.ID, &c.Nom,
		&c.Description)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &c, nil
}
func CreateCategory(c model.Category) error {
	_, err := DB.Exec(`INSERT INTO categorie_prestation (nom, description) 
VALUES (?, ?)`, c.Nom, c.Description)
	return err
}
func UpdateCategory(id int, c model.Category) error {
	result, err := DB.Exec(`UPDATE categorie_prestation SET nom = ?, 
description = ? WHERE id_categorie = ?`, c.Nom, c.Description, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("category not found")
	}
	return nil
}
func DeleteCategory(id int) error {
	result, err := DB.Exec(`DELETE FROM categorie_prestation WHERE 
11
id_categorie = ?`, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("category not found")
	}
	return nil
}
func GetPrestations() ([]model.Prestation, error) {
	rows, err := DB.Query(`
 SELECT id_prestation, titre, COALESCE(description, ''), 
COALESCE(type, ''),
 COALESCE(prix, 0), is_active,
 COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
 id_categorie
 FROM prestation
 ORDER BY id_prestation DESC
 `)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var prestations []model.Prestation
	for rows.Next() {
		var p model.Prestation
		if err := rows.Scan(&p.ID, &p.Titre, &p.Description, &p.Type,
			&p.Prix, &p.IsActive, &p.CreatedAt, &p.CategoryID); err != nil {
			return nil, err
		}
		prestations = append(prestations, p)
	}
	return prestations, nil
}
func GetPrestationByID(id int) (*model.Prestation, error) {
	var p model.Prestation
	err := DB.QueryRow(`
 SELECT id_prestation, titre, COALESCE(description, ''), 
COALESCE(type, ''),
 COALESCE(prix, 0), is_active,
 COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
 id_categorie
 FROM prestation
 WHERE id_prestation = ?
 `, id).Scan(&p.ID, &p.Titre, &p.Description, &p.Type, &p.Prix,
		&p.IsActive, &p.CreatedAt, &p.CategoryID)
	if err == sql.ErrNoRows {
		return nil, nil

	}
	if err != nil {
		return nil, err
	}
	return &p, nil
}
func CreatePrestation(p model.Prestation) error {
	_, err := DB.Exec(`
 INSERT INTO prestation (titre, description, type, prix, is_active, 
id_categorie)
 VALUES (?, ?, ?, ?, ?, ?)
 `, p.Titre, p.Description, p.Type, p.Prix, p.IsActive, p.CategoryID)
	return err
}
func UpdatePrestation(id int, p model.Prestation) error {
	result, err := DB.Exec(`
 UPDATE prestation
 SET titre = ?, description = ?, type = ?, prix = ?, is_active = ?, 
id_categorie = ?
 WHERE id_prestation = ?
 `, p.Titre, p.Description, p.Type, p.Prix, p.IsActive, p.CategoryID, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("prestation not found")
	}
	return nil
}
func DeletePrestation(id int) error {
	result, err := DB.Exec(`DELETE FROM prestation WHERE id_prestation = ?`,
		id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("prestation not found")
	}
	return nil
}
func GetEvents() ([]model.Event, error) {
	rows, err := DB.Query(`
 SELECT id_session,
 COALESCE(DATE_FORMAT(date_debut, '%Y-%m-%d %H:%i:%s'), ''),
 COALESCE(DATE_FORMAT(date_fin, '%Y-%m-%d %H:%i:%s'), ''),
13
 COALESCE(lieu, ''),
 COALESCE(capacite_max, 0),
 COALESCE(statut, ''),
 COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
 id_prestation,
 COALESCE(id_validateur, 0)
 FROM session
 ORDER BY id_session DESC
 `)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var events []model.Event
	for rows.Next() {
		var e model.Event
		if err := rows.Scan(&e.ID, &e.DateDebut, &e.DateFin, &e.Lieu,
			&e.CapaciteMax, &e.Statut, &e.CreatedAt, &e.PrestationID, &e.ValidateurID); err != nil {
			return nil, err
		}
		events = append(events, e)
	}
	return events, nil
}
func GetEventByID(id int) (*model.Event, error) {
	var e model.Event
	err := DB.QueryRow(`
 SELECT id_session,
 COALESCE(DATE_FORMAT(date_debut, '%Y-%m-%d %H:%i:%s'), ''),
 COALESCE(DATE_FORMAT(date_fin, '%Y-%m-%d %H:%i:%s'), ''),
 COALESCE(lieu, ''),
 COALESCE(capacite_max, 0),
 COALESCE(statut, ''),
 COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
 id_prestation,
 COALESCE(id_validateur, 0)
 FROM session
 WHERE id_session = ?
 `, id).Scan(&e.ID, &e.DateDebut, &e.DateFin, &e.Lieu, &e.CapaciteMax,
		&e.Statut, &e.CreatedAt, &e.PrestationID, &e.ValidateurID)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &e, nil
}
func CreateEvent(e model.Event) error {
	_, err := DB.Exec(`
 INSERT INTO session (date_debut, date_fin, lieu, capacite_max, 
statut, id_prestation, id_validateur)
 VALUES (?, ?, ?, ?, ?, ?, ?)
 `, e.DateDebut, e.DateFin, e.Lieu, e.CapaciteMax, e.Statut,
		e.PrestationID, e.ValidateurID)
	return err
}
func UpdateEvent(id int, e model.Event) error {
	result, err := DB.Exec(`
 UPDATE session
 SET date_debut = ?, date_fin = ?, lieu = ?, capacite_max = ?, statut 
= ?, id_prestation = ?, id_validateur = ?
 WHERE id_session = ?
 `, e.DateDebut, e.DateFin, e.Lieu, e.CapaciteMax, e.Statut,
		e.PrestationID, e.ValidateurID, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("event not found")
	}
	return nil
}
func DeleteEvent(id int) error {
	result, err := DB.Exec(`DELETE FROM session WHERE id_session = ?`, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("event not found")
	}
	return nil
}
