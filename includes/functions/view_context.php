<?php
require_once __DIR__ . '/api_core.php';

function vc_escape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function vc_media_url(?string $value): string {
    $raw = trim((string)($value ?? ''));
    if ($raw === '') return '';
    if (preg_match('#^https?://#i', $raw)) return $raw;
    $base = getenv('API_BASE_URL') ?: 'http://localhost:8080';
    return rtrim($base, '/') . '/' . ltrim($raw, '/');
}

function vc_current_user(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['token'])) return [];
    $res = callAPI('GET', '/me', $_SESSION['token']);
    return is_array($res['data'] ?? null) ? $res['data'] : [];
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string
    {
        if (empty($date)) {
            return '';
        }
        $t = strtotime($date);
        if ($t === false) {
            return (string)$date;
        }
        return date('d/m/Y H:i', $t);
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('h')) {
    function h($value): string
    {
        return e($value);
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel(string $status): string
    {
        return e($status);
    }
}

function nav_role_dashboard_url(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $role = (int)($_SESSION['role_id'] ?? 0);
    return match ($role) {
        1 => 'admin.php',
        2 => 'particulier.php',
        3 => 'pro.php',
        4 => 'salarie.php',
        default => 'index.php',
    };
}

