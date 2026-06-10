<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function render_empty_state(string $title, string $message, ?string $actionLabel = null, ?string $actionUrl = null): void
{
    echo '<div class="empty-state">';
    echo '<h3>' . e($title) . '</h3>';
    echo '<p class="muted">' . e($message) . '</p>';
    if ($actionLabel !== null && $actionUrl !== null && $actionUrl !== '') {
        echo '<a class="btn-primary" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a>';
    }
    echo '</div>';
}

function conseil_categories_list(): array
{
    return ['Tutoriel', 'Guide', 'News', 'Éco-gestes', 'Réparation', 'Matériaux', 'Sécurité', 'Autre'];
}

function conseil_author_label(?int $authorId, array $pseudoMap = []): string
{
    $id = (int)($authorId ?? 0);
    if ($id <= 0) {
        return 'Système';
    }
    return (string)($pseudoMap[$id] ?? ('Auteur #' . $id));
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string
    {
        if (empty($date)) {
            return '—';
        }
        $t = strtotime($date);
        return $t === false ? (string)$date : date('d/m/Y H:i', $t);
    }
}

function session_status_badge_class(string $statut): string
{
    return match ($statut) {
        'valide' => 'badge-open',
        'rejete' => 'badge-reported',
        'annule' => 'badge-closed',
        default => 'badge-pending',
    };
}
