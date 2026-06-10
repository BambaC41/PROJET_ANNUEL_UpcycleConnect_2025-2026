<?php
/**
 * Vérifieur syntaxe PHP - simple version sans CLI
 */

$baseDir = __DIR__;
$errors = [];
$checked = 0;

function checkFileForSyntax($filePath) {
    $code = file_get_contents($filePath);
    $tokens = @token_get_all($code);
    
    if ($tokens === false) {
        return 'Failed to tokenize';
    }
    
    // Basic checks for common syntax issues
    $braces = 0;
    $brackets = 0;
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            continue;
        }
        
        if ($token === '{') $braces++;
        if ($token === '}') $braces--;
        if ($token === '[') $brackets++;
        if ($token === ']') $brackets--;
    }
    
    if ($braces !== 0) {
        return "Unmatched braces (${braces})";
    }
    if ($brackets !== 0) {
        return "Unmatched brackets (${brackets})";
    }
    
    return null;
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    
    $path = $file->getRealPath();
    if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false) {
        continue;
    }
    
    $checked++;
    $error = checkFileForSyntax($path);
    if ($error) {
        $errors[$path] = $error;
    }
}

echo json_encode([
    'checked' => $checked,
    'errors' => count($errors),
    'details' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
