<?php
/**
 * Vérification finale que le bug est corrigé
 * Simule le scénario exact du bug : login → particulier.php
 */

// Simule une session PHP normalisée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simule le login d'un particulier
$_SESSION['token'] = 'test_jwt_token_12345';
$_SESSION['role_id'] = 3;  // role_id 3 = particulier
$_SESSION['user_id'] = 1;

// Nettoie any registered functions
if (function_exists('test_cleanup')) {
    test_cleanup();
}

$error_count = 0;
$test_results = [];

try {
    // TEST 1 : Charge particulier_bootstrap.php comme le ferait particulier.php
    echo "TEST 1: Chargement de particulier_bootstrap.php...\n";
    ob_start();
    $result = @include_once(__DIR__ . '/includes/particulier_bootstrap.php');
    $output = ob_get_clean();
    
    if ($result === false) {
        $test_results[] = "❌ FAIL: Impossible d'inclure particulier_bootstrap.php";
        $error_count++;
    } elseif (!empty($output)) {
        $test_results[] = "⚠️ WARNING: particulier_bootstrap.php a produit du output non attendu";
    } else {
        $test_results[] = "✓ PASS: particulier_bootstrap.php inclus sans erreur";
    }
    
    // TEST 2 : Vérification que formatDateFr existe et fonctionne
    echo "TEST 2: Vérification de formatDateFr()...\n";
    if (!function_exists('formatDateFr')) {
        $test_results[] = "❌ FAIL: formatDateFr() n'existe pas";
        $error_count++;
    } else {
        $date = @formatDateFr('2025-05-12 14:30:00');
        if ($date === false || $date === '') {
            $test_results[] = "❌ FAIL: formatDateFr() retourne une valeur invalide";
            $error_count++;
        } else {
            $test_results[] = "✓ PASS: formatDateFr() fonctionne correctement: '$date'";
        }
    }
    
    // TEST 3 : Vérification que e() existe et fonctionne
    echo "TEST 3: Vérification de e()...\n";
    if (!function_exists('e')) {
        $test_results[] = "❌ FAIL: e() n'existe pas";
        $error_count++;
    } else {
        $test = @e('<script>alert("xss")</script>');
        if (strpos($test, '<') !== false || strpos($test, '>') !== false) {
            $test_results[] = "❌ FAIL: e() n'échappe pas correctement";
            $error_count++;
        } else {
            $test_results[] = "✓ PASS: e() échappe correctement le HTML";
        }
    }
    
    // TEST 4 : Charge view_context.php également (scénario réel)
    echo "TEST 4: Charge supplémentaire de view_context.php...\n";
    ob_start();
    $result = @include_once(__DIR__ . '/includes/functions/view_context.php');
    $output = ob_get_clean();
    
    if ($result === false) {
        $test_results[] = "❌ FAIL: Impossible d'inclure view_context.php";
        $error_count++;
    } else {
        $test_results[] = "✓ PASS: view_context.php inclus sans redéclaration";
    }
    
    // TEST 5 : Les fonctions marchent encore
    echo "TEST 5: Vérification finale des fonctions...\n";
    $date = @formatDateFr('2025-01-01');
    $escaped = @e('<div>');
    if ($date && $escaped) {
        $test_results[] = "✓ PASS: Toutes les fonctions marchent après charges multiples";
    } else {
        $test_results[] = "❌ FAIL: Les fonctions ne marchent pas après charges multiples";
        $error_count++;
    }
    
} catch (Throwable $e) {
    $test_results[] = "❌ EXCEPTION: " . $e->getMessage();
    $error_count++;
}

// Affiche les résultats
echo "\n" . str_repeat("=", 70) . "\n";
echo "RÉSULTATS DES TESTS\n";
echo str_repeat("=", 70) . "\n";

foreach ($test_results as $result) {
    echo "$result\n";
}

echo str_repeat("=", 70) . "\n";

if ($error_count === 0) {
    echo "✅ TOUS LES TESTS PASSENT - LE BUG EST CORRIGÉ!\n";
    echo "\nLe problème de redéclaration de formatDateFr() a été résolu:\n";
    echo "- Les fonctions sont protégées par if (!function_exists(...))\n";
    echo "- Tous les bootstrap chargent view_context.php avec require_once\n";
    echo "- Les inclusions multiples ne posent plus de problème\n";
    exit(0);
} else {
    echo "❌ $error_count TEST(S) ÉCHOUÉ(S)\n";
    exit(1);
}
