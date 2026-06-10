<?php
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$errors = [];

foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $path = $file->getPathname();
        $output = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
        
        if (strpos($output, 'No syntax errors') === false) {
            $errors[] = $output;
        }
    }
}

if (empty($errors)) {
    echo "✓ Aucune erreur de syntaxe PHP détectée.\n";
} else {
    echo count($errors) . " erreur(s) de syntaxe trouvée(s):\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
}
