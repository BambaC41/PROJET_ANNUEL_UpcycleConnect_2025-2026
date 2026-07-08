<?php

if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

require_once __DIR__ . '/services/MailService.php';

use UpcycleConnect\Services\MailService;

function getMailService(): MailService
{
    $apiKey = getenv('BREVO_API_KEY') ?: '';
    $fromEmail = getenv('BREVO_FROM_ADDRESS') ?: 'noreply@upcycle-connect.tech';
    $fromName = getenv('BREVO_FROM_NAME') ?: 'UpcycleConnect';

    return new MailService($apiKey, $fromEmail, $fromName);
}
