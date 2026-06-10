<?php
/**
 * Page de test pour vérifier la santé du projet
 * Cette page fait un test complet sans accès à la session
 */

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Tests de santé UpcycleConnect</title>
    <style>
        body { font-family: sans-serif; background: #f1f5f9; padding: 20px; }
        .test { background: #fff; padding: 12px; margin: 8px 0; border-radius: 8px; border-left: 4px solid #2196f3; }
        .test.pass { border-left-color: #16a34a; }
        .test.fail { border-left-color: #dc2626; background: #fef2f2; }
        .test.warn { border-left-color: #f59e0b; background: #fffbeb; }
        .test strong { display: block; margin-bottom: 4px; }
        .test code { display: block; background: #f1f5f9; padding: 4px; border-radius: 4px; font-size: 12px; margin-top: 4px; color: #64748b; }
    </style>
</head>
<body>
<h1>Tests de santé - UpcycleConnect</h1>\n";

$errors = [];
$warnings = [];
$passes = [];

// TEST 1: Inclusions critiques
$criticalIncludes = [
    'includes/ui_helpers.php',
    'includes/functions/forum_local.php',
    'includes/functions/forum.php',
    'includes/functions/local_db.php',
    'includes/functions/view_context.php',
];

foreach ($criticalIncludes as $inc) {
    $path = __DIR__ . '/' . $inc;
    if (file_exists($path)) {
        $passes[] = "✓ $inc exists";
    } else {
        $errors[] = "✗ $inc MISSING!";
    }
}

// TEST 2: Fonctions critiques
$criticalFunctions = [
    'e' => 'HTML escape',
    'render_empty_state' => 'Empty state renderer',
    'formatDateFr' => 'French date formatter',
    'forum_topic_badges' => 'Forum topic badges',
    'forum_get_topics' => 'Get forum topics',
    'forum_create_topic' => 'Create topic',
];

foreach ($criticalFunctions as $func => $desc) {
    if (function_exists($func)) {
        $passes[] = "✓ $func() exists";
    } else {
        $warnings[] = "⚠ $func() NOT YET LOADED - will be loaded on demand";
    }
}

// TEST 3: Vérifier forum.php includes forum.php (API)
$forumContent = file_get_contents(__DIR__ . '/forum.php');
if (strpos($forumContent, "includes/functions/forum.php") !== false) {
    $passes[] = "✓ forum.php includes forum.php (API)";
} else {
    $errors[] = "✗ forum.php does NOT include forum.php (API) - forum_topic_badges() will fail!";
}

// TEST 4: Vérifier salarie.php pour count() errors
$sarieContent = file_get_contents(__DIR__ . '/salarie.php');
if (preg_match('/count\s*\(\s*\$pending\s*\)/', $sarieContent)) {
    $errors[] = "✗ salarie.php still has count(\$pending) - will crash!";
} else {
    $passes[] = "✓ salarie.php count() errors seem fixed";
}

// TEST 5: Pages critiques existent
$criticalPages = [
    'forum.php' => 'Forum main',
    'forum_topic.php' => 'Forum topic view',
    'salarie.php' => 'Employee dashboard',
    'admin_catalog.php' => 'Admin catalog',
    'admin_finance.php' => 'Admin finance',
    'admin_forum.php' => 'Admin forum',
    'pro_projects.php' => 'Pro projects',
    'salarie_forum.php' => 'Employee forum',
    'salarie_conseils.php' => 'Employee conseils',
    'salarie_events.php' => 'Employee events',
];

foreach ($criticalPages as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $passes[] = "✓ $file exists";
    } else {
        $errors[] = "✗ $file MISSING!";
    }
}

// TEST 6: Styles
$cssFiles = [
    'styles/style.css',
    'styles/admin.css',
    'styles/employee.css',
    'styles/ui-components.css',
];

foreach ($cssFiles as $css) {
    $path = __DIR__ . '/' . $css;
    if (file_exists($path)) {
        $passes[] = "✓ $css exists";
    } else {
        $warnings[] = "⚠ $css missing";
    }
}

// Print results
echo "\n<h2>Errors (" . count($errors) . ")</h2>";
foreach ($errors as $e) {
    echo "<div class='test fail'><strong>$e</strong></div>\n";
}

echo "<h2>Warnings (" . count($warnings) . ")</h2>";
foreach ($warnings as $w) {
    echo "<div class='test warn'><strong>$w</strong></div>\n";
}

echo "<h2>Passed (" . count($passes) . ")</h2>";
foreach ($passes as $p) {
    echo "<div class='test pass'><strong>$p</strong></div>\n";
}

echo "\n</body>\n</html>";
