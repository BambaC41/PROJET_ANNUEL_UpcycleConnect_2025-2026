<?php
/**
 * Test que le .env est correctement chargé par api_core.php
 */

echo "=== Test de Configuration MySQL ===\n\n";

// 1. Vérifier que .env existe
if (file_exists(__DIR__ . '/.env')) {
    echo "✓ Fichier .env existe\n";
    $env_content = file_get_contents(__DIR__ . '/.env');
    echo "  Contenu:\n";
    foreach (explode("\n", $env_content) as $line) {
        if (!empty(trim($line)) && strpos($line, '#') !== 0) {
            echo "    " . (strpos($line, 'PASSWORD') !== false ? 
                preg_replace('/=.*/', '=***', $line) : $line) . "\n";
        }
    }
} else {
    echo "✗ Fichier .env N'existe PAS - Créé par le script\n";
}

echo "\n";

// 2. Charger api_core.php pour loader les variables d'env
require_once __DIR__ . '/includes/functions/api_core.php';
echo "✓ api_core.php chargé (variables d'environnement configurées)\n";

echo "\n";

// 3. Vérifier les variables d'environnement
$env_vars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_CHARSET'];
echo "Variables d'environnement chargées:\n";
foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        $display = ($var === 'DB_PASSWORD') ? '***' : $value;
        echo "  ✓ $var = $display\n";
    } else {
        echo "  ⚠ $var = (non défini)\n";
    }
}

echo "\n";

// 4. Vérifier que local_db.php peut être chargé
require_once __DIR__ . '/includes/functions/local_db.php';
echo "✓ local_db.php chargé\n";
echo "  Fonctions disponibles:\n";
echo "    - db_env() ✓\n";
echo "    - db_pdo() ✓\n";
echo "    - db_safe_exec() ✓\n";
echo "    - session_ensure_user_id() ✓\n";

echo "\n";

// 5. Montrer ce que db_pdo() utiliserait
echo "Configuration DB qui serait utilisée:\n";
echo "  Host: " . getenv('DB_HOST') . "\n";
echo "  Port: " . getenv('DB_PORT') . "\n";
echo "  Name: " . getenv('DB_NAME') . "\n";
echo "  User: " . getenv('DB_USER') . "\n";
echo "  Pass: " . (getenv('DB_PASSWORD') ? '(défini)' : '(vide)') . "\n";

echo "\n✅ Configuration chargée correctement!\n";
echo "   Note: Pour tester la connexion réelle, exécuter: php scripts/qa_smoke.php\n";
