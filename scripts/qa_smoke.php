<?php
declare(strict_types=1);

// CLI only
if (PHP_SAPI !== 'cli') {
    http_response_code(400);
    echo "This script must be run in CLI.\n";
    exit(1);
}

function envv(string $key, string $default = ''): string
{
    $v = getenv($key);
    return $v !== false ? (string)$v : $default;
}

function out(string $level, string $message): void
{
    echo sprintf("[%s] %s\n", $level, $message);
}

function ok(string $m): void { out('OK', $m); }
function warn(string $m): void { out('WARNING', $m); }
function err(string $m): void { out('ERROR', $m); }

$hasError = false;

// Load project DB helper if available (do not fail if missing)
$localDbPath = __DIR__ . '/../includes/functions/local_db.php';
if (is_file($localDbPath)) {
    require_once $localDbPath;
    ok("Loaded DB helper: includes/functions/local_db.php");
} else {
    warn("DB helper not found at includes/functions/local_db.php (fallback to env connection).");
}

function connect_pdo(): PDO
{
    if (function_exists('db_pdo')) {
        /** @var PDO $pdo */
        $pdo = db_pdo();
        return $pdo;
    }

    $host = envv('DB_HOST', 'localhost');
    $port = envv('DB_PORT', '3306');
    $name = envv('DB_NAME', 'upcycleconnect');
    $user = envv('DB_USER', 'root');
    $pass = envv('DB_PASSWORD', '');
    $charset = envv('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

try {
    $pdo = connect_pdo();
    ok("DB connection OK");
} catch (Throwable $e) {
    err("DB connection failed: " . $e->getMessage());
    exit(1);
}

try {
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($db === '') {
        $hasError = true;
        err("No database selected (SELECT DATABASE() empty).");
    } else {
        ok("Database selected: " . $db);
    }
} catch (Throwable $e) {
    $hasError = true;
    err("Cannot read selected database: " . $e->getMessage());
}

$requiredTables = [
    'role',
    'utilisateur',
    'objet',
    'conteneur',
    'demande_depot',
    'code_acces',
    'code_barre',
    'retrait',
    'annonce',
    'categorie_prestation',
    'prestation',
    'session',
    'inscription',
    'session',
    'paiement',
    'conseil',
    'notification',
    'document_genere',
    'facture',
    'projet_upcycling',
    'projet_etape',
    'abonnement_pro',
    'campagne_publicitaire',
    'forum_categories',
    'forum_topics',
    'forum_posts',
    'forum_reports',
    'forum_moderation_logs',
    'audit_log',
];

try {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $have = [];
    foreach ($tables as $row) {
        $have[] = (string)$row[0];
    }
    $missing = array_values(array_diff($requiredTables, $have));
    if (!empty($missing)) {
        $hasError = true;
        err("Missing tables: " . implode(', ', $missing));
    } else {
        ok("All required tables present (" . count($requiredTables) . ")");
    }
} catch (Throwable $e) {
    $hasError = true;
    err("SHOW TABLES failed: " . $e->getMessage());
}

// Column checks (minimal critical set)
$requiredColumns = [
    'utilisateur' => [
        'id_user', 'email', 'password_hash', 'pseudo', 'prenom', 'nom', 'telephone',
        'adresse_rue', 'adresse_ville', 'adresse_code_postal', 'adresse_pays',
        'photo_profil', 'bio', 'statut', 'is_banned', 'ban_reason', 'ban_until',
        'is_approved', 'tutorial_completed', 'created_at', 'id_role',
    ],
    'paiement' => [
        'id_paiement',
        'provider', 'payment_ref', 'montant', 'devise', 'statut', 'paid_at', 'created_at', 'id_inscription',
        'stripe_session_id', 'payment_provider', 'amount', 'currency', 'status', 'user_id',
    ],
    'notification' => ['id_notification', 'id_user', 'type', 'titre', 'contenu', 'is_read', 'created_at'],
    'document_genere' => ['id_document', 'id_user', 'type', 'titre', 'file_path', 'contenu_html', 'id_paiement', 'id_demande', 'id_inscription', 'created_at'],
    'facture' => ['id_facture', 'numero', 'id_user', 'id_paiement', 'montant_ht', 'montant_ttc', 'statut', 'created_at'],
    'annonce' => ['id_annonce', 'id_reserve_par', 'id_acheteur', 'date_reserve', 'date_achat'],
    'conseil' => ['id_conseil', 'id_auteur', 'titre', 'contenu', 'image_url', 'is_active'],
    'session' => ['id_session', 'date_debut', 'date_fin', 'lieu', 'capacite_max', 'statut', 'id_prestation', 'id_createur'],
];

try {
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    foreach ($requiredColumns as $table => $cols) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$dbName, $table]);
        $haveCols = array_map(static fn($r) => (string)$r['COLUMN_NAME'], $stmt->fetchAll());
        $missCols = array_values(array_diff($cols, $haveCols));
        if (!empty($missCols)) {
            $hasError = true;
            err("Missing columns in {$table}: " . implode(', ', $missCols));
        } else {
            ok("Columns OK for {$table}");
        }
    }
} catch (Throwable $e) {
    $hasError = true;
    err("Column checks failed: " . $e->getMessage());
}

