<?php


if (!function_exists('session_ensure_user_id')) {
    function session_ensure_user_id(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}
