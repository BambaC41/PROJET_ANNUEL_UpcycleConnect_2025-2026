<?php
/**
 * Vérification exhaustive des includes manquants et functions undefined
 */

// List of all PHP pages that are user-facing and critical
$criticalPages = [
    'forum.php',
    'forum_topic.php',
    'salarie.php',
    'salarie_forum.php',
    'salarie_events.php',
    'salarie_conseils.php',
    'admin.php',
    'admin_forum.php',
    'admin_forum_reports.php',
    'admin_forum_categories.php',
    'admin_catalog.php',
    'admin_finance.php',
    'admin_events.php',
    'admin_users.php',
    'admin_conseils.php',
    'pro_projects.php',
    'particulier_conseils.php',
    'notifications.php',
    'conseil_detail.php',
];

// Critical functions that need to always be available
$criticalFunctions = [
    'e' => 'htmlspecialchars wrapper',
    'render_empty_state' => 'empty state renderer',
    'formatDateFr' => 'french date formatter',
    'forum_topic_badges' => 'forum topic badges',
    'forum_get_topics' => 'get forum topics',
    'forum_create_topic' => 'create topic',
];

// Check which functions are needed in which pages
$requirements = [
    'forum.php' => ['forum_topic_badges', 'render_empty_state', 'formatDateFr', 'e', 'nav_role_dashboard_url'],
    'forum_topic.php' => ['forum_topic_badges', 'formatDateFr', 'e', 'nl2br'],
    'salarie.php' => ['formatDateFr', 'e'],
    'admin_forum.php' => ['forum_topic_badges', 'e', 'render_empty_state', 'formatDateFr'],
    'notifications.php' => ['e', 'formatDateFr'],
];

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Vérification des includes</title>
    <style>
        body { font-family: monospace; background: #f0f0f0; padding: 20px; }
        .test { background: #fff; padding: 10px; margin: 5px 0; border-left: 4px solid #2196f3; }
        .test.pass { border-left-color: #16a34a; background: #eafae6; }
        .test.fail { border-left-color: #dc2626; background: #fee2e2; }
        .warn { color: #f59e0b; }
    </style>
</head>
<body>
<h1>Vérification des includes/functions</h1>\n";

$baseDir = __DIR__;
$errors = [];
$warnings = [];

// Check critical functions are loadable
echo "<h2>1. Functions disponibles:</h2>\n";
$funcsToCheck = array_keys($criticalFunctions);

// Manually check for function definitions
foreach ($funcsToCheck as $func) {
    $found = false;
    
    // Check ui_helpers.php
    if (file_exists($baseDir . '/includes/ui_helpers.php')) {
        $content = file_get_contents($baseDir . '/includes/ui_helpers.php');
        if (strpos($content, "function $func") !== false) {
            $found = true;
        }
    }
    
    // Check view_context.php
    if (!$found && file_exists($baseDir . '/includes/functions/view_context.php')) {
        $content = file_get_contents($baseDir . '/includes/functions/view_context.php');
        if (strpos($content, "function $func") !== false) {
            $found = true;
        }
    }
    
    // Check forum_local.php
    if (!$found && file_exists($baseDir . '/includes/functions/forum_local.php')) {
        $content = file_get_contents($baseDir . '/includes/functions/forum_local.php');
        if (strpos($content, "function $func") !== false) {
            $found = true;
        }
    }
    
    // Check forum.php
    if (!$found && file_exists($baseDir . '/includes/functions/forum.php')) {
        $content = file_get_contents($baseDir . '/includes/functions/forum.php');
        if (strpos($content, "function $func") !== false) {
            $found = true;
        }
    }
    
    $status = $found ? '✓' : '✗';
    $class = $found ? 'pass' : 'fail';
    echo "<div class='test $class'>$status $func - " . ($found ? 'FOUND' : 'NOT FOUND') . "</div>\n";
    
    if (!$found) {
        $errors[] = "Function $func not found in any includes!";
    }
}

echo "<h2>2. Pages critiques et leurs includes:</h2>\n";
foreach ($criticalPages as $page) {
    $path = $baseDir . '/' . $page;
    if (!file_exists($path)) {
        echo "<div class='test fail'>✗ $page NOT FOUND</div>\n";
        $errors[] = "Page $page does not exist";
        continue;
    }
    
    $content = file_get_contents($path);
    $hasForumPhp = strpos($content, "includes/functions/forum.php") !== false;
    $hasUIHelpers = strpos($content, "includes/ui_helpers.php") !== false;
    $hasViewContext = strpos($content, "includes/functions/view_context.php") !== false;
    
    if (strpos($page, 'forum') !== false && !$hasForumPhp) {
        echo "<div class='test warn'>⚠ $page - missing forum.php include!</div>\n";
        $warnings[] = "$page is missing forum.php include";
    }
    
    if ((strpos($page, 'admin_') !== false || strpos($page, 'salarie') !== false) && !$hasUIHelpers && !$hasViewContext) {
        echo "<div class='test warn'>⚠ $page - missing ui_helpers or view_context!</div>\n";
    }
    
    echo "<div class='test pass'>✓ $page exists</div>\n";
}

echo "<h2>3. Summary:</h2>\n";
echo "<p>Errors: " . count($errors) . "</p>\n";
echo "<p>Warnings: " . count($warnings) . "</p>\n";

if (!empty($errors)) {
    echo "<h3>Errors to fix:</h3>\n<ul>\n";
    foreach ($errors as $e) {
        echo "<li>$e</li>\n";
    }
    echo "</ul>\n";
}

if (!empty($warnings)) {
    echo "<h3>Warnings:</h3>\n<ul>\n";
    foreach ($warnings as $w) {
        echo "<li>$w</li>\n";
    }
    echo "</ul>\n";
}

echo "\n</body>\n</html>";
