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
	http.HandleFunc("/users/", app.UserByIDHandler)
	http.HandleFunc("/admin/users", app.AdminCreateUserHandler)
	http.HandleFunc("/categories", app.CategoriesHandler)
	http.HandleFunc("/categories/", app.CategoryByIDHandler)
	http.HandleFunc("/prestations", app.PrestationsHandler)
	http.HandleFunc("/prestations/", app.PrestationByIDHandler)
	http.HandleFunc("/events", app.EventsHandler)
	http.HandleFunc("/events/", app.EventByIDHandler)
	log.Println("Server running on http://localhost:8080")
	if err := http.ListenAndServe(":8080", nil); err != nil {
		log.Fatal(err)
	}
}
