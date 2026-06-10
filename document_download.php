<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['token']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/functions/documents.php';
require_once __DIR__ . '/includes/functions/pdf_simple.php';
require_once __DIR__ . '/includes/functions/local_db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Document invalide.');
}

$doc = document_get_by_id($id);
if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
$isAdmin = $roleId === 1;
$ownerId = (int)($doc['id_user'] ?? 0);
if (!$isAdmin && $ownerId !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('Acces refuse.');
}

$relative = trim((string)($doc['file_path'] ?? ''));
$projectRoot = realpath(__DIR__);
if ($projectRoot === false) {
    http_response_code(500);
    exit('Configuration invalide.');
}
$storageRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents');

$inline = isset($_GET['view']) && (string)$_GET['view'] === '1';
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)($doc['titre'] ?? 'document'));
if ($safeName === '') {
    $safeName = 'document';
}
$safeName .= '.pdf';

$full = $relative !== '' ? realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)) : false;

$servedFromDisk = false;
if ($full && $storageRoot && str_starts_with($full, $storageRoot) && is_file($full)) {
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string)filesize($full));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($safeName) . '"');
        readfile($full);
        exit;
    }
    $htmlDisk = (string)file_get_contents($full);
    $lines = pdf_lines_from_simple_html($htmlDisk !== '' ? $htmlDisk : (string)($doc['contenu_html'] ?? ''));
    $pdfBinary = pdf_simple_build((string)($doc['titre'] ?? 'Document'), $lines !== [] ? $lines : ['Document UpcycleConnect']);
} else {
    $lines = pdf_lines_from_simple_html((string)($doc['contenu_html'] ?? ''));
    if ($lines === []) {
        $lines = [
            'Type: ' . (string)($doc['type'] ?? ''),
            'Reference document #' . (string)($doc['id_document'] ?? ''),
        ];
    }
    $pdfBinary = pdf_simple_build((string)($doc['titre'] ?? 'Document'), $lines);
}

// Persist recovered / migrated PDF once when stored path was missing or not PDF
if ($relative === '' || !str_ends_with(strtolower($relative), '.pdf')) {
    $storage = ensure_documents_storage();
    $filename = 'recovered_' . $id . '_' . time() . '.pdf';
    $path = $storage . DIRECTORY_SEPARATOR . $filename;
    if (@file_put_contents($path, $pdfBinary) !== false) {
        $newRel = 'storage/documents/' . $filename;
        db_safe_exec(static function (PDO $pdo) use ($id, $newRel): void {
            $st = $pdo->prepare('UPDATE document_genere SET file_path = ? WHERE id_document = ?');
            $st->execute([$newRel, $id]);
        }, null);
    }
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string)strlen($pdfBinary));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($safeName) . '"');
echo $pdfBinary;
