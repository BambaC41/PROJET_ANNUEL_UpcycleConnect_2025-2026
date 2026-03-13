<?php

/**
 * Fonction générique pour appeler l'API UpcycleConnect
 */
function callAPI($endpoint, $token = null) {
    // Configuration centralisée de l'URL
    $baseUrl = "http://localhost:8081"; 
    $url = $baseUrl . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    }
    return null; // Retourne null en cas d'erreur
}
?>