<?php
/**
 * Script de vérification syntaxe PHP pour tous les fichiers
 */

$errors = [];
$checked = 0;

function checkPhpFile($file) {
    $output = [];
    $returnCode = 0;
    exec(sprintf('php -l %s 2>&1', escapeshellarg($file)), $output, $returnCode);
    $result = implode("\n", $output);
    
    if ($returnCode !== 0 || strpos($result, 'Parse error') !== false || strpos($result, 'Fatal error') !== false) {
        return $result;
    }
    return null;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false) {
            continue;
        }
        
        $checked++;
        $error = checkPhpFile($path);
        if ($error) {
            $errors[$path] = $error;
        }
    }
}

echo "✓ Checked: $checked files\n";
if (empty($errors)) {
    echo "✓ No PHP syntax errors found!\n";
} else {
    echo "\n❌ Found " . count($errors) . " errors:\n";
    foreach ($errors as $file => $error) {
        echo "\n$file:\n";
        echo "  $error\n";
    }
    exit(1);
}
