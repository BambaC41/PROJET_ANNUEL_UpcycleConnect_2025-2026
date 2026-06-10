#!/usr/bin/env php
<?php
/**
 * Test CLI - Vérifie que formatDateFr() n'est plus redéclarée
 */
ob_start();

// Désactive les headers
define('CLI_MODE', true);

// Création d'une session simulée
$_SESSION = [];
$_SESSION['token'] = 'test_token_12345';
$_SESSION['role_id'] = 3; // Particulier

try {
    echo "[1/4] Chargement de view_context.php...\n";
    require_once __DIR__ . '/includes/functions/view_context.php';
    if (!function_exists('formatDateFr')) {
        throw new Exception('formatDateFr() non défini après view_context.php');
    }
    echo "      ✓ OK\n";
    
    echo "[2/4] Vérification que formatDateFr() existe...\n";
    $test1 = formatDateFr('2025-05-12');
    echo "      ✓ formatDateFr() = '$test1'\n";
    
    echo "[3/4] Chargement de particulier_bootstrap.php (risque de redéclaration)...\n";
    require_once __DIR__ . '/includes/particulier_bootstrap.php';
    echo "      ✓ OK - Aucune redéclaration!\n";
    
    echo "[4/4] Vérification des fonctions...\n";
    $test2 = formatDateFr('2025-05-12 14:30:00');
    $escaped = e('<script>');
    echo "      ✓ formatDateFr() = '$test2'\n";
    echo "      ✓ e() fonctionne\n";
    
    echo "\n✅ SUCCÈS - Les corrections fonctionnent!\n";
    echo "   - formatDateFr() n'est pas redéclarée\n";
    echo "   - e() n'est pas redéclarée\n";
    
    ob_end_clean();
    exit(0);
    
} catch (Throwable $e) {
    ob_end_clean();
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . "\n";
    echo "   Ligne: " . $e->getLine() . "\n";
    exit(1);
}
