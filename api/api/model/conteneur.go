package model

type Conteneur struct {
	ID int `json:"id_conteneur"`

	Code string `json:"code"`

	Adresse string `json:"adresse"`

	Statut string `json:"statut"`

	DateInstallation string `json:"date_installation,omitempty"`

	DerniereMaintenance string `json:"derniere_maintenance,omitempty"`
}

type CreateDemandeDepotRequest struct {
	ConteneurID int `json:"id_conteneur"`

	Titre string `json:"titre"`

	Description string `json:"description"`

	Etat string `json:"etat"`

	TypeMateriau string `json:"type_materiau"`

	Poids *float64 `json:"poids,omitempty"`

	Volume *float64 `json:"volume,omitempty"`

	PhotoURL string `json:"photo_url"`
}

type DemandeDepotView struct {
	IDDemande int `json:"id_demande"`

	Statut string `json:"statut"`

	RequestedAt string `json:"requested_at,omitempty"`

	ValidatedAt string `json:"validated_at,omitempty"`

	DepositedAt string `json:"deposited_at,omitempty"`

	IDConteneur int `json:"id_conteneur"`

	CodeConteneur string `json:"code_conteneur"`

	AdresseConteneur string `json:"adresse_conteneur"`

	IDObjet int `json:"id_objet"`

	Titre string `json:"titre"`

	Description string `json:"description"`

	Etat string `json:"etat"`

	TypeMateriau string `json:"type_materiau"`

	PhotoURL string `json:"photo_url"`
}

type DepotCodes struct {
	CodeAcces string `json:"code_acces"`

	BarcodeValue string `json:"barcode_value"`

	Statut string `json:"statut"`

	ExpiresAt string `json:"expires_at,omitempty"`

	UsedAt string `json:"used_at,omitempty"`
}