// Demo accounts checks
$demoUsers = [
    ['email' => 'admin@upcycleconnect.fr', 'id_role' => 1],
    ['email' => 'user1@test.com', 'id_role' => 2],
    ['email' => 'pro1@test.com', 'id_role' => 3],
    ['email' => 'emp1@test.com', 'id_role' => 4],
];

try {
    $stmt = $pdo->prepare('SELECT email, id_role, is_approved, is_banned, tutorial_completed FROM utilisateur WHERE email = ? LIMIT 1');
    foreach ($demoUsers as $u) {
        $stmt->execute([$u['email']]);
        $row = $stmt->fetch();
        if (!$row) {
            $hasError = true;
            err("Demo user missing: {$u['email']}");
            continue;
        }
        $roleOk = (int)($row['id_role'] ?? 0) === (int)$u['id_role'];
        $approvedOk = (int)($row['is_approved'] ?? 0) === 1;
        $bannedOk = (int)($row['is_banned'] ?? 0) === 0;
        if (!$roleOk || !$approvedOk || !$bannedOk) {
            $hasError = true;
            err("Demo user invalid flags: {$u['email']} (role={$row['id_role']}, approved={$row['is_approved']}, banned={$row['is_banned']})");
        } else {
            ok("Demo user OK: {$u['email']} (role {$u['id_role']})");
        }
        if ($u['email'] === 'user1@test.com' && (int)($row['tutorial_completed'] ?? 1) !== 0) {
            $hasError = true;
            err("user1@test.com must have tutorial_completed=0 for demo.");
        }
    }
} catch (Throwable $e) {
    $hasError = true;
    err("Demo accounts check failed: " . $e->getMessage());
}

// Minimal statuses coherence
$expected = [
    'annonce' => ['en_attente', 'validee', 'rejetee'],
    'demande_depot' => ['en_attente', 'validee', 'deposee', 'retiree', 'rejetee'],
    'session' => ['en_attente', 'valide', 'annule', 'rejete'],
    'inscription' => ['en_attente', 'confirmee', 'annulee'],
    'paiement.status' => ['pending', 'paid', 'failed', 'refunded'],
];

try {
    $checks = [
        ['table' => 'annonce', 'col' => 'statut', 'key' => 'annonce'],
        ['table' => 'demande_depot', 'col' => 'statut', 'key' => 'demande_depot'],
        ['table' => 'session', 'col' => 'statut', 'key' => 'session'],
        ['table' => 'inscription', 'col' => 'statut', 'key' => 'inscription'],
        ['table' => 'paiement', 'col' => 'status', 'key' => 'paiement.status'],
    ];
    foreach ($checks as $c) {
        $rows = $pdo->query("SELECT DISTINCT {$c['col']} AS v FROM {$c['table']}")->fetchAll();
        $values = [];
        foreach ($rows as $r) {
            $v = trim((string)($r['v'] ?? ''));
            if ($v !== '') $values[] = $v;
        }
        $values = array_values(array_unique($values));
        $unexpected = array_values(array_diff($values, $expected[$c['key']]));
        if (!empty($unexpected)) {
            warn("Unexpected statuses in {$c['table']}.{$c['col']}: " . implode(', ', $unexpected));
        } else {
            ok("Statuses OK for {$c['table']}.{$c['col']}");
        }
    }
} catch (Throwable $e) {
    warn("Status checks skipped: " . $e->getMessage());
}

// Files presence
$requiredFiles = [
    __DIR__ . '/../notifications.php',
    __DIR__ . '/../document_download.php',
    __DIR__ . '/../paiement_demo.php',
    __DIR__ . '/../paiement_checkout_demo.php',
    __DIR__ . '/../paiement_success.php',
    __DIR__ . '/../paiement_cancel.php',
    __DIR__ . '/../tutorial_complete.php',
    __DIR__ . '/../tutorial_reset.php',
    __DIR__ . '/../includes/notifications.php',
    __DIR__ . '/../includes/functions/documents.php',
    __DIR__ . '/../includes/functions/demo_payments.php',
    __DIR__ . '/../includes/functions/pdf_simple.php',
    __DIR__ . '/../includes/functions/qr.php',
    __DIR__ . '/../includes/functions/bootstrap_notify.php',
    __DIR__ . '/../includes/third_party/qrcode_arase.php',
    __DIR__ . '/../storage/documents',
];

foreach ($requiredFiles as $p) {
    if (is_dir($p) || is_file($p)) {
        ok("Path exists: " . str_replace('\\', '/', $p));
    } else {
        $hasError = true;
        err("Missing path: " . str_replace('\\', '/', $p));
    }
}

if ($hasError) {
    err("Smoke test finished with blocking errors.");
    exit(1);
}

ok("Smoke test finished successfully.");
exit(0);

