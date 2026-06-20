<?php

function guest_chat_get_messages($token) {
    $res = api_get('/guest/messages?token=' . urlencode($token), false);
    if (($res['status'] ?? 0) === 200) {
        return $res['data'];
    }
    return [];
}

function guest_chat_send_message($token, $content) {
    return api_post('/guest/messages?token=' . urlencode($token), ['content' => $content], false);
}