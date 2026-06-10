package model

type Event struct {
	IDSession        int    `json:"id_session"`
	DateDebut        string `json:"date_debut"`
	DateFin          string `json:"date_fin"`
	Lieu             string `json:"lieu"`
	CapaciteMax      int    `json:"capacite_max"`
	Statut           string `json:"statut"`
	CreatedAt        string `json:"created_at"`
	IDPrestation     int    `json:"id_prestation"`
	PrestationTitre  string `json:"prestation_titre"`
	PrestationPrix   float64 `json:"prestation_prix"`
	IDValidateur     int    `json:"id_validateur"`
	IDCreateur       int    `json:"id_createur"`
	InscritsCount    int    `json:"inscrits_count"`
	PlacesRestantes  int    `json:"places_restantes"`
}
