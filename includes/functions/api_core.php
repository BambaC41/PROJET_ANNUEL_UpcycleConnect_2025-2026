<?php

// Chargement simple du fichier .env pour l'environnement local
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim(trim($parts[1]), '\'"');
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

function callAPI($method, $endpoint, $token = null, $data = null) {
    $baseUrl = getenv('API_BASE_URL') ?: "http://localhost:8080";
    $url = $baseUrl . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
        case 'GET':
        default:
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            break;
    }

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string)$response, true);
    $errorText = '';
    if (is_array($decoded) && !empty($decoded['error'])) {
        $errorText = (string)$decoded['error'];
    } elseif (!empty($curl_error)) {
        $errorText = $curl_error;
    } elseif ($http_code >= 400) {
        $errorText = trim((string)$response);
    }

    return [
        'status' => $http_code,
        'data' => $decoded,
        'raw' => $response,
        'error' => $errorText
    ];
}

function api_upload_file($fieldName, $tmpPath, $originalName, $token = null) {
    if (empty($tmpPath) || !file_exists($tmpPath)) {
        return ['status' => 400, 'data' => ['error' => 'File not found']];
    }

    $baseUrl = getenv('API_BASE_URL') ?: "http://localhost:8080";
    $url = rtrim($baseUrl, '/') . '/upload';

    $ch = curl_init($url);
    $file = new CURLFile($tmpPath, mime_content_type($tmpPath) ?: 'application/octet-stream', $originalName);
    $payload = [$fieldName => $file];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $headers = [];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

function api_get($endpoint, $requireAuth = false) {
    $token = $requireAuth ? (isset($_SESSION['token']) ? $_SESSION['token'] : null) : null;
    return callAPI('GET', $endpoint, $token);
}

function api_post($endpoint, $data = null, $requireAuth = false) {
    $token = $requireAuth ? (isset($_SESSION['token']) ? $_SESSION['token'] : null) : null;
    return callAPI('POST', $endpoint, $token, $data);
}

function api_put($endpoint, $data = null, $requireAuth = false) {
    $token = $requireAuth ? (isset($_SESSION['token']) ? $_SESSION['token'] : null) : null;
    return callAPI('PUT', $endpoint, $token, $data);
}

function api_delete($endpoint, $requireAuth = false) {
    $token = $requireAuth ? (isset($_SESSION['token']) ? $_SESSION['token'] : null) : null;
    return callAPI('DELETE', $endpoint, $token);
}
// Récupérer le vrai chiffre d'affaires Stripe
function api_get_stripe_balance() {
    $result = api_get('/admin/stripe/balance', true);
    
    // Debug - afficher ce que retourne l'API (à enlever en production)
    if (($result['status'] ?? 0) !== 200) {
        error_log("Stripe API error: " . print_r($result, true));
    }
    
    return $result;
}
?>