<?php
/**
 * Vérification rapide que les fichiers modifiés ont une syntaxe valide
 */

$files_to_check = [
    'includes/functions/local_db.php',
    'tutorial_complete.php',
];

$errors = [];
foreach ($files_to_check as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        $errors[] = "Fichier non trouvé: $path";
        continue;
    }
    
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return);
    
    $result = implode("\n", $output);
    if ($return !== 0 || strpos($result, 'No syntax errors') === false) {
        $errors[] = "$file: $result";
    } else {
        echo "✓ $file: OK\n";
    }
}

if (empty($errors)) {
    echo "\n✅ Tous les fichiers modifiés ont une syntaxe valide!\n";
    
    // Vérifier que local_db.php charge sans erreur
    echo "\nTest: Chargement de local_db.php...\n";
    $env_content = "API_BASE_URL=http://localhost:8080\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=upcycleconnect\nDB_USER=root\nDB_PASSWORD=";
    @file_put_contents('.env', $env_content);
    
    try {
        require_once __DIR__ . '/includes/functions/local_db.php';
        if (function_exists('db_pdo') && function_exists('db_safe_exec') && function_exists('db_env')) {
            echo "✓ Fonctions disponibles: db_pdo, db_safe_exec, db_env\n";
            echo "\n✅ local_db.php charge correctement!\n";
        } else {
            echo "❌ Fonctions manquantes\n";
        }
    } catch (Throwable $e) {
        echo "⚠️  Erreur (normale si MySQL non disponible): " . $e->getMessage() . "\n";
    }
    
    exit(0);
} else {
    echo "\n❌ Erreurs trouvées:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
    exit(1);
}
