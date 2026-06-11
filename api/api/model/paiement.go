package model

type Paiement struct {
	IDPaiement    int     `json:"id_paiement"`
	Provider      string  `json:"provider"`
	PaymentRef    string  `json:"payment_ref"`
	Montant       float64 `json:"montant"`
	Devise        string  `json:"devise"`
	Statut        string  `json:"statut"`
	PaidAt        string  `json:"paid_at"`
	CreatedAt     string  `json:"created_at"`
	IDInscription int     `json:"id_inscription"`
	IDUser        int     `json:"id_user"`
	Metadata      string  `json:"metadata"`
}

type MyPaiementView struct {
	IDPaiement      int     `json:"id_paiement"`
	Provider        string  `json:"provider"`
	PaymentRef      string  `json:"payment_ref"`
	Montant         float64 `json:"montant"`
	Devise          string  `json:"devise"`
	Statut          string  `json:"statut"`
	PaidAt          string  `json:"paid_at"`
	CreatedAt       string  `json:"created_at"`
	IDInscription   int     `json:"id_inscription"`
	IDSession       int     `json:"id_session"`
	PrestationTitre string  `json:"prestation_titre"`
	PrestationType  string  `json:"prestation_type"`
	Lieu            string  `json:"lieu"`
	DateDebut       string  `json:"date_debut"`
	UserEmail       string  `json:"user_email"`
	UserPseudo      string  `json:"user_pseudo"`
	UserPrenom      string  `json:"user_prenom"`
	UserNom         string  `json:"user_nom"`
}
