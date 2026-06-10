package model

type UserScore struct {
	ScoreGlobal        int    `json:"score_global"`
	Niveau             string `json:"niveau"`
	AnnoncesCount      int    `json:"annonces_count"`
	DepotsValidesCount int    `json:"depots_valides_count"`
	AteliersCount      int    `json:"ateliers_count"`
	PaiementsCount     int    `json:"paiements_count"`
	CO2EconomiseKg     int    `json:"co2_economise_kg"`
}