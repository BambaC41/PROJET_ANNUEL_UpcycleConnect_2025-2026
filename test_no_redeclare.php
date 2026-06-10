<?php
// Teste si les fonctions ne sont pas redéclarées
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (strpos($errstr, 'Cannot redeclare') !== false) {
        die("FATAL: $errstr in $errfile:$errline");
    }
    return false;
});

// Charge view_context.php
$res = @include_once(__DIR__ . '/includes/functions/view_context.php');
if ($res === false) {
    echo "Erreur: Impossible de charger view_context.php\n";
    exit(1);
}
echo "✓ view_context.php chargé\n";

// Simule une session particulier
$_SESSION['token'] = 'test_token';
$_SESSION['role_id'] = 3;

// Maintenant teste le chargement de particulier_bootstrap
$res = @include_once(__DIR__ . '/includes/particulier_bootstrap.php');
if ($res === false) {
    echo "Erreur: Impossible de charger particulier_bootstrap.php\n";
    exit(1);
}
echo "✓ particulier_bootstrap.php chargé SANS redéclaration!\n";

// Teste les fonctions
if (function_exists('formatDateFr')) {
    $date = formatDateFr('2025-05-12 12:30:00');
    echo "✓ formatDateFr() fonctionne: $date\n";
} else {
    echo "✗ formatDateFr() n'existe pas!\n";
    exit(1);
}

if (function_exists('e')) {
    $test = e('<test>');
    echo "✓ e() fonctionne\n";
} else {
    echo "✗ e() n'existe pas!\n";
    exit(1);
}

echo "\n✅ SUCCÈS - Aucune redéclaration détectée!\n";
