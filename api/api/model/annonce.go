package model

type Annonce struct {
	ID           int      `json:"id_annonce"`
	Mode         string   `json:"mode"`
	Prix         *float64 `json:"prix"`
	Statut       string   `json:"statut"`
	ValidatedAt  *string  `json:"validated_at"`
	CreatedAt    string   `json:"created_at"`
	UserID       int      `json:"id_user"`
	ObjetID      int      `json:"id_objet"`
	ValidateurID *int     `json:"id_validateur"`

	Titre        string   `json:"titre"`
	Description  string   `json:"description"`
	Etat         string   `json:"etat"`
	TypeMateriau string   `json:"type_materiau"`
	Poids        *float64 `json:"poids"`
	Volume       *float64 `json:"volume"`
	PhotoURL     string   `json:"photo_url"`
}
