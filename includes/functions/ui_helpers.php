<?php

if (!function_exists('formatPriceEur')) {
    function formatPriceEur(float $price): string {
        return number_format($price, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('formatDateFr')) {
    function formatDateFr(?string $date): string {
        if (empty($date)) return '';
        $timestamp = strtotime($date);
        return date('d/m/Y H:i', $timestamp);
    }
}

if (!function_exists('e')) {
    function e(?string $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vc_media_url')) {
    function vc_media_url(?string $url): string {
        if (empty($url)) return '';
        return $url;
    }
}

if (!function_exists('conseil_author_label')) {
    function conseil_author_label(?int $authorId, array $authorsMap): string {
        if (!$authorId) return '—';
        return $authorsMap[$authorId] ?? 'Utilisateur #' . $authorId;
    }
}

if (!function_exists('conseils_admin_items_from_response')) {
    function conseils_admin_items_from_response(array $response): array {
        if (($response['status'] ?? 0) !== 200) return [];
        return $response['data']['items'] ?? $response['data'] ?? [];
    }
}

if (!function_exists('conseils_admin_total_from_response')) {
    function conseils_admin_total_from_response(array $response): int {
        if (($response['status'] ?? 0) !== 200) return 0;
        return $response['data']['total'] ?? count($response['data'] ?? []);
    }
}

if (!function_exists('forum_items_from_response')) {
    function forum_items_from_response(array $response): array {
        if (($response['status'] ?? 0) !== 200) return [];
        return $response['data']['items'] ?? $response['data'] ?? [];
    }
}

if (!function_exists('forum_api_error_message')) {
    function forum_api_error_message(array $response): string {
        return $response['error'] ?? 'Erreur API inconnue';
    }
}

if (!function_exists('render_empty_state')) {
    function render_empty_state(string $title, string $message, string $buttonText = '', string $buttonLink = ''): void {
        echo '<div style="text-align:center; padding:40px;">';
        echo '<h3>' . e($title) . '</h3>';
        echo '<p>' . e($message) . '</p>';
        if ($buttonText && $buttonLink) {
            echo '<a class="btn-primary" href="' . e($buttonLink) . '">' . e($buttonText) . '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('forum_topic_badges')) {
    function forum_topic_badges(array $topic): array {
        $badges = [];
        if (!empty($topic['is_pinned'])) $badges[] = ['label' => 'Épinglé', 'class' => 'status-ok'];
        if (!empty($topic['is_locked'])) $badges[] = ['label' => 'Verrouillé', 'class' => 'status-warn'];
        if (!empty($topic['is_hidden'])) $badges[] = ['label' => 'Masqué', 'class' => 'status-danger'];
        if (($topic['status'] ?? '') === 'closed') $badges[] = ['label' => 'Fermé', 'class' => 'status-muted'];
        if (empty($badges)) $badges[] = ['label' => 'Ouvert', 'class' => 'status-ok'];
        return $badges;
    }
}
?>
