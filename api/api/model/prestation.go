package model

type Prestation struct {
	ID          int     `json:"id_prestation"`
	Titre       string  `json:"titre"`
	Description string  `json:"description"`
	Type        string  `json:"type"`
	Prix        float64 `json:"prix"`
	IsActive    bool    `json:"is_active"`
	CreatedAt   string  `json:"created_at"`
	IDCategorie int     `json:"id_categorie"`
	IDUser      int     `json:"id_user"`
}
