package model

type Event struct {
	ID           int    `json:"id_session"`
	DateDebut    string `json:"date_debut"`
	DateFin      string `json:"date_fin"`
	Lieu         string `json:"lieu"`
	CapaciteMax  int    `json:"capacite_max"`
	Statut       string `json:"statut"`
	CreatedAt    string `json:"created_at"`
	PrestationID int    `json:"id_prestation"`
	ValidateurID int    `json:"id_validateur"`
}
