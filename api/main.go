package main

import (
	"api/app"
	"api/db"
	"log"
	"net/http"
)

func main() {
	err := db.InitDB()
	if err != nil {
		log.Fatal("DB connection error: ", err)
	}

	http.HandleFunc("/register", app.Register)
	http.HandleFunc("/login", app.Login)
	http.HandleFunc("/users", app.GetUsers)
	http.HandleFunc("/users/", app.GetUserByID)
	http.HandleFunc("/me", app.GetMe)
	log.Println("Server running on http://localhost:8080")
	err = http.ListenAndServe(":8080", nil)
	if err != nil {
		log.Fatal(err)
	}
}
