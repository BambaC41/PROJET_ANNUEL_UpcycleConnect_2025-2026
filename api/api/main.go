package main

import (
	"api/app"
	"api/db"
	"log"
	"net/http"
)

func main() {
	if err := db.InitDB(); err != nil {
		log.Fatal("DB connection error: ", err)
	}
	http.HandleFunc("/register", app.RegisterHandler)
	http.HandleFunc("/login", app.LoginHandler)
	http.HandleFunc("/me", app.MeHandler)
	http.HandleFunc("/profile/", app.PublicProfileHandler)
	http.HandleFunc("/me/update", app.UpdateMeHandler)
	http.HandleFunc("/users", app.UsersHandler)
	http.HandleFunc("/users/", app.UsersRouter)
	http.HandleFunc("/pros/pending", app.PendingProsHandler)
	http.HandleFunc("/admin/users", app.AdminCreateUserHandler)
	http.HandleFunc("/categories", app.CategoriesHandler)
	http.HandleFunc("/categories/", app.CategoryByIDHandler)
	http.HandleFunc("/prestations", app.PrestationsHandler)
	http.HandleFunc("/prestations/", app.PrestationByIDHandler)
	http.HandleFunc("/events", app.EventsHandler)
	http.HandleFunc("/events/", app.EventByIDHandler)
	http.HandleFunc("/me/events", app.MyEventsHandler)
	http.HandleFunc("/me/conseils", app.MyConseilsHandler)
	http.HandleFunc("/me/inscriptions", app.MyInscriptionsHandler)
	http.HandleFunc("/inscriptions/", app.InscriptionByIDHandler)
	http.HandleFunc("/me/paiements", app.MyPaiementsHandler)
	http.HandleFunc("/paiements", app.PaiementsHandler)
	http.HandleFunc("/me/score", app.MyScoreHandler)
	http.HandleFunc("/conseils", app.ConseilsHandler)
	http.HandleFunc("/conseils/", app.ConseilByIDHandler)
	http.HandleFunc("/admin/conseils", app.AdminConseilsHandler)
	http.HandleFunc("/forum/", app.ForumRouter)
	http.HandleFunc("/admin/forum/", app.AdminForumRouter)
	http.HandleFunc("/health", app.HealthHandler)
	http.HandleFunc("/profiles", app.PublicProfilesHandler)
	http.HandleFunc("/annonces", app.AnnoncesHandler)
	http.HandleFunc("/annonces/", app.AnnonceByIDHandler)
	http.HandleFunc("/me/annonces", app.MyAnnoncesHandler)
	http.HandleFunc("/admin/annonces/pending", app.AdminPendingAnnoncesHandler)
	http.HandleFunc("/conteneurs", app.ConteneursHandler)
	http.HandleFunc("/conteneurs/", app.ConteneurByIDHandler)
	http.HandleFunc("/demandes-depot", app.DemandesDepotHandler)
	http.HandleFunc("/demandes-depot/", app.DemandeDepotByIDHandler)
	http.HandleFunc("/me/demandes-depot", app.MyDemandesDepotHandler)
	http.HandleFunc("/upload", app.UploadHandler)
	http.HandleFunc("/create-checkout-session", app.CreateCheckoutSession)
	http.HandleFunc("/verify-payment", app.VerifyPayment)
	http.Handle("/uploads/", http.StripPrefix("/uploads/", http.FileServer(http.Dir("uploads"))))
	log.Println("Server running on http://localhost:8080")
	if err := http.ListenAndServe(":8080", nil); err != nil {
		log.Fatal(err)
	}
}
