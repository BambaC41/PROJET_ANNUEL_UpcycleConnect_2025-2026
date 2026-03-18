package app

import (
	"api/db"
	"api/model"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
)

func CategoriesHandler(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		categories, err := db.GetCategories()
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(categories)
	case http.MethodPost:
		if _, ok := requireAdminOrStaff(w, r); !ok {
			return
		}
		var c model.Category
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if err := db.CreateCategory(c); err != nil {
			http.Error(w, "Insert error", http.StatusInternalServerError)
			return
		}
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(map[string]string{"message": "category created"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}
func CategoryByIDHandler(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/categories/")
	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, "Invalid ID", http.StatusBadRequest)
		return
	}
	switch r.Method {
	case http.MethodGet:
		category, err := db.GetCategoryByID(id)
		if err != nil {
			http.Error(w, "Database error", http.StatusInternalServerError)
			return

		}
		if category == nil {
			http.Error(w, "Category not found", http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(category)
	case http.MethodPut:
		if _, ok := requireAdminOrStaff(w, r); !ok {
			return
		}
		var c model.Category
		if err := json.NewDecoder(r.Body).Decode(&c); err != nil {
			http.Error(w, "Invalid JSON", http.StatusBadRequest)
			return
		}
		if err := db.UpdateCategory(id, c); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "category updated"})
	case http.MethodDelete:
		if _, ok := requireAdminOrStaff(w, r); !ok {
			return
		}
		if err := db.DeleteCategory(id); err != nil {
			http.Error(w, err.Error(), http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]string{"message": "category deleted"})
	default:
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
	}
}
