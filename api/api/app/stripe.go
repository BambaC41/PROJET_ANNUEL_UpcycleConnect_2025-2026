package app

import (
	"api/db"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"time"

	"github.com/jung-kurt/gofpdf"
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
		SessionID     string `json:"session_id"`
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
			"php_session_id": payload.SessionID,
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

func generateInvoiceNumber() string {
	return fmt.Sprintf("FACT-%s-%d", time.Now().Format("20060102"), time.Now().UnixNano()%10000)
}

func generatePaymentReceipt(userID int, amount float64, itemName string, metadata map[string]string) (string, error) {
	receiptDir := "uploads/receipts"
	if err := os.MkdirAll(receiptDir, 0755); err != nil {
		return "", err
	}

	invoiceNumber := generateInvoiceNumber()
	filename := fmt.Sprintf("facture_%d_%s.pdf", userID, time.Now().Format("20060102_150405"))
	fullPath := filepath.Join(receiptDir, filename)

	// Récupérer infos utilisateur
	var userEmail, userPseudo string
	db.DB.QueryRow(`
		SELECT COALESCE(email, ''), COALESCE(pseudo, '')
		FROM utilisateur WHERE id_user = ?
	`, userID).Scan(&userEmail, &userPseudo)

	tvaRate := 0.20
	amountHT := amount / (1 + tvaRate)
	amountTVA := amount - amountHT

	pdf := gofpdf.New("P", "mm", "A4", "")
	pdf.AddPage()

	// En-tête
	pdf.SetFont("Arial", "B", 24)
	pdf.SetTextColor(34, 139, 34)
	pdf.Cell(0, 15, "UpcycleConnect")
	pdf.Ln(10)

	pdf.SetFont("Arial", "", 10)
	pdf.SetTextColor(100, 100, 100)
	pdf.Cell(0, 5, "174 rue La Fayette, 75010 Paris")
	pdf.Ln(5)
	pdf.Cell(0, 5, "SIRET: 12345678900012")
	pdf.Ln(5)
	pdf.Cell(0, 5, "contact@upcycleconnect.fr")
	pdf.Ln(12)

	// Titre
	pdf.SetFont("Arial", "B", 18)
	pdf.SetTextColor(0, 0, 0)
	pdf.Cell(0, 10, "FACTURE")
	pdf.Ln(12)

	// Infos facture
	pdf.SetFont("Arial", "", 10)
	pdf.Cell(40, 6, "N° Facture:")
	pdf.Cell(80, 6, invoiceNumber)
	pdf.Cell(30, 6, "Date:")
	pdf.Cell(0, 6, time.Now().Format("02/01/2006"))
	pdf.Ln(7)

	pdf.Cell(40, 6, "Client:")
	pdf.Cell(80, 6, fmt.Sprintf("UP-%d", userID))
	pdf.Cell(30, 6, "Pseudo:")
	pdf.Cell(0, 6, userPseudo)
	pdf.Ln(7)

	pdf.Cell(40, 6, "Email:")
	pdf.Cell(0, 6, userEmail)
	pdf.Ln(12)

	// Détail
	pdf.SetFont("Arial", "B", 11)
	pdf.Cell(0, 8, "Detail de la prestation")
	pdf.Ln(8)

	pdf.SetFont("Arial", "B", 10)
	pdf.Cell(100, 7, "Designation")
	pdf.Cell(30, 7, "Prix HT")
	pdf.Cell(30, 7, "TVA 20%")
	pdf.Cell(30, 7, "Total TTC")
	pdf.Ln(7)

	pdf.SetFont("Arial", "", 10)
	pdf.Cell(100, 7, itemName)
	pdf.Cell(30, 7, fmt.Sprintf("%.2f", amountHT))
	pdf.Cell(30, 7, fmt.Sprintf("%.2f", amountTVA))
	pdf.Cell(30, 7, fmt.Sprintf("%.2f", amount))
	pdf.Ln(10)

	// Totaux
	pdf.SetX(120)
	pdf.Cell(30, 7, "Total HT:")
	pdf.Cell(30, 7, fmt.Sprintf("%.2f EUR", amountHT))
	pdf.Ln(7)

	pdf.SetX(120)
	pdf.Cell(30, 7, "TVA (20%):")
	pdf.Cell(30, 7, fmt.Sprintf("%.2f EUR", amountTVA))
	pdf.Ln(7)

	pdf.SetX(120)
	pdf.SetFont("Arial", "B", 11)
	pdf.Cell(30, 8, "TOTAL TTC:")
	pdf.Cell(30, 8, fmt.Sprintf("%.2f EUR", amount))
	pdf.Ln(15)

	// Références
	pdf.SetFont("Arial", "I", 9)
	pdf.SetTextColor(100, 100, 100)
	if metadata["annonce_id"] != "" {
		pdf.Cell(0, 5, "Annonce concernee: n° "+metadata["annonce_id"])
		pdf.Ln(5)
	}
	if metadata["inscription_id"] != "" {
		pdf.Cell(0, 5, "Inscription concernee: n° "+metadata["inscription_id"])
		pdf.Ln(5)
	}
	pdf.Cell(0, 5, "Transaction Stripe: "+metadata["php_session_id"])
	pdf.Ln(10)

	// Pied
	pdf.SetY(-30)
	pdf.SetFont("Arial", "I", 8)
	pdf.Cell(0, 5, "UpcycleConnect - Ce document fait office de facture officielle")
	pdf.Ln(4)
	pdf.Cell(0, 5, fmt.Sprintf("Facture n° %s - Generee le %s", invoiceNumber, time.Now().Format("02/01/2006")))

	err := pdf.OutputFileAndClose(fullPath)
	if err != nil {
		return "", err
	}

	log.Printf("Facture PDF generee: %s", fullPath)
	return filename, nil
}

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

	isPaid := s.PaymentStatus == stripe.CheckoutSessionPaymentStatusPaid
	amount := float64(s.AmountTotal) / 100

	response := map[string]interface{}{
		"paid":       isPaid,
		"amount":     amount,
		"metadata":   s.Metadata,
		"session_id": s.ID,
	}

	log.Printf("VerifyPayment: paid=%v, amount=%.2f", isPaid, amount)

	if isPaid {
		userID, _ := strconv.Atoi(s.Metadata["user_id"])
		itemName := s.Metadata["item_name"]
		annonceID := s.Metadata["annonce_id"]

		if annonceID != "" {
			db.DB.Exec(`UPDATE annonce SET commission_payee = 1, commission_payee_at = NOW() WHERE id_annonce = ?`, annonceID)
			log.Printf("Commission payee pour l'annonce %s", annonceID)
		}

		pdfFilename, err := generatePaymentReceipt(userID, amount, itemName, s.Metadata)
		if err != nil {
			log.Printf("Erreur generation PDF: %v", err)
		} else {
			db.DB.Exec(`INSERT INTO document_genere (id_user, type, titre, file_path, created_at) VALUES (?, 'facture', ?, ?, NOW())`,
				userID, "Facture - "+itemName, pdfFilename)
			log.Printf("Facture PDF enregistree: %s", pdfFilename)
		}

		db.DB.Exec(`INSERT INTO notification (id_user, type, titre, contenu, created_at) VALUES (?, 'paiement', 'Facture disponible', ?, NOW())`,
			userID, fmt.Sprintf("Votre facture a ete generee et est disponible dans votre espace Documents."))
		log.Printf("Notification envoyee")
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}
