<?php
/**
 * Quick smoke test de pages critiques
 */

// Vérifier que les fonctions utilisées partout existent
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once __DIR__ . '/includes/functions/forum.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$checks = [];

// Test 1: forum_topic_badges
$checks['forum_topic_badges'] = function_exists('forum_topic_badges') ? '✓' : '✗';

// Test 2: render_empty_state
$checks['render_empty_state'] = function_exists('render_empty_state') ? '✓' : '✗';

// Test 3: formatDateFr
$checks['formatDateFr'] = function_exists('formatDateFr') ? '✓' : '✗';

// Test 4: e() function (HTML escape)
$checks['e'] = function_exists('e') ? '✓' : '✗';

// Test 5: nav_role_dashboard_url
$checks['nav_role_dashboard_url'] = function_exists('nav_role_dashboard_url') ? '✓' : '✗';

// Test 6: forum_get_topics
$checks['forum_get_topics'] = function_exists('forum_get_topics') ? '✓' : '✗';

// Test 7: forum_get_categories
$checks['forum_get_categories'] = function_exists('forum_get_categories') ? '✓' : '✗';

echo "Function Availability Check:\n";
echo str_repeat("=", 50) . "\n";
foreach ($checks as $name => $result) {
    printf("%-30s %s\n", $name, $result);
}
echo str_repeat("=", 50) . "\n";

$failed = array_filter($checks, fn($v) => $v === '✗');
if (empty($failed)) {
    echo "✓ All critical functions available!\n";
} else {
    echo "✗ Missing functions: " . implode(", ", array_keys($failed)) . "\n";
}
