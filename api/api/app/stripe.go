package app

import (
	"encoding/json"
	"log"
	"net/http"
	"strconv"

	"github.com/stripe/stripe-go/v78"
	"github.com/stripe/stripe-go/v78/checkout/session"
)

func init() {
	stripe.Key = "sk_test_51TgtT9V05VqXDcgMFj94nV75YrYO67bcoLXokkldSJLCDaQeNsTaX26jpc5RJp7Y0IE2Zkt3VDdxpgZ3fjma2KFd00AmEOIMOG"
	log.Println("✅ Stripe key loaded")
}

func CreateCheckoutSession(w http.ResponseWriter, r *http.Request) {
	log.Println("📦 CreateCheckoutSession called, method:", r.Method)

	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		log.Println("❌ Authentication failed")
		return
	}
	log.Println("✅ User authenticated:", claims.UserID)

	var payload struct {
		Amount        int64  `json:"amount"`
		ItemName      string `json:"item_name"`
		InscriptionID string `json:"inscription_id"`
		PrestationID  string `json:"prestation_id"`
		AnnonceID     string `json:"annonce_id"`
		FactureID     string `json:"facture_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		payload.Amount = 2999
		payload.ItemName = "Abonnement UpcycleConnect"
	}
	log.Printf("💰 Amount: %.2f€ for %s", float64(payload.Amount)/100, payload.ItemName)

	params := &stripe.CheckoutSessionParams{
		PaymentMethodTypes: stripe.StringSlice([]string{"card"}),
		LineItems: []*stripe.CheckoutSessionLineItemParams{
			{
				PriceData: &stripe.CheckoutSessionLineItemPriceDataParams{
					Currency: stripe.String("eur"),
					ProductData: &stripe.CheckoutSessionLineItemPriceDataProductDataParams{
						Name:        stripe.String(payload.ItemName),
						Description: stripe.String("Paiement sécurisé UpcycleConnect"),
						Images:      stripe.StringSlice([]string{"http://localhost/upcycle/assets/logo.png"}),
					},
					UnitAmount: stripe.Int64(payload.Amount),
				},
				Quantity: stripe.Int64(1),
			},
		},
		Mode:       stripe.String(string(stripe.CheckoutSessionModePayment)),
		SuccessURL: stripe.String("http://localhost/upcycle/paiement_success.php?session_id={CHECKOUT_SESSION_ID}"),
		CancelURL:  stripe.String("http://localhost/upcycle/paiement_cancel.php"),
		Metadata: map[string]string{
			"user_id":        strconv.Itoa(claims.UserID),
			"item_name":      payload.ItemName,
			"inscription_id": payload.InscriptionID,
			"prestation_id":  payload.PrestationID,
			"annonce_id":     payload.AnnonceID,
			"facture_id":     payload.FactureID,
		},
	}

	s, err := session.New(params)
	if err != nil {
		log.Println("❌ Stripe error:", err.Error())
		http.Error(w, "Erreur Stripe: "+err.Error(), http.StatusInternalServerError)
		return
	}

	log.Println("✅ Stripe session created")
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"url": s.URL})
}

// VerifyPayment vérifie un paiement Stripe
func VerifyPayment(w http.ResponseWriter, r *http.Request) {
	sessionID := r.URL.Query().Get("session_id")
	if sessionID == "" {
		http.Error(w, "Missing session_id", http.StatusBadRequest)
		return
	}

	s, err := session.Get(sessionID, nil)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"paid":       s.PaymentStatus == stripe.CheckoutSessionPaymentStatusPaid,
		"amount":     float64(s.AmountTotal) / 100,
		"metadata":   s.Metadata,
		"session_id": s.ID,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}
