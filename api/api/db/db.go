package db

import (
	"api/model"
	"database/sql"
	"errors"
	"fmt"
	"os"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

var DB *sql.DB

func InitDB() error {
	var err error
	dsn := os.Getenv("DB_DSN")
	if strings.TrimSpace(dsn) == "" {
		dbUser := getenv("DB_USER", "root")
		dbPassword := getenv("DB_PASSWORD", "root")
		dbHost := getenv("DB_HOST", "localhost")
		dbPort := getenv("DB_PORT", "3306")
		dbName := getenv("DB_NAME", "upcycleconnect")
		dsn = fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true", dbUser, dbPassword, dbHost, dbPort, dbName)
	}

	DB, err = sql.Open("mysql", dsn)
	if err != nil {
		return err
	}
	return DB.Ping()
}

func getenv(key, defaultValue string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return defaultValue
	}
	return value
}

func scanUser(scanner interface{ Scan(dest ...any) error }, user *model.User) error {
	return scanner.Scan(
		&user.ID, &user.Email, &user.PasswordHash,
		&user.Pseudo, &user.Prenom, &user.Nom,
		&user.Telephone, &user.AdresseRue, &user.AdresseVille,
		&user.AdresseCodePostal, &user.AdressePays,
		&user.PhotoProfil, &user.Bio, &user.Statut,
		&user.CreatedAt, &user.RoleID,
		&user.IsBanned, &user.BanReason, &user.BanUntil, &user.IsApproved, &user.TutorialCompleted,
	)
}

const userSelect = `
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
	       id_role,
	       COALESCE(is_banned, 0),
	       COALESCE(ban_reason, ''),
	       COALESCE(DATE_FORMAT(ban_until, '%Y-%m-%d %H:%i:%s'), ''),
	       COALESCE(is_approved, 1),
	       COALESCE(tutorial_completed, 0)
	FROM utilisateur`

