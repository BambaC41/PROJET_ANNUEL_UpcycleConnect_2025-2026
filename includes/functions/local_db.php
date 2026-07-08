<?php


require_once __DIR__ . '/api_core.php';

function db_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? (string)$value : $default;
}

function db_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = db_env('DB_HOST', '127.0.0.1');
    $port = db_env('DB_PORT', '3306');
    $name = db_env('DB_NAME', 'upcycleconnect');
    $user = db_env('DB_USER', 'root');
    $pass = db_env('DB_PASSWORD', '');
    $charset = db_env('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage(), 0);
        throw new RuntimeException(
            'Erreur de connexion à la base de données. Vérifiez les variables d\'environnement DB_HOST, DB_USER, DB_PASSWORD, DB_NAME.'
        );
    }

    return $pdo;
}

function db_safe_exec(callable $callback, mixed $fallback = null): mixed
{
    try {
        return $callback(db_pdo());
    } catch (Throwable $e) {
        return $fallback;
    }
}

function session_ensure_user_id(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        return $uid;
    }
    if (empty($_SESSION['token'])) {
        return 0;
    }
    require_once __DIR__ . '/api_core.php';
    $me = callAPI('GET', '/me', $_SESSION['token']);
    if (($me['status'] ?? 0) === 200 && is_array($me['data'] ?? null)) {
        $d = $me['data'];
        if (!empty($d['id_user'])) {
            $_SESSION['user_id'] = (int)$d['id_user'];
        }
        if (isset($d['id_role'])) {
            $_SESSION['role_id'] = (int)$d['id_role'];
        }
        return (int)($_SESSION['user_id'] ?? 0);
    }
    return 0;
}


function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'upcycleconnect';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
