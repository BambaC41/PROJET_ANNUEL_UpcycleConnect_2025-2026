<?php

require_once __DIR__ . '/local_db.php';
require_once __DIR__ . '/pdf_simple.php';

function ensure_documents_storage(): string
{
    $dir = __DIR__ . '/../../storage/documents';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return realpath($dir) ?: $dir;
}

function document_generate_ref(string $prefix = 'DOC'): string
{
    return $prefix . '-' . date('YmdHis') . '-' . random_int(1000, 9999);
}


function document_create_html(
    int $userId,
    string $type,
    string $title,
    string $html,
    ?int $paymentId = null,
    ?int $demandeId = null,
    ?int $inscriptionId = null,
    ?array $pdfLines = null,
): ?int {
    $storage = ensure_documents_storage();
    $filename = strtolower($type) . '_' . $userId . '_' . time() . '_' . random_int(100, 999) . '.pdf';
    $fullPath = $storage . DIRECTORY_SEPARATOR . $filename;

    $lines = $pdfLines ?? pdf_lines_from_simple_html($html);
    if ($lines === []) {
        $lines = ['Document genere automatiquement — UpcycleConnect'];
    }
    $lines[] = 'Document genere automatiquement — UpcycleConnect';
    $pdfBinary = pdf_simple_build($title, $lines);

    if (@file_put_contents($fullPath, $pdfBinary) === false) {
        return null;
    }

    $relative = 'storage/documents/' . $filename;
    return db_safe_exec(function (PDO $pdo) use ($userId, $type, $title, $relative, $html, $paymentId, $demandeId, $inscriptionId) {
        $sql = 'INSERT INTO document_genere (id_user, type, titre, file_path, contenu_html, id_paiement, id_demande, id_inscription, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $type, $title, $relative, $html, $paymentId, $demandeId, $inscriptionId]);
        return (int)$pdo->lastInsertId();
    }, null);
}

function document_list_for_user(int $userId): array
{
    return (array)db_safe_exec(function (PDO $pdo) use ($userId) {
        $stmt = $pdo->prepare('SELECT * FROM document_genere WHERE id_user = ? ORDER BY id_document DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }, []);
}

function document_list_all(): array
{
    return (array)db_safe_exec(function (PDO $pdo) {
        $stmt = $pdo->query('SELECT d.*, u.email FROM document_genere d JOIN utilisateur u ON u.id_user = d.id_user ORDER BY d.id_document DESC');
        return $stmt->fetchAll();
    }, []);
}

function document_get_by_id(int $id): ?array
{
    return db_safe_exec(function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare('SELECT * FROM document_genere WHERE id_document = ?');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        return $doc ?: null;
    }, null);
}