func GetUsers() ([]model.User, error) {
	rows, err := DB.Query(userSelect + ` ORDER BY id_user DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var users []model.User
	for rows.Next() {
		var user model.User
		if err := scanUser(rows, &user); err != nil {
			return nil, err
		}
		users = append(users, user)
	}
	return users, nil
}

func GetUserByID(id int) (*model.User, error) {
	var user model.User
	err := scanUser(DB.QueryRow(userSelect+` WHERE id_user = ?`, id), &user)
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
	err := scanUser(DB.QueryRow(userSelect+` WHERE LOWER(email) = LOWER(?)`, email), &user)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &user, nil
}

func CreateUser(req model.RegisterRequest, passwordHash string) error {
	isApproved := true
	if req.RoleID == 3 {
		isApproved = false
	}
	_, err := DB.Exec(`
		INSERT INTO utilisateur (
			email, password_hash, pseudo, prenom, nom, telephone,
			adresse_rue, adresse_ville, adresse_code_postal, adresse_pays,
			photo_profil, bio, id_role, statut, is_approved
		)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	`, req.Email, passwordHash, req.Pseudo, req.Prenom, req.Nom,
		req.Telephone, req.AdresseRue, req.AdresseVille, req.AdresseCodePostal, req.AdressePays,
		req.PhotoProfil, req.Bio, req.RoleID, "actif", isApproved)
	return err
}

func UpdateOwnProfile(id int, req model.UpdateUserRequest) error {
	result, err := DB.Exec(`
		UPDATE utilisateur
		SET pseudo = ?, prenom = ?, nom = ?, telephone = ?,
		    adresse_rue = ?, adresse_ville = ?, adresse_code_postal = ?, adresse_pays = ?,
		    photo_profil = ?, bio = ?
		WHERE id_user = ?
	`, req.Pseudo, req.Prenom, req.Nom, req.Telephone,
		req.AdresseRue, req.AdresseVille, req.AdresseCodePostal,
		req.AdressePays, req.PhotoProfil, req.Bio, id)
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
	rows, err := DB.Query(`SELECT id_categorie, nom, COALESCE(description, '') FROM categorie_prestation ORDER BY id_categorie DESC`)
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
	err := DB.QueryRow(`SELECT id_categorie, nom, COALESCE(description, '') FROM categorie_prestation WHERE id_categorie = ?`, id).Scan(&c.ID, &c.Nom, &c.Description)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &c, nil
}

func CreateCategory(c model.Category) error {
	_, err := DB.Exec(`INSERT INTO categorie_prestation (nom, description) VALUES (?, ?)`, c.Nom, c.Description)
	return err
}

func UpdateCategory(id int, c model.Category) error {
	result, err := DB.Exec(`UPDATE categorie_prestation SET nom = ?, description = ? WHERE id_categorie = ?`, c.Nom, c.Description, id)
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
	result, err := DB.Exec(`DELETE FROM categorie_prestation WHERE id_categorie = ?`, id)
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
		SELECT id_prestation, titre, COALESCE(description, ''), COALESCE(type, ''),
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
			&p.Prix, &p.IsActive, &p.CreatedAt, &p.IDCategorie); err != nil {
			return nil, err
		}
		prestations = append(prestations, p)
	}
	return prestations, nil
}

func GetPrestationByID(id int) (*model.Prestation, error) {
	var p model.Prestation
	err := DB.QueryRow(`
		SELECT id_prestation, titre, COALESCE(description, ''), COALESCE(type, ''),
		       COALESCE(prix, 0), is_active,
		       COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), ''),
		       id_categorie
		FROM prestation
		WHERE id_prestation = ?
	`, id).Scan(&p.ID, &p.Titre, &p.Description, &p.Type, &p.Prix,
		&p.IsActive, &p.CreatedAt, &p.IDCategorie)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &p, nil
}

func CreatePrestation(p model.Prestation) (int64, error) {
	if strings.TrimSpace(p.Titre) == "" {
		return 0, errors.New("missing titre")
	}
	if p.IDCategorie <= 0 {
		return 0, errors.New("invalid categorie")
	}

	result, err := DB.Exec(`
		INSERT INTO prestation (
			titre,
			description,
			type,
			prix,
			is_active,
			id_categorie,
			id_user
		) VALUES (?, ?, ?, ?, ?, ?, ?)
	`,
		p.Titre,
		p.Description,
		p.Type,
		p.Prix,
		p.IsActive,
		p.IDCategorie,
		p.IDUser,
	)
	if err != nil {
		return 0, err
	}

	return result.LastInsertId()
}

func UpdatePrestation(id int, p model.Prestation) error {
	result, err := DB.Exec(`
		UPDATE prestation
		SET titre = ?, description = ?, type = ?, prix = ?, is_active = ?, id_categorie = ?
		WHERE id_prestation = ?
	`, p.Titre, p.Description, p.Type, p.Prix, p.IsActive, p.IDCategorie, id)
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
	result, err := DB.Exec(`DELETE FROM prestation WHERE id_prestation = ?`, id)
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
		SELECT
			s.id_session,
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(s.date_fin, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.lieu, ''),
			COALESCE(s.capacite_max, 0),
			COALESCE(s.statut, ''),
			COALESCE(DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.id_prestation, 0),
			COALESCE(p.titre, ''),
			COALESCE(p.prix, 0),
			COALESCE(s.id_validateur, 0),
			COALESCE(s.id_createur, 0),
			COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END) AS inscrits_count,
			(COALESCE(s.capacite_max, 0) - COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END)) AS places_restantes
		FROM session s
		LEFT JOIN prestation p ON p.id_prestation = s.id_prestation
		LEFT JOIN inscription i ON i.id_session = s.id_session
		GROUP BY
			s.id_session,
			s.date_debut,
			s.date_fin,
			s.lieu,
			s.capacite_max,
			s.statut,
			s.created_at,
			s.id_prestation,
			p.titre,
			p.prix,
			s.id_validateur,
			s.id_createur
		ORDER BY s.date_debut DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var events []model.Event
	for rows.Next() {
		var e model.Event
		if err := rows.Scan(
			&e.IDSession,
			&e.DateDebut,
			&e.DateFin,
			&e.Lieu,
			&e.CapaciteMax,
			&e.Statut,
			&e.CreatedAt,
			&e.IDPrestation,
			&e.PrestationTitre,
			&e.PrestationPrix,
			&e.IDValidateur,
			&e.IDCreateur,
			&e.InscritsCount,
			&e.PlacesRestantes,
		); err != nil {
			return nil, err
		}
		if e.PlacesRestantes < 0 {
			e.PlacesRestantes = 0
		}
		events = append(events, e)
	}
	return events, nil
}

func GetEventsByCreator(creatorID int) ([]model.Event, error) {
	rows, err := DB.Query(`
		SELECT
			s.id_session,
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(s.date_fin, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.lieu, ''),
			COALESCE(s.capacite_max, 0),
			COALESCE(s.statut, ''),
			COALESCE(DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.id_prestation, 0),
			COALESCE(p.titre, ''),
			COALESCE(p.prix, 0),
			COALESCE(s.id_validateur, 0),
			COALESCE(s.id_createur, 0),
			COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END) AS inscrits_count,
			(COALESCE(s.capacite_max, 0) - COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END)) AS places_restantes
		FROM session s
		LEFT JOIN prestation p ON p.id_prestation = s.id_prestation
		LEFT JOIN inscription i ON i.id_session = s.id_session
		WHERE s.id_createur = ?
		GROUP BY
			s.id_session,
			s.date_debut,
			s.date_fin,
			s.lieu,
			s.capacite_max,
			s.statut,
			s.created_at,
			s.id_prestation,
			p.titre,
			p.prix,
			s.id_validateur,
			s.id_createur
		ORDER BY s.date_debut DESC
	`, creatorID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var events []model.Event
	for rows.Next() {
		var e model.Event
		if err := rows.Scan(
			&e.IDSession,
			&e.DateDebut,
			&e.DateFin,
			&e.Lieu,
			&e.CapaciteMax,
			&e.Statut,
			&e.CreatedAt,
			&e.IDPrestation,
			&e.PrestationTitre,
			&e.PrestationPrix,
			&e.IDValidateur,
			&e.IDCreateur,
			&e.InscritsCount,
			&e.PlacesRestantes,
		); err != nil {
			return nil, err
		}
		if e.PlacesRestantes < 0 {
			e.PlacesRestantes = 0
		}
		events = append(events, e)
	}
	return events, nil
}

func GetEventCreatorID(id int) (int, error) {
	var creatorID int
	err := DB.QueryRow(`SELECT COALESCE(id_createur, 0) FROM session WHERE id_session = ?`, id).Scan(&creatorID)
	if err == sql.ErrNoRows {
		return 0, errors.New("event not found")
	}
	return creatorID, err
}

func GetEventByID(id int) (*model.Event, error) {
	var e model.Event

	err := DB.QueryRow(`
		SELECT
			s.id_session,
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(s.date_fin, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.lieu, ''),
			COALESCE(s.capacite_max, 0),
			COALESCE(s.statut, ''),
			COALESCE(DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.id_prestation, 0),
			COALESCE(p.titre, ''),
			COALESCE(p.prix, 0),
			COALESCE(s.id_validateur, 0),
			COALESCE(s.id_createur, 0),
			COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END) AS inscrits_count,
			(COALESCE(s.capacite_max, 0) - COUNT(CASE WHEN i.statut <> 'annulee' THEN 1 END)) AS places_restantes
		FROM session s
		LEFT JOIN prestation p ON p.id_prestation = s.id_prestation
		LEFT JOIN inscription i ON i.id_session = s.id_session
		WHERE s.id_session = ?
		GROUP BY
			s.id_session,
			s.date_debut,
			s.date_fin,
			s.lieu,
			s.capacite_max,
			s.statut,
			s.created_at,
			s.id_prestation,
			p.titre,
			p.prix,
			s.id_validateur,
			s.id_createur
	`, id).Scan(
		&e.IDSession,
		&e.DateDebut,
		&e.DateFin,
		&e.Lieu,
		&e.CapaciteMax,
		&e.Statut,
		&e.CreatedAt,
		&e.IDPrestation,
		&e.PrestationTitre,
		&e.PrestationPrix,
		&e.IDValidateur,
		&e.IDCreateur,
		&e.InscritsCount,
		&e.PlacesRestantes,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	if e.PlacesRestantes < 0 {
		e.PlacesRestantes = 0
	}

	return &e, nil
}

func CreateEvent(e model.Event) (int64, error) {
	if e.IDPrestation <= 0 {
		return 0, errors.New("invalid prestation")
	}
	if strings.TrimSpace(e.DateDebut) == "" || strings.TrimSpace(e.DateFin) == "" {
		return 0, errors.New("missing dates")
	}
	if strings.TrimSpace(e.Lieu) == "" {
		return 0, errors.New("missing lieu")
	}

	var idValidateur any
	if e.IDValidateur > 0 {
		idValidateur = e.IDValidateur
	} else {
		idValidateur = nil
	}
	var idCreateur any
	if e.IDCreateur > 0 {
		idCreateur = e.IDCreateur
	} else {
		idCreateur = nil
	}

	result, err := DB.Exec(`
		INSERT INTO session (
			date_debut,
			date_fin,
			lieu,
			capacite_max,
			statut,
			id_prestation,
			id_validateur,
			id_createur
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
	`,
		e.DateDebut,
		e.DateFin,
		e.Lieu,
		e.CapaciteMax,
		e.Statut,
		e.IDPrestation,
		idValidateur,
		idCreateur,
	)
	if err != nil {
		return 0, err
	}

	return result.LastInsertId()
}

func UpdateEvent(id int, e model.Event) error {
	result, err := DB.Exec(`
		UPDATE session
		SET date_debut = ?, date_fin = ?, lieu = ?, capacite_max = ?, statut = ?, id_prestation = ?, id_validateur = ?
		WHERE id_session = ?
	`, e.DateDebut, e.DateFin, e.Lieu, e.CapaciteMax, e.Statut,
		e.IDPrestation, e.IDValidateur, id)
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

func GetPublicProfiles() ([]model.PublicProfile, error) {
	rows, err := DB.Query(`
		SELECT id_user,
		       COALESCE(pseudo, ''),
		       COALESCE(bio, ''),
		       COALESCE(photo_profil, '')
		FROM utilisateur
		WHERE (id_role <> 4 OR COALESCE(is_approved, 1) = 1)
		ORDER BY id_user DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var profiles []model.PublicProfile
	for rows.Next() {
		var p model.PublicProfile
		if err := rows.Scan(&p.ID, &p.Pseudo, &p.Bio, &p.PhotoProfil); err != nil {
			return nil, err
		}
		profiles = append(profiles, p)
	}
	return profiles, nil
}

