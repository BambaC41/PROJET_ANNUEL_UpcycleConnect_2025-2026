<?php

require_once __DIR__ . '/api_core.php';

function api_get_my_score() {
    return api_get('/me/score', true);
}
