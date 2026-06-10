<?php

require_once __DIR__ . '/../third_party/qrcode_arase.php';

/**
 * Returns raw SVG markup for a scannable QR (local generator, MIT).
 */
function qr_svg_string(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
    }
    $qr = QRCode::getMinimumQRCode($value, QR_ERROR_CORRECT_LEVEL_M);
    ob_start();
    $qr->printSVG(4);
    return (string)ob_get_clean();
}

function qr_svg_data_uri(string $value): string
{
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(qr_svg_string($value));
}
