<?php
/**
 * Diagnostic complet du projet UpcycleConnect
 * Identifie tous les bugs critiques à corriger
 */
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/ui_helpers.php';
require_once __DIR__ . '/includes/functions/forum_local.php';
require_once __DIR__ . '/includes/functions/forum.php';

$diagnostics = [];

echo "=== DIAGNOSTIC UpcycleConnect ===\n\n";

// 1. Vérifier les tables forum
echo "[1] Forum tables...\n";
$forumReady = (bool)db_safe_exec(static function (PDO $pdo): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE 'forum_%'");
    return $stmt !== false && $stmt->rowCount() > 0;
}, false);
echo "  Forum tables: " . ($forumReady ? "✓ OK" : "✗ MISSING") . "\n";

// 2. Vérifier les fonctions critiques
echo "\n[2] Critical functions...\n";
$functions = [
    'forum_topic_badges' => 'forum topic badges',
    'forum_get_topics' => 'get forum topics',
    'forum_get_categories' => 'get categories',
    'forum_create_topic' => 'create topic',
    'render_empty_state' => 'empty state helper',
    'formatDateFr' => 'french date formatter',
    'e' => 'html escape',
    'nav_role_dashboard_url' => 'role dashboard url',
];

foreach ($functions as $func => $desc) {
    $exists = function_exists($func);
    echo "  $func: " . ($exists ? "✓ OK" : "✗ MISSING") . "\n";
}

// 3. Vérifier la structure des rôles
echo "\n[3] User roles structure...\n";
$roles = db_safe_exec(static function (PDO $pdo): array {
    $stmt = $pdo->query('SELECT DISTINCT id_role FROM utilisateur ORDER BY id_role');
    return (array)($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []);
}, []);
echo "  Roles found: " . implode(", ", $roles) . "\n";
echo "  Expected: 1, 2, 3, 4\n";

// 4. Vérifier les tables essentielles
echo "\n[4] Database tables...\n";
$tables = [
    'utilisateur' => 'Users table',
    'forum_topics' => 'Forum topics',
    'forum_posts' => 'Forum posts',
    'forum_categories' => 'Forum categories',
    'conseil' => 'Conseils/News',
    'session' => 'Events/Sessions',
    'prestation' => 'Prestations/Offers',
    'notifications' => 'Notifications',
];

foreach ($tables as $table => $desc) {
    $exists = (bool)db_safe_exec(static function (PDO $pdo) use ($table): bool {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        return $stmt !== false && $stmt->rowCount() > 0;
    }, false);
    echo "  $table: " . ($exists ? "✓ OK" : "✗ MISSING") . "\n";
}

// 5. Vérifier les inclusions de fichiers problématiques
echo "\n[5] Critical file includes...\n";
$criticalFiles = [
    'forum.php' => 'Main forum page',
    'forum_topic.php' => 'Forum topic page',
    'salarie.php' => 'Employee dashboard',
    'admin_catalog.php' => 'Admin catalog',
    'admin_finance.php' => 'Admin finance',
    'pro_projects.php' => 'Pro projects',
];

foreach ($criticalFiles as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "  $file: ✗ FILE MISSING!\n";
        continue;
    }
    
    // Quick check for common issues
    $content = file_get_contents($path);
    if (strpos($content, 'forum_topic_badges') !== false && strpos($content, 'requires_functions/forum.php') === false && 
        strpos($content, 'includes/functions/forum.php') === false) {
        // Only warn if it calls the function but doesn't include the file directly
        // echo "  $file: ⚠ May be missing forum.php include\n";
    } else {
        echo "  $file: ✓ OK\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
