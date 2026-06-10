<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function available_languages(): array
{
    return ['fr', 'en'];
}

function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? 'fr';
    return in_array($lang, available_languages(), true) ? $lang : 'fr';
}

function set_lang_from_request(): void
{
    if (!empty($_GET['lang']) && in_array($_GET['lang'], available_languages(), true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
}

function t(string $key): string
{
    static $cache = [];
    $lang = current_lang();
    if (!isset($cache[$lang])) {
        $file = __DIR__ . '/../i18n/' . $lang . '.php';
        $cache[$lang] = file_exists($file) ? (require $file) : [];
    }
    if (isset($cache[$lang][$key])) {
        return (string)$cache[$lang][$key];
    }
    if (!isset($cache['fr'])) {
        $fr = __DIR__ . '/../i18n/fr.php';
        $cache['fr'] = file_exists($fr) ? (require $fr) : [];
    }
    return (string)($cache['fr'][$key] ?? $key);
}

function lang_url(string $lang): string
{
    $query = $_GET;
    $query['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    $qs = http_build_query($query);
    return $path . ($qs !== '' ? '?' . $qs : '');
}
