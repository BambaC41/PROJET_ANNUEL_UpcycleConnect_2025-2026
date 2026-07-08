<?php
declare(strict_types=1);

function pdf_simple_translit(string $text): string
{
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false && $t !== '') {
            return $t;
        }
    }
    return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
}

function pdf_simple_escape_pdf(string $s): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}


function pdf_simple_build(string $title, array $lines): string
{
    $title = pdf_simple_translit($title);
    $escapedLines = [];
    foreach ($lines as $ln) {
        $escapedLines[] = pdf_simple_translit((string)$ln);
    }

    $streamParts = ["BT", '/F1 14 Tf', '72 760 Td', '(' . pdf_simple_escape_pdf($title) . ') Tj', '0 -22 Td', '/F1 11 Tf'];
    foreach ($escapedLines as $line) {
        $streamParts[] = '0 -16 Td';
        $streamParts[] = '(' . pdf_simple_escape_pdf($line) . ') Tj';
    }
    $streamParts[] = 'ET';
    $stream = implode("\n", $streamParts);

    $objects = [];

    $objects[1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[2] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    $objects[3] = "<< /Type /Page /Parent 4 0 R /MediaBox [0 0 612 792] /Contents 2 0 R /Resources << /Font << /F1 1 0 R >> >> >>";
    $objects[4] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[5] = "<< /Type /Catalog /Pages 4 0 R >>";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];

    for ($i = 1; $i <= 5; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 5 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

    return $pdf;
}

function pdf_lines_from_simple_html(string $html): array
{
    $t = preg_replace('#</(p|h1|h2|h3|div|li)\s*>#i', "\n", $html);
    $t = preg_replace('#<br\s*/?>#i', "\n", (string)$t);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = preg_split('/\R+/u', (string)$t) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}