func BanUser(id int, reason string, until string) error {
	result, err := DB.Exec(`
		UPDATE utilisateur
		SET is_banned = TRUE,
		    ban_reason = ?,
		    ban_until = ?
		WHERE id_user = ?
	`, reason, until, id)
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

func UnbanUser(id int) error {
	result, err := DB.Exec(`
		UPDATE utilisateur
		SET is_banned = FALSE,
		    ban_reason = NULL,
		    ban_until = NULL
		WHERE id_user = ?
	`, id)
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

func GetPendingPros() ([]model.User, error) {
	rows, err := DB.Query(userSelect + ` WHERE id_role = 3 AND is_approved = 0 ORDER BY id_user DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var users []model.User
	for rows.Next() {
		var user model.User
		if err := scanUser(rows, &user); err != nil {
			return nil, err
		}
		users = append(users, user)
	}
	return users, nil
}

func ApprovePro(id int) error {
	result, err := DB.Exec(`
        UPDATE utilisateur
        SET is_approved = TRUE
        WHERE id_user = ? AND id_role = 3
    `, id)
	if err != nil {
		return err
	}
	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("pro user not found")
	}
	return nil
}
func GetAnnoncesByUserID(userID int) ([]model.Annonce, error) {
	rows, err := DB.Query(`
		SELECT
			a.id_annonce,
			COALESCE(a.mode, ''),
			a.prix,
			COALESCE(a.statut, ''),
			DATE_FORMAT(a.validated_at, '%Y-%m-%d %H:%i:%s'),
			DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s'),
			a.id_user,
			a.id_objet,
			a.id_validateur,
			o.titre,
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			o.poids,
			o.volume,
			COALESCE(o.photo_url, ''),
			COALESCE(a.commission_payee, 0) AS commission_payee,
			a.commission_payee_at
		FROM annonce a
		INNER JOIN objet o ON o.id_objet = a.id_objet
		WHERE a.id_user = ?
		ORDER BY a.id_annonce DESC
	`, userID)

	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var annonces []model.Annonce
	for rows.Next() {
		var a model.Annonce
		var validatedAt sql.NullString
		var validateurID sql.NullInt64
		var prix sql.NullFloat64
		var poids sql.NullFloat64
		var volume sql.NullFloat64
		var commissionPayee sql.NullBool
		var commissionPayeeAt sql.NullTime

		err := rows.Scan(
			&a.ID,
			&a.Mode,
			&prix,
			&a.Statut,
			&validatedAt,
			&a.CreatedAt,
			&a.UserID,
			&a.ObjetID,
			&validateurID,
			&a.Titre,
			&a.Description,
			&a.Etat,
			&a.TypeMateriau,
			&poids,
			&volume,
			&a.PhotoURL,
			&commissionPayee,
			&commissionPayeeAt,
		)
		if err != nil {
			return nil, err
		}

		if prix.Valid {
			a.Prix = &prix.Float64
		}
		if validatedAt.Valid {
			a.ValidatedAt = &validatedAt.String
		}
		if validateurID.Valid {
			v := int(validateurID.Int64)
			a.ValidateurID = &v
		}
		if poids.Valid {
			a.Poids = &poids.Float64
		}
		if volume.Valid {
			a.Volume = &volume.Float64
		}
		if commissionPayee.Valid {
			a.CommissionPayee = commissionPayee.Bool
		}
		if commissionPayeeAt.Valid {
			a.CommissionPayeeAt = &commissionPayeeAt.Time
		}

		annonces = append(annonces, a)
	}

	return annonces, nil
}

func GetAnnoncesValidees() ([]model.Annonce, error) {
	rows, err := DB.Query(`
		SELECT
			a.id_annonce,
			COALESCE(a.mode, ''),
			a.prix,
			COALESCE(a.statut, ''),
			DATE_FORMAT(a.validated_at, '%Y-%m-%d %H:%i:%s'),
			DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s'),
			a.id_user,
			a.id_objet,
			a.id_validateur,
			o.titre,
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			o.poids,
			o.volume,
			COALESCE(o.photo_url, ''),
			COALESCE(a.commission_payee, 0) AS commission_payee,
			a.commission_payee_at
		FROM annonce a
		INNER JOIN objet o ON o.id_objet = a.id_objet
		WHERE a.statut = 'validee'
		ORDER BY a.id_annonce DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var annonces []model.Annonce
	for rows.Next() {
		var a model.Annonce
		var validatedAt sql.NullString
		var validateurID sql.NullInt64
		var prix sql.NullFloat64
		var poids sql.NullFloat64
		var volume sql.NullFloat64
		var commissionPayee sql.NullBool
		var commissionPayeeAt sql.NullTime

		err := rows.Scan(
			&a.ID,
			&a.Mode,
			&prix,
			&a.Statut,
			&validatedAt,
			&a.CreatedAt,
			&a.UserID,
			&a.ObjetID,
			&validateurID,
			&a.Titre,
			&a.Description,
			&a.Etat,
			&a.TypeMateriau,
			&poids,
			&volume,
			&a.PhotoURL,
			&commissionPayee,
			&commissionPayeeAt,
		)
		if err != nil {
			return nil, err
		}

		if prix.Valid {
			a.Prix = &prix.Float64
		}
		if validatedAt.Valid {
			a.ValidatedAt = &validatedAt.String
		}
		if validateurID.Valid {
			v := int(validateurID.Int64)
			a.ValidateurID = &v
		}
		if poids.Valid {
			a.Poids = &poids.Float64
		}
		if volume.Valid {
			a.Volume = &volume.Float64
		}
		if commissionPayee.Valid {
			a.CommissionPayee = commissionPayee.Bool
		}
		if commissionPayeeAt.Valid {
			a.CommissionPayeeAt = &commissionPayeeAt.Time
		}

		annonces = append(annonces, a)
	}

	return annonces, nil
}

func GetAnnonceByID(id int) (*model.Annonce, error) {
	var a model.Annonce
	var validatedAt sql.NullString
	var validateurID sql.NullInt64
	var prix sql.NullFloat64
	var poids sql.NullFloat64
	var volume sql.NullFloat64
	var commissionPayee sql.NullBool
	var commissionPayeeAt sql.NullTime

	err := DB.QueryRow(`
		SELECT
			a.id_annonce,
			COALESCE(a.mode, ''),
			a.prix,
			COALESCE(a.statut, ''),
			DATE_FORMAT(a.validated_at, '%Y-%m-%d %H:%i:%s'),
			DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s'),
			a.id_user,
			a.id_objet,
			a.id_validateur,
			o.titre,
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			o.poids,
			o.volume,
			COALESCE(o.photo_url, ''),
			COALESCE(a.commission_payee, 0) AS commission_payee,
			a.commission_payee_at
		FROM annonce a
		INNER JOIN objet o ON o.id_objet = a.id_objet
		WHERE a.id_annonce = ?
	`, id).Scan(
		&a.ID,
		&a.Mode,
		&prix,
		&a.Statut,
		&validatedAt,
		&a.CreatedAt,
		&a.UserID,
		&a.ObjetID,
		&validateurID,
		&a.Titre,
		&a.Description,
		&a.Etat,
		&a.TypeMateriau,
		&poids,
		&volume,
		&a.PhotoURL,
		&commissionPayee,
		&commissionPayeeAt,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	if prix.Valid {
		a.Prix = &prix.Float64
	}
	if validatedAt.Valid {
		a.ValidatedAt = &validatedAt.String
	}
	if validateurID.Valid {
		v := int(validateurID.Int64)
		a.ValidateurID = &v
	}
	if poids.Valid {
		a.Poids = &poids.Float64
	}
	if volume.Valid {
		a.Volume = &volume.Float64
	}
	if commissionPayee.Valid {
		a.CommissionPayee = commissionPayee.Bool
	}
	if commissionPayeeAt.Valid {
		a.CommissionPayeeAt = &commissionPayeeAt.Time
	}

	return &a, nil
}

func GetPendingAnnonces() ([]model.Annonce, error) {
	rows, err := DB.Query(`
		SELECT
			a.id_annonce,
			COALESCE(a.mode, ''),
			a.prix,
			COALESCE(a.statut, ''),
			DATE_FORMAT(a.validated_at, '%Y-%m-%d %H:%i:%s'),
			DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s'),
			a.id_user,
			a.id_objet,
			a.id_validateur,
			o.titre,
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			o.poids,
			o.volume,
			COALESCE(o.photo_url, ''),
			COALESCE(a.commission_payee, 0) AS commission_payee,
			a.commission_payee_at
		FROM annonce a
		INNER JOIN objet o ON o.id_objet = a.id_objet
		WHERE a.statut = 'en_attente'
		ORDER BY a.id_annonce DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var annonces []model.Annonce
	for rows.Next() {
		var a model.Annonce
		var validatedAt sql.NullString
		var validateurID sql.NullInt64
		var prix sql.NullFloat64
		var poids sql.NullFloat64
		var volume sql.NullFloat64
		var commissionPayee sql.NullBool
		var commissionPayeeAt sql.NullTime

		err := rows.Scan(
			&a.ID,
			&a.Mode,
			&prix,
			&a.Statut,
			&validatedAt,
			&a.CreatedAt,
			&a.UserID,
			&a.ObjetID,
			&validateurID,
			&a.Titre,
			&a.Description,
			&a.Etat,
			&a.TypeMateriau,
			&poids,
			&volume,
			&a.PhotoURL,
			&commissionPayee,
			&commissionPayeeAt,
		)
		if err != nil {
			return nil, err
		}

		if prix.Valid {
			a.Prix = &prix.Float64
		}
		if validatedAt.Valid {
			a.ValidatedAt = &validatedAt.String
		}
		if validateurID.Valid {
			v := int(validateurID.Int64)
			a.ValidateurID = &v
		}
		if poids.Valid {
			a.Poids = &poids.Float64
		}
		if volume.Valid {
			a.Volume = &volume.Float64
		}
		if commissionPayee.Valid {
			a.CommissionPayee = commissionPayee.Bool
		}
		if commissionPayeeAt.Valid {
			a.CommissionPayeeAt = &commissionPayeeAt.Time
		}

		annonces = append(annonces, a)
	}

	return annonces, nil
}

func CreateAnnonce(a model.Annonce) error {
	tx, err := DB.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	result, err := tx.Exec(`
		INSERT INTO objet (
			titre, description, etat, type_materiau, poids, volume, photo_url, id_user
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
	`,
		a.Titre,
		a.Description,
		a.Etat,
		a.TypeMateriau,
		a.Poids,
		a.Volume,
		a.PhotoURL,
		a.UserID,
	)
	if err != nil {
		return err
	}

	objetID, err := result.LastInsertId()
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		INSERT INTO annonce (
			mode, prix, statut, id_user, id_objet
		) VALUES (?, ?, ?, ?, ?)
	`,
		a.Mode,
		a.Prix,
		a.Statut,
		a.UserID,
		objetID,
	)
	if err != nil {
		return err
	}

	return tx.Commit()
}

func UpdateAnnonce(id int, userID int, a model.Annonce) error {
	tx, err := DB.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	var objetID int
	err = tx.QueryRow(`
		SELECT id_objet
		FROM annonce
		WHERE id_annonce = ? AND id_user = ?
	`, id, userID).Scan(&objetID)

	if err == sql.ErrNoRows {
		return errors.New("annonce not found or forbidden")
	}
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		UPDATE objet
		SET titre = ?, description = ?, etat = ?, type_materiau = ?, poids = ?, volume = ?, photo_url = ?
		WHERE id_objet = ? AND id_user = ?
	`,
		a.Titre,
		a.Description,
		a.Etat,
		a.TypeMateriau,
		a.Poids,
		a.Volume,
		a.PhotoURL,
		objetID,
		userID,
	)
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		UPDATE annonce
		SET mode = ?, prix = ?, statut = ?
		WHERE id_annonce = ? AND id_user = ?
	`,
		a.Mode,
		a.Prix,
		a.Statut,
		id,
		userID,
	)
	if err != nil {
		return err
	}

	return tx.Commit()
}

func DeleteAnnonce(id int, userID int) error {
	tx, err := DB.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	var objetID int
	err = tx.QueryRow(`
		SELECT id_objet
		FROM annonce
		WHERE id_annonce = ? AND id_user = ?
	`, id, userID).Scan(&objetID)

	if err == sql.ErrNoRows {
		return errors.New("annonce not found or forbidden")
	}
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		DELETE FROM annonce
		WHERE id_annonce = ? AND id_user = ?
	`, id, userID)
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		DELETE FROM objet
		WHERE id_objet = ? AND id_user = ?
	`, objetID, userID)
	if err != nil {
		return err
	}

	return tx.Commit()
}

func ModerateAnnonce(annonceID int, validateurID int, statut string) error {
	result, err := DB.Exec(`
		UPDATE annonce
		SET statut = ?, validated_at = NOW(), id_validateur = ?
		WHERE id_annonce = ?
	`, statut, validateurID, annonceID)
	if err != nil {
		return err
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("annonce not found")
	}
	return nil
}

func GetConteneurs() ([]model.Conteneur, error) {
	rows, err := DB.Query(`
		SELECT
			id_conteneur,
			code,
			COALESCE(adresse, ''),
			COALESCE(statut, ''),
			COALESCE(DATE_FORMAT(date_installation, '%Y-%m-%d'), ''),
			COALESCE(DATE_FORMAT(derniere_maintenance, '%Y-%m-%d'), '')
		FROM conteneur
		ORDER BY id_conteneur DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var conteneurs []model.Conteneur
	for rows.Next() {
		var c model.Conteneur
		if err := rows.Scan(
			&c.ID,
			&c.Code,
			&c.Adresse,
			&c.Statut,
			&c.DateInstallation,
			&c.DerniereMaintenance,
		); err != nil {
			return nil, err
		}
		conteneurs = append(conteneurs, c)
	}
	return conteneurs, nil
}

func GetConteneurByID(id int) (*model.Conteneur, error) {
	var c model.Conteneur
	err := DB.QueryRow(`
		SELECT
			id_conteneur,
			code,
			COALESCE(adresse, ''),
			COALESCE(statut, ''),
			COALESCE(DATE_FORMAT(date_installation, '%Y-%m-%d'), ''),
			COALESCE(DATE_FORMAT(derniere_maintenance, '%Y-%m-%d'), '')
		FROM conteneur
		WHERE id_conteneur = ?
	`, id).Scan(
		&c.ID,
		&c.Code,
		&c.Adresse,
		&c.Statut,
		&c.DateInstallation,
		&c.DerniereMaintenance,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	return &c, nil
}

func CreateConteneur(c model.Conteneur) (int64, error) {
	result, err := DB.Exec(`
		INSERT INTO conteneur (
			code, adresse, statut, date_installation, derniere_maintenance
		) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
	`, c.Code, c.Adresse, c.Statut, c.DateInstallation, c.DerniereMaintenance)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

func UpdateConteneur(id int, c model.Conteneur) error {
	result, err := DB.Exec(`
		UPDATE conteneur
		SET code = ?, adresse = ?, statut = ?, date_installation = NULLIF(?, ''), derniere_maintenance = NULLIF(?, '')
		WHERE id_conteneur = ?
	`, c.Code, c.Adresse, c.Statut, c.DateInstallation, c.DerniereMaintenance, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("conteneur not found")
	}
	return nil
}

func DeleteConteneur(id int) error {
	result, err := DB.Exec(`DELETE FROM conteneur WHERE id_conteneur = ?`, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("conteneur not found")
	}
	return nil
}

func CreateObjetAndDemandeDepot(userID int, req model.CreateDemandeDepotRequest) (int64, int64, error) {
	tx, err := DB.Begin()
	if err != nil {
		return 0, 0, err
	}
	defer tx.Rollback()

	objRes, err := tx.Exec(`
		INSERT INTO objet (
			titre,
			description,
			etat,
			type_materiau,
			poids,
			volume,
			photo_url,
			id_user
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
	`,
		req.Titre,
		req.Description,
		req.Etat,
		req.TypeMateriau,
		req.Poids,
		req.Volume,
		req.PhotoURL,
		userID,
	)
	if err != nil {
		return 0, 0, err
	}

	objID, err := objRes.LastInsertId()
	if err != nil {
		return 0, 0, err
	}

	demRes, err := tx.Exec(`
		INSERT INTO demande_depot (
			statut,
			id_user,
			id_objet,
			id_conteneur
		) VALUES ('en_attente', ?, ?, ?)
	`, userID, objID, req.ConteneurID)
	if err != nil {
		return 0, 0, err
	}

	demID, err := demRes.LastInsertId()
	if err != nil {
		return 0, 0, err
	}

	if err := tx.Commit(); err != nil {
		return 0, 0, err
	}

	return objID, demID, nil
}

func GetDemandesDepotByUserID(userID int) ([]model.DemandeDepotView, error) {
	rows, err := DB.Query(`
		SELECT
			dd.id_demande,
			COALESCE(dd.statut, ''),
			COALESCE(DATE_FORMAT(dd.requested_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.validated_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.deposited_at, '%Y-%m-%d %H:%i:%s'), ''),
			c.id_conteneur,
			COALESCE(c.code, ''),
			COALESCE(c.adresse, ''),
			o.id_objet,
			COALESCE(o.titre, ''),
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			COALESCE(o.photo_url, '')
		FROM demande_depot dd
		INNER JOIN conteneur c ON c.id_conteneur = dd.id_conteneur
		INNER JOIN objet o ON o.id_objet = dd.id_objet
		WHERE dd.id_user = ?
		ORDER BY dd.id_demande DESC
	`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var demandes []model.DemandeDepotView
	for rows.Next() {
		var d model.DemandeDepotView
		if err := rows.Scan(
			&d.IDDemande,
			&d.Statut,
			&d.RequestedAt,
			&d.ValidatedAt,
			&d.DepositedAt,
			&d.IDConteneur,
			&d.CodeConteneur,
			&d.AdresseConteneur,
			&d.IDObjet,
			&d.Titre,
			&d.Description,
			&d.Etat,
			&d.TypeMateriau,
			&d.PhotoURL,
		); err != nil {
			return nil, err
		}
		demandes = append(demandes, d)
	}

	return demandes, nil
}

func GetDemandeDepotByID(id int) (*model.DemandeDepotView, error) {
	var d model.DemandeDepotView

	err := DB.QueryRow(`
		SELECT
			dd.id_demande,
			COALESCE(dd.statut, ''),
			COALESCE(DATE_FORMAT(dd.requested_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.validated_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.deposited_at, '%Y-%m-%d %H:%i:%s'), ''),
			c.id_conteneur,
			COALESCE(c.code, ''),
			COALESCE(c.adresse, ''),
			o.id_objet,
			COALESCE(o.titre, ''),
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			COALESCE(o.photo_url, '')
		FROM demande_depot dd
		INNER JOIN conteneur c ON c.id_conteneur = dd.id_conteneur
		INNER JOIN objet o ON o.id_objet = dd.id_objet
		WHERE dd.id_demande = ?
	`, id).Scan(
		&d.IDDemande,
		&d.Statut,
		&d.RequestedAt,
		&d.ValidatedAt,
		&d.DepositedAt,
		&d.IDConteneur,
		&d.CodeConteneur,
		&d.AdresseConteneur,
		&d.IDObjet,
		&d.Titre,
		&d.Description,
		&d.Etat,
		&d.TypeMateriau,
		&d.PhotoURL,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	return &d, nil
}

func DeleteDemandeDepot(id int, userID int) error {
	result, err := DB.Exec(`
		DELETE FROM demande_depot
		WHERE id_demande = ? AND id_user = ?
	`, id, userID)
	if err != nil {
		return err
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("demande not found or forbidden")
	}

	return nil
}

func ValidateDemandeDepot(id int, codeAcces string, barcodeValue string) error {
	tx, err := DB.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	_, err = tx.Exec(`
		UPDATE demande_depot
		SET statut = 'validee',
		    validated_at = NOW()
		WHERE id_demande = ?
	`, id)
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		INSERT INTO code_acces (code, expires_at, id_demande)
		VALUES (?, DATE_ADD(NOW(), INTERVAL 48 HOUR), ?)
	`, codeAcces, id)
	if err != nil {
		return err
	}

	_, err = tx.Exec(`
		INSERT INTO code_barre (barcode_value, statut, id_demande)
		VALUES (?, 'actif', ?)
	`, barcodeValue, id)
	if err != nil {
		return err
	}

	return tx.Commit()
}

func GetDepotCodes(id int) (*model.DepotCodes, error) {
	var c model.DepotCodes

	err := DB.QueryRow(`
		SELECT
			COALESCE(ca.code, ''),
			COALESCE(cb.barcode_value, ''),
			COALESCE(dd.statut, ''),
			COALESCE(DATE_FORMAT(ca.expires_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(ca.used_at, '%Y-%m-%d %H:%i:%s'), '')
		FROM demande_depot dd
		LEFT JOIN code_acces ca ON ca.id_demande = dd.id_demande
		LEFT JOIN code_barre cb ON cb.id_demande = dd.id_demande
		WHERE dd.id_demande = ?
		LIMIT 1
	`, id).Scan(
		&c.CodeAcces,
		&c.BarcodeValue,
		&c.Statut,
		&c.ExpiresAt,
		&c.UsedAt,
	)

	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	return &c, nil
}

func GetAllDemandesDepot() ([]model.DemandeDepotView, error) {
	rows, err := DB.Query(`
		SELECT
			dd.id_demande,
			COALESCE(dd.statut, ''),
			COALESCE(DATE_FORMAT(dd.requested_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.validated_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(dd.deposited_at, '%Y-%m-%d %H:%i:%s'), ''),
			c.id_conteneur,
			COALESCE(c.code, ''),
			COALESCE(c.adresse, ''),
			o.id_objet,
			COALESCE(o.titre, ''),
			COALESCE(o.description, ''),
			COALESCE(o.etat, ''),
			COALESCE(o.type_materiau, ''),
			COALESCE(o.photo_url, '')
		FROM demande_depot dd
		INNER JOIN conteneur c ON c.id_conteneur = dd.id_conteneur
		INNER JOIN objet o ON o.id_objet = dd.id_objet
		ORDER BY dd.id_demande DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var demandes []model.DemandeDepotView
	for rows.Next() {
		var d model.DemandeDepotView
		if err := rows.Scan(
			&d.IDDemande,
			&d.Statut,
			&d.RequestedAt,
			&d.ValidatedAt,
			&d.DepositedAt,
			&d.IDConteneur,
			&d.CodeConteneur,
			&d.AdresseConteneur,
			&d.IDObjet,
			&d.Titre,
			&d.Description,
			&d.Etat,
			&d.TypeMateriau,
			&d.PhotoURL,
		); err != nil {
			return nil, err
		}
		demandes = append(demandes, d)
	}

	return demandes, nil
}

func countActiveInscriptionsBySessionID(sessionID int) (int, error) {
	var count int
	err := DB.QueryRow(`
		SELECT COUNT(*)
		FROM inscription
		WHERE id_session = ?
		  AND COALESCE(statut, '') <> 'annulee'
	`, sessionID).Scan(&count)
	return count, err
}

func userHasActiveInscription(userID int, sessionID int) (bool, error) {
	var count int
	err := DB.QueryRow(`
		SELECT COUNT(*)
		FROM inscription
		WHERE id_user = ?
		  AND id_session = ?
		  AND COALESCE(statut, '') <> 'annulee'
	`, userID, sessionID).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func CreateInscription(userID int, sessionID int) (int64, error) {
	event, err := GetEventByID(sessionID)
	if err != nil {
		return 0, err
	}
	if event == nil {
		return 0, errors.New("event not found")
	}
	if event.Statut != "valide" {
		return 0, errors.New("event not active")
	}

	alreadyRegistered, err := userHasActiveInscription(userID, sessionID)
	if err != nil {
		return 0, err
	}
	if alreadyRegistered {
		return 0, errors.New("already registered")
	}

	currentCount, err := countActiveInscriptionsBySessionID(sessionID)
	if err != nil {
		return 0, err
	}
	if event.CapaciteMax > 0 && currentCount >= event.CapaciteMax {
		return 0, errors.New("event full")
	}

	result, err := DB.Exec(`
		INSERT INTO inscription (statut, id_user, id_session)
		VALUES ('confirmee', ?, ?)
	`, userID, sessionID)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

func GetMyInscriptions(userID int) ([]model.MyInscriptionView, error) {
	rows, err := DB.Query(`
		SELECT
			i.id_inscription,
			COALESCE(i.statut, ''),
			COALESCE(DATE_FORMAT(i.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			s.id_session,
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(s.date_fin, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(s.lieu, ''),
			COALESCE(s.capacite_max, 0),
			COALESCE(s.statut, ''),
			p.id_prestation,
			COALESCE(p.titre, ''),
			COALESCE(p.type, ''),
			COALESCE(p.prix, 0)
		FROM inscription i
		INNER JOIN (
			SELECT id_session, MAX(id_inscription) AS last_inscription_id
			FROM inscription
			WHERE id_user = ?
			GROUP BY id_session
		) latest ON latest.last_inscription_id = i.id_inscription
		INNER JOIN session s ON s.id_session = i.id_session
		INNER JOIN prestation p ON p.id_prestation = s.id_prestation
		WHERE i.id_user = ?
		ORDER BY s.date_debut DESC, i.id_inscription DESC
	`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var inscriptions []model.MyInscriptionView
	for rows.Next() {
		var ins model.MyInscriptionView
		if err := rows.Scan(
			&ins.IDInscription,
			&ins.Statut,
			&ins.CreatedAt,
			&ins.IDSession,
			&ins.DateDebut,
			&ins.DateFin,
			&ins.Lieu,
			&ins.CapaciteMax,
			&ins.SessionStatut,
			&ins.IDPrestation,
			&ins.PrestationTitre,
			&ins.PrestationType,
			&ins.PrestationPrix,
		); err != nil {
			return nil, err
		}
		inscriptions = append(inscriptions, ins)
	}
	return inscriptions, nil
}

func CancelInscription(inscriptionID int, userID int) error {
	result, err := DB.Exec(`
		UPDATE inscription
		SET statut = 'annulee'
		WHERE id_inscription = ? AND id_user = ?
	`, inscriptionID, userID)
	if err != nil {
		return err
	}
	affected, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("inscription not found")
	}
	return nil
}

func GetInscriptionByID(inscriptionID int, userID int) (int, float64, error) {
	var sessionID int
	var prix float64

	err := DB.QueryRow(`
		SELECT s.id_session, COALESCE(p.prix, 0)
		FROM inscription i
		INNER JOIN session s ON s.id_session = i.id_session
		INNER JOIN prestation p ON p.id_prestation = s.id_prestation
		WHERE i.id_inscription = ?
		  AND i.id_user = ?
		  AND COALESCE(i.statut, '') = 'confirmee'
	`, inscriptionID, userID).Scan(&sessionID, &prix)

	if err == sql.ErrNoRows {
		return 0, 0, errors.New("inscription not found")
	}
	if err != nil {
		return 0, 0, err
	}
	return sessionID, prix, nil
}

func PaiementExistsForInscription(inscriptionID int) (bool, error) {
	var count int
	err := DB.QueryRow(`
		SELECT COUNT(*)
		FROM paiement
		WHERE id_inscription = ?
		  AND COALESCE(statut, '') = 'paid'
	`, inscriptionID).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func CreatePaiementForInscription(inscriptionID int, userID int) (int64, error) {
	_, montant, err := GetInscriptionByID(inscriptionID, userID)
	if err != nil {
		return 0, err
	}

	exists, err := PaiementExistsForInscription(inscriptionID)
	if err != nil {
		return 0, err
	}
	if exists {
		return 0, errors.New("payment already exists")
	}

	paymentRef := fmt.Sprintf("PAY-%d-%d", inscriptionID, time.Now().Unix())

	result, err := DB.Exec(`
		INSERT INTO paiement (
			provider,
			payment_ref,
			montant,
			devise,
			statut,
			paid_at,
			id_inscription
		) VALUES (?, ?, ?, ?, ?, NOW(), ?)
	`, "mock", paymentRef, montant, "EUR", "paid", inscriptionID)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

func GetMyPaiements(userID int) ([]model.MyPaiementView, error) {
	rows, err := DB.Query(`
		SELECT
			pa.id_paiement,
			COALESCE(pa.provider, ''),
			COALESCE(pa.payment_ref, ''),
			COALESCE(pa.montant, 0),
			COALESCE(pa.devise, ''),
			COALESCE(pa.statut, ''),
			COALESCE(DATE_FORMAT(pa.paid_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(pa.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			pa.id_inscription,
			s.id_session,
			COALESCE(pr.titre, ''),
			COALESCE(pr.type, ''),
			COALESCE(s.lieu, ''),
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(u.email, ''),
			COALESCE(u.pseudo, ''),
			COALESCE(u.prenom, ''),
			COALESCE(u.nom, '')
		FROM paiement pa
		INNER JOIN inscription i ON i.id_inscription = pa.id_inscription
		INNER JOIN session s ON s.id_session = i.id_session
		INNER JOIN prestation pr ON pr.id_prestation = s.id_prestation
		INNER JOIN utilisateur u ON u.id_user = i.id_user
		WHERE i.id_user = ?
		ORDER BY pa.id_paiement DESC
	`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var paiements []model.MyPaiementView
	for rows.Next() {
		var p model.MyPaiementView
		if err := rows.Scan(
			&p.IDPaiement,
			&p.Provider,
			&p.PaymentRef,
			&p.Montant,
			&p.Devise,
			&p.Statut,
			&p.PaidAt,
			&p.CreatedAt,
			&p.IDInscription,
			&p.IDSession,
			&p.PrestationTitre,
			&p.PrestationType,
			&p.Lieu,
			&p.DateDebut,
			&p.UserEmail,
			&p.UserPseudo,
			&p.UserPrenom,
			&p.UserNom,
		); err != nil {
			return nil, err
		}
		paiements = append(paiements, p)
	}
	return paiements, nil
}

func GetAllPaiements() ([]model.MyPaiementView, error) {
	rows, err := DB.Query(`
		SELECT
			pa.id_paiement,
			COALESCE(pa.provider, ''),
			COALESCE(pa.payment_ref, ''),
			COALESCE(pa.montant, 0),
			COALESCE(pa.devise, ''),
			COALESCE(pa.statut, ''),
			COALESCE(DATE_FORMAT(pa.paid_at, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(DATE_FORMAT(pa.created_at, '%Y-%m-%d %H:%i:%s'), ''),
			pa.id_inscription,
			s.id_session,
			COALESCE(pr.titre, ''),
			COALESCE(pr.type, ''),
			COALESCE(s.lieu, ''),
			COALESCE(DATE_FORMAT(s.date_debut, '%Y-%m-%d %H:%i:%s'), ''),
			COALESCE(u.email, ''),
			COALESCE(u.pseudo, ''),
			COALESCE(u.prenom, ''),
			COALESCE(u.nom, '')
		FROM paiement pa
		INNER JOIN inscription i ON i.id_inscription = pa.id_inscription
		INNER JOIN session s ON s.id_session = i.id_session
		INNER JOIN prestation pr ON pr.id_prestation = s.id_prestation
		INNER JOIN utilisateur u ON u.id_user = i.id_user
		ORDER BY pa.id_paiement DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var paiements []model.MyPaiementView
	for rows.Next() {
		var p model.MyPaiementView
		if err := rows.Scan(
			&p.IDPaiement,
			&p.Provider,
			&p.PaymentRef,
			&p.Montant,
			&p.Devise,
			&p.Statut,
			&p.PaidAt,
			&p.CreatedAt,
			&p.IDInscription,
			&p.IDSession,
			&p.PrestationTitre,
			&p.PrestationType,
			&p.Lieu,
			&p.DateDebut,
			&p.UserEmail,
			&p.UserPseudo,
			&p.UserPrenom,
			&p.UserNom,
		); err != nil {
			return nil, err
		}
		paiements = append(paiements, p)
	}
	return paiements, nil
}

func scanConseilRow(scanner interface{ Scan(dest ...any) error }, c *model.Conseil) error {
	return scanner.Scan(
		&c.IDConseil,
		&c.Titre,
		&c.Contenu,
		&c.Categorie,
		&c.ImageURL,
		&c.IsActive,
		&c.IDAuteur,
		&c.CreatedAt,
	)
}

const conseilSelect = `
	SELECT
		id_conseil,
		COALESCE(titre, ''),
		COALESCE(contenu, ''),
		COALESCE(categorie, ''),
		COALESCE(image_url, ''),
		COALESCE(is_active, 0),
		COALESCE(id_auteur, 0),
		COALESCE(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), '')
	FROM conseil`

func GetConseils() ([]model.Conseil, error) {
	rows, err := DB.Query(conseilSelect + `
		WHERE is_active = 1
		ORDER BY created_at DESC, id_conseil DESC
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var conseils []model.Conseil
	for rows.Next() {
		var c model.Conseil
		if err := scanConseilRow(rows, &c); err != nil {
			return nil, err
		}
		conseils = append(conseils, c)
	}
	return conseils, nil
}

func GetConseilsByAuthor(authorID int) ([]model.Conseil, error) {
	rows, err := DB.Query(conseilSelect+`
		WHERE id_auteur = ?
		ORDER BY created_at DESC, id_conseil DESC
	`, authorID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var conseils []model.Conseil
	for rows.Next() {
		var c model.Conseil
		if err := scanConseilRow(rows, &c); err != nil {
			return nil, err
		}
		conseils = append(conseils, c)
	}
	return conseils, nil
}

func GetConseilAuthorID(id int) (int, error) {
	var authorID int
	err := DB.QueryRow(`SELECT COALESCE(id_auteur, 0) FROM conseil WHERE id_conseil = ?`, id).Scan(&authorID)
	if err == sql.ErrNoRows {
		return 0, errors.New("conseil not found")
	}
	return authorID, err
}

func GetConseilByID(id int) (*model.Conseil, error) {
	var c model.Conseil
	err := DB.QueryRow(conseilSelect+`
		WHERE id_conseil = ?
		  AND is_active = 1
	`, id).Scan(
		&c.IDConseil,
		&c.Titre,
		&c.Contenu,
		&c.Categorie,
		&c.ImageURL,
		&c.IsActive,
		&c.IDAuteur,
		&c.CreatedAt,
	)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &c, nil
}

func GetConseilByIDAny(id int) (*model.Conseil, error) {
	var c model.Conseil
	err := DB.QueryRow(conseilSelect+` WHERE id_conseil = ?`, id).Scan(
		&c.IDConseil,
		&c.Titre,
		&c.Contenu,
		&c.Categorie,
		&c.ImageURL,
		&c.IsActive,
		&c.IDAuteur,
		&c.CreatedAt,
	)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &c, nil
}

func GetAllConseilsAdmin() ([]model.Conseil, error) {
	items, _, err := SearchConseilsAdmin(model.ConseilFilter{Page: 1, PerPage: 10000})
	return items, err
}

const conseilAdminSelect = `
	SELECT
		c.id_conseil,
		COALESCE(c.titre, ''),
		COALESCE(c.contenu, ''),
		COALESCE(c.categorie, ''),
		COALESCE(c.image_url, ''),
		COALESCE(c.is_active, 0),
		COALESCE(c.id_auteur, 0),
		COALESCE(DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s'), ''),
		COALESCE(u.pseudo, ''),
		COALESCE(u.email, '')
	FROM conseil c
	LEFT JOIN utilisateur u ON u.id_user = c.id_auteur`

func scanConseilAdminRow(scanner interface{ Scan(dest ...any) error }, c *model.Conseil) error {
	return scanner.Scan(
		&c.IDConseil,
		&c.Titre,
		&c.Contenu,
		&c.Categorie,
		&c.ImageURL,
		&c.IsActive,
		&c.IDAuteur,
		&c.CreatedAt,
		&c.AuteurPseudo,
		&c.AuteurEmail,
	)
}

func SearchConseilsAdmin(f model.ConseilFilter) ([]model.Conseil, int, error) {
	if f.Page < 1 {
		f.Page = 1
	}
	if f.PerPage < 1 {
		f.PerPage = 25
	}
	if f.PerPage > 200 {
		f.PerPage = 200
	}

	where := []string{"1=1"}
	args := []any{}

	switch strings.ToLower(strings.TrimSpace(f.Status)) {
	case "draft", "brouillon":
		where = append(where, "c.is_active = 0")
	case "published", "publie", "active", "publié":
		where = append(where, "c.is_active = 1")
	}

	if f.AuthorID > 0 {
		where = append(where, "c.id_auteur = ?")
		args = append(args, f.AuthorID)
	}

	if strings.TrimSpace(f.Query) != "" {
		q := "%" + strings.TrimSpace(f.Query) + "%"
		where = append(where, "(c.titre LIKE ? OR c.contenu LIKE ? OR c.categorie LIKE ?)")
		args = append(args, q, q, q)
	}

	if strings.TrimSpace(f.DateFrom) != "" {
		where = append(where, "DATE(c.created_at) >= ?")
		args = append(args, strings.TrimSpace(f.DateFrom))
	}
	if strings.TrimSpace(f.DateTo) != "" {
		where = append(where, "DATE(c.created_at) <= ?")
		args = append(args, strings.TrimSpace(f.DateTo))
	}

	whereSQL := strings.Join(where, " AND ")

	var total int
	countQ := "SELECT COUNT(*) FROM conseil c WHERE " + whereSQL
	if err := DB.QueryRow(countQ, args...).Scan(&total); err != nil {
		return nil, 0, err
	}

	offset := (f.Page - 1) * f.PerPage
	listArgs := append(args, f.PerPage, offset)
	rows, err := DB.Query(conseilAdminSelect+`
		WHERE `+whereSQL+`
		ORDER BY c.created_at DESC, c.id_conseil DESC
		LIMIT ? OFFSET ?
	`, listArgs...)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()

	var conseils []model.Conseil
	for rows.Next() {
		var c model.Conseil
		if err := scanConseilAdminRow(rows, &c); err != nil {
			return nil, 0, err
		}
		conseils = append(conseils, c)
	}
	return conseils, total, nil
}

func CreateConseil(c model.Conseil) (int64, error) {
	var idAuteur any
	if c.IDAuteur > 0 {
		idAuteur = c.IDAuteur
	} else {
		idAuteur = nil
	}
	result, err := DB.Exec(`
		INSERT INTO conseil (titre, contenu, categorie, image_url, is_active, id_auteur)
		VALUES (?, ?, ?, ?, ?, ?)
	`, c.Titre, c.Contenu, c.Categorie, c.ImageURL, c.IsActive, idAuteur)
	if err != nil {
		return 0, err
	}
	return result.LastInsertId()
}

func UpdateConseil(id int, c model.Conseil) error {
	result, err := DB.Exec(`
		UPDATE conseil
		SET titre = ?, contenu = ?, categorie = ?, image_url = ?, is_active = ?
		WHERE id_conseil = ?
	`, c.Titre, c.Contenu, c.Categorie, c.ImageURL, c.IsActive, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("conseil not found")
	}
	return nil
}

func DeleteConseil(id int) error {
	result, err := DB.Exec(`DELETE FROM conseil WHERE id_conseil = ?`, id)
	if err != nil {
		return err
	}
	affected, _ := result.RowsAffected()
	if affected == 0 {
		return errors.New("conseil not found")
	}
	return nil
}

func GetUserScore(userID int) (*model.UserScore, error) {
	var annoncesCount int
	var depotsValidesCount int
	var ateliersCount int
	var paiementsCount int

	err := DB.QueryRow(`
		SELECT COUNT(*)
		FROM annonce
		WHERE id_user = ?
	`, userID).Scan(&annoncesCount)
	if err != nil {
		return nil, err
	}

	err = DB.QueryRow(`
		SELECT COUNT(*)
		FROM demande_depot
		WHERE id_user = ?
		  AND COALESCE(statut, '') = 'validee'
	`, userID).Scan(&depotsValidesCount)
	if err != nil {
		return nil, err
	}

	err = DB.QueryRow(`
		SELECT COUNT(*)
		FROM inscription
		WHERE id_user = ?
		  AND COALESCE(statut, '') = 'confirmee'
	`, userID).Scan(&ateliersCount)
	if err != nil {
		return nil, err
	}

	err = DB.QueryRow(`
		SELECT COUNT(*)
		FROM paiement p
		INNER JOIN inscription i ON i.id_inscription = p.id_inscription
		WHERE i.id_user = ?
		  AND COALESCE(p.statut, '') = 'paid'
	`, userID).Scan(&paiementsCount)
	if err != nil {
		return nil, err
	}

	score := (annoncesCount * 8) + (depotsValidesCount * 12) + (ateliersCount * 10) + (paiementsCount * 5)
	if score > 100 {
		score = 100
	}

	niveau := "Débutant"
	switch {
	case score >= 80:
		niveau = "Upcycleur Avancé"
	case score >= 60:
		niveau = "Upcycleur Confirmé"
	case score >= 40:
		niveau = "Upcycleur Actif"
	}

	co2 := (annoncesCount * 2) + (depotsValidesCount * 3) + (ateliersCount * 2)

	return &model.UserScore{
		ScoreGlobal:        score,
		Niveau:             niveau,
		AnnoncesCount:      annoncesCount,
		DepotsValidesCount: depotsValidesCount,
		AteliersCount:      ateliersCount,
		PaiementsCount:     paiementsCount,
		CO2EconomiseKg:     co2,
	}, nil
}
func UpdateUser(id int, req model.UpdateUserRequest, passwordHash string) error {
	current, err := GetUserByID(id)
	if err != nil {
		return err
	}
	if current == nil {
		return errors.New("user not found")
	}

	
	setParts := []string{}
	args := []any{}

	if req.Email != nil {
		setParts = append(setParts, "email = ?")
		args = append(args, *req.Email)
	}
	if passwordHash != "" {
		setParts = append(setParts, "password_hash = ?")
		args = append(args, passwordHash)
	}
	if req.Pseudo != nil {
		setParts = append(setParts, "pseudo = ?")
		args = append(args, *req.Pseudo)
	}
	if req.Prenom != nil {
		setParts = append(setParts, "prenom = ?")
		args = append(args, *req.Prenom)
	}
	if req.Nom != nil {
		setParts = append(setParts, "nom = ?")
		args = append(args, *req.Nom)
	}
	if req.Telephone != nil {
		setParts = append(setParts, "telephone = ?")
		args = append(args, *req.Telephone)
	}
	if req.AdresseRue != nil {
		setParts = append(setParts, "adresse_rue = ?")
		args = append(args, *req.AdresseRue)
	}
	if req.AdresseVille != nil {
		setParts = append(setParts, "adresse_ville = ?")
		args = append(args, *req.AdresseVille)
	}
	if req.AdresseCodePostal != nil {
		setParts = append(setParts, "adresse_code_postal = ?")
		args = append(args, *req.AdresseCodePostal)
	}
	if req.AdressePays != nil {
		setParts = append(setParts, "adresse_pays = ?")
		args = append(args, *req.AdressePays)
	}
	if req.PhotoProfil != nil {
		setParts = append(setParts, "photo_profil = ?")
		args = append(args, *req.PhotoProfil)
	}
	if req.Bio != nil {
		setParts = append(setParts, "bio = ?")
		args = append(args, *req.Bio)
	}
	if req.Statut != nil {
		setParts = append(setParts, "statut = ?")
		args = append(args, *req.Statut)
	}
	if req.RoleID != nil {
		setParts = append(setParts, "id_role = ?")
		args = append(args, *req.RoleID)
	}
	if req.IsApproved != nil {
		setParts = append(setParts, "is_approved = ?")
		args = append(args, *req.IsApproved)
	}

	if len(setParts) == 0 {
		return errors.New("no fields to update")
	}

	query := "UPDATE utilisateur SET " + strings.Join(setParts, ", ") + " WHERE id_user = ?"
	args = append(args, id)

	result, err := DB.Exec(query, args...)
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
func GetUsersByRole(roleID int) ([]model.User, error) {
	rows, err := DB.Query(userSelect+` WHERE id_role = ? ORDER BY id_user DESC`, roleID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var users []model.User
	for rows.Next() {
		var user model.User
		if err := scanUser(rows, &user); err != nil {
			return nil, err
		}
		users = append(users, user)
	}
	return users, nil
}
