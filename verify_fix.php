<?php
// Test de vérification simple pour voir si formatDateFr est bien protégée
session_start();
$_SESSION['token'] = 'test_token';
$_SESSION['role_id'] = 3; // Particulier

// Simulation du load des deux fichiers problématiques
try {
    // Étape 1 : Include view_context.php
    require_once __DIR__ . '/includes/functions/view_context.php';
    echo "✓ view_context.php chargé\n";
    
    // Étape 2 : Include particulier_bootstrap.php qui devrait maintenant réutiliser formatDateFr
    require_once __DIR__ . '/includes/particulier_bootstrap.php';
    echo "✓ particulier_bootstrap.php chargé\n";
    
    // Étape 3 : Test la fonction formatDateFr
    $result = formatDateFr('2025-05-12 12:30:00');
    echo "✓ formatDateFr() fonctionne : $result\n";
    
    // Étape 4 : Test la fonction e()
    $escaped = e('<script>alert("test")</script>');
    echo "✓ e() fonctionne : " . strlen($escaped) . " chars\n";
    
    echo "\n✓ SUCCÈS - Aucune redéclaration détectée!\n";
} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
