package model

type Inscription struct {
	ID        int    `json:"id_inscription"`
	Statut    string `json:"statut"`
	CreatedAt string `json:"created_at"`
	UserID    int    `json:"id_user"`
	SessionID int    `json:"id_session"`
}

type MyInscriptionView struct {
	IDInscription    int     `json:"id_inscription"`
	Statut           string  `json:"statut"`
	CreatedAt        string  `json:"created_at"`
	IDSession        int     `json:"id_session"`
	DateDebut        string  `json:"date_debut"`
	DateFin          string  `json:"date_fin"`
	Lieu             string  `json:"lieu"`
	CapaciteMax      int     `json:"capacite_max"`
	SessionStatut    string  `json:"session_statut"`
	IDPrestation     int     `json:"id_prestation"`
	PrestationTitre  string  `json:"prestation_titre"`
	PrestationType   string  `json:"prestation_type"`
	PrestationPrix   float64 `json:"prestation_prix"`
}
