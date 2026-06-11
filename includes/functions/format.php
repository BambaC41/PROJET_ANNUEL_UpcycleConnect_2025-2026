<?php
// includes/functions/format.php

if (!function_exists('formatPriceEur')) {
    function formatPriceEur(float $price): string
    {
        return number_format($price, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string
    {
        if (empty($date)) return '';
        $timestamp = strtotime($date);
        return date('d/m/Y H:i', $timestamp);
    }
}
?>