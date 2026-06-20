package app

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

const maxUploadSize = 8 << 20

func UploadHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	if _, ok := requireAuth(w, r); !ok {
		return
	}

	if err := r.ParseMultipartForm(maxUploadSize); err != nil {
		http.Error(w, "Invalid multipart form", http.StatusBadRequest)
		return
	}

	file, header, err := r.FormFile("file")
	if err != nil {
		http.Error(w, "Missing file", http.StatusBadRequest)
		return
	}
	defer file.Close()

	ext := strings.ToLower(filepath.Ext(header.Filename))
	switch ext {
	case ".jpg", ".jpeg", ".png", ".webp", ".gif":
	default:
		http.Error(w, "Unsupported file type", http.StatusBadRequest)
		return
	}

	if err := os.MkdirAll("uploads", 0o755); err != nil {
		http.Error(w, "Cannot create upload directory", http.StatusInternalServerError)
		return
	}

	filename := strings.ReplaceAll(time.Now().Format("20060102_150405.000000000"), ".", "") + ext
	path := filepath.Join("uploads", filename)

	dst, err := os.Create(path)
	if err != nil {
		http.Error(w, "Cannot save file", http.StatusInternalServerError)
		return
	}
	defer dst.Close()

	if _, err := dst.ReadFrom(file); err != nil {
		http.Error(w, "Cannot write file", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"file_url": "/uploads/" + filename,
	})
}

func UploadChatFileHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	claims, ok := requireAuth(w, r)
	if !ok {
		return
	}

	if err := r.ParseMultipartForm(50 << 20); err != nil {
		http.Error(w, "Invalid multipart form", http.StatusBadRequest)
		return
	}

	file, header, err := r.FormFile("file")
	if err != nil {
		http.Error(w, "Missing file", http.StatusBadRequest)
		return
	}
	defer file.Close()

	ext := strings.ToLower(filepath.Ext(header.Filename))
	allowedExt := map[string]bool{
		".jpg": true, ".jpeg": true, ".png": true, ".gif": true, ".webp": true,
		".pdf": true, ".doc": true, ".docx": true, ".xls": true, ".xlsx": true,
		".txt": true, ".zip": true, ".rar": true,
	}
	if !allowedExt[ext] {
		http.Error(w, "File type not allowed", http.StatusBadRequest)
		return
	}

	if err := os.MkdirAll("uploads/chat", 0o755); err != nil {
		http.Error(w, "Cannot create upload directory", http.StatusInternalServerError)
		return
	}

	filename := fmt.Sprintf("%d_%s%s", claims.UserID, time.Now().Format("20060102_150405.000000000"), ext)
	path := filepath.Join("uploads/chat", filename)

	dst, err := os.Create(path)
	if err != nil {
		http.Error(w, "Cannot save file", http.StatusInternalServerError)
		return
	}
	defer dst.Close()

	if _, err := dst.ReadFrom(file); err != nil {
		http.Error(w, "Cannot write file", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"file_path": "/uploads/chat/" + filename,
		"file_name": header.Filename,
	})
}
