<?php
$files_to_check = [
    'includes/functions/view_context.php',
    'includes/particulier_bootstrap.php',
    'includes/pro_bootstrap.php',
    'includes/admin_bootstrap.php',
    'includes/employee_bootstrap.php',
];

$base = __DIR__;
$errors = [];

foreach ($files_to_check as $file) {
    $path = $base . '/' . $file;
    if (!file_exists($path)) {
        echo "✗ Fichier non trouvé: $path\n";
        continue;
    }
    
    // Vérification syntaxe PHP
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return);
    
    $result = implode("\n", $output);
    if ($return !== 0 || strpos($result, 'No syntax errors') === false) {
        $errors[] = "$file: $result";
        echo "✗ $file: ERREUR DE SYNTAXE\n";
    } else {
        echo "✓ $file: OK\n";
    }
}

if (empty($errors)) {
    echo "\n✅ Tous les fichiers ont une syntaxe valide!\n";
} else {
    echo "\n❌ Erreurs trouvées:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
    exit(1);
}
