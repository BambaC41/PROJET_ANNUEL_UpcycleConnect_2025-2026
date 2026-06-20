package app

import (
	"api/db"
	"encoding/json"
	"fmt"
	"io"
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
	log.Println("Stripe key loaded")
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
		Type          string `json:"type"`
		Formule       string `json:"formule"`
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
			"type":           payload.Type,
			"formule":        payload.Formule,
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

	pdf.SetFont("Arial", "B", 18)
	pdf.SetTextColor(0, 0, 0)
	pdf.Cell(0, 10, "FACTURE")
	pdf.Ln(12)

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
		inscriptionID := s.Metadata["inscription_id"] //

		if annonceID != "" {
			_, err := db.DB.Exec(`UPDATE annonce SET commission_payee = 1, commission_payee_at = NOW() WHERE id_annonce = ?`, annonceID)
			if err != nil {
				log.Printf("Erreur mise à jour commission annonce %s: %v", annonceID, err)
			} else {
				log.Printf("Commission payée pour l'annonce %s", annonceID)
			}
		}

		if inscriptionID != "" {
			_, err := db.DB.Exec(`
				INSERT INTO paiement (provider, payment_ref, montant, devise, statut, paid_at, id_inscription, user_id)
				VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
			`, "stripe", s.ID, amount, "EUR", "paid", inscriptionID, userID)
			if err != nil {
				log.Printf("Erreur insertion paiement inscription %s: %v", inscriptionID, err)
			} else {
				log.Printf("Paiement enregistré pour l'inscription %s", inscriptionID)
			}
		}

		pdfFilename, err := generatePaymentReceipt(userID, amount, itemName, s.Metadata)
		if err != nil {
			log.Printf("Erreur generation PDF: %v", err)
		} else {
			_, err = db.DB.Exec(`INSERT INTO document_genere (id_user, type, titre, file_path, created_at) VALUES (?, 'facture', ?, ?, NOW())`,
				userID, "Facture - "+itemName, pdfFilename)
			if err != nil {
				log.Printf("Erreur insertion document_genere: %v", err)
			} else {
				log.Printf("Facture PDF enregistrée: %s", pdfFilename)
			}
		}

		_, err = db.DB.Exec(`INSERT INTO notification (id_user, type, titre, contenu, created_at) VALUES (?, 'paiement', 'Facture disponible', ?, NOW())`,
			userID, fmt.Sprintf("Votre facture a été générée et est disponible dans votre espace Documents."))
		if err != nil {
			log.Printf("Erreur insertion notification: %v", err)
		} else {
			log.Printf("Notification envoyée")
		}
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}
func callStripeAPI(endpoint string, method string) (map[string]interface{}, error) {
	url := "https://api.stripe.com/v1/" + endpoint

	req, err := http.NewRequest(method, url, nil)
	if err != nil {
		return nil, err
	}
	req.SetBasicAuth(stripe.Key, "")
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	var result map[string]interface{}
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, err
	}

	return result, nil
}

func GetStripeBalance() (map[string]interface{}, error) {
	balanceData, err := callStripeAPI("balance", "GET")
	if err != nil {
		return nil, err
	}

	chargesData, err := callStripeAPI("charges?limit=50", "GET")
	if err != nil {
		return nil, err
	}

	availableAmount := 0.0
	pendingAmount := 0.0
	currency := "eur"

	if balance, ok := balanceData["available"].([]interface{}); ok && len(balance) > 0 {
		if item, ok := balance[0].(map[string]interface{}); ok {
			if amount, ok := item["amount"].(float64); ok {
				availableAmount = amount / 100
			}
			if cur, ok := item["currency"].(string); ok {
				currency = cur
			}
		}
	}
	if pending, ok := balanceData["pending"].([]interface{}); ok && len(pending) > 0 {
		if item, ok := pending[0].(map[string]interface{}); ok {
			if amount, ok := item["amount"].(float64); ok {
				pendingAmount = amount / 100
			}
		}
	}

	var transactions []map[string]interface{}
	totalRevenue := 0.0

	if data, ok := chargesData["data"].([]interface{}); ok {
		for _, charge := range data {
			if ch, ok := charge.(map[string]interface{}); ok {
				if paid, ok := ch["paid"].(bool); ok && paid {
					amount := 0.0
					if amt, ok := ch["amount"].(float64); ok {
						amount = amt / 100
						totalRevenue += amount
					}
					created := int64(0)
					if cr, ok := ch["created"].(float64); ok {
						created = int64(cr)
					}
					status := "paid"
					if st, ok := ch["status"].(string); ok {
						status = st
					}
					transactions = append(transactions, map[string]interface{}{
						"id":      ch["id"],
						"amount":  amount,
						"status":  status,
						"type":    "charge",
						"created": time.Unix(created, 0).Format("2006-01-02 15:04:05"),
					})
				}
			}
		}
	}

	return map[string]interface{}{
		"balance_available": availableAmount,
		"balance_pending":   pendingAmount,
		"currency":          currency,
		"total_revenue":     totalRevenue,
		"transactions":      transactions,
		"transaction_count": len(transactions),
	}, nil
}

func GetAllStripePaymentsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	if _, ok := requireAdmin(w, r); !ok {
		return
	}

	data, err := GetStripeBalance()
	if err != nil {
		http.Error(w, "Erreur Stripe: "+err.Error(), http.StatusInternalServerError)
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"data":    data,
	})
}
