package model

type Conseil struct {
	IDConseil    int    `json:"id_conseil"`
	Titre        string `json:"titre"`
	Contenu      string `json:"contenu"`
	Categorie    string `json:"categorie"`
	ImageURL     string `json:"image_url"`
	IsActive     bool   `json:"is_active"`
	IDAuteur     int    `json:"id_auteur"`
	CreatedAt    string `json:"created_at"`
	Statut       string `json:"statut,omitempty"`
	AuteurPseudo string `json:"auteur_pseudo,omitempty"`
	AuteurEmail  string `json:"auteur_email,omitempty"`
}

type ConseilFilter struct {
	Status   string
	AuthorID int
	Query    string
	DateFrom string
	DateTo   string
	Page     int
	PerPage  int
}
