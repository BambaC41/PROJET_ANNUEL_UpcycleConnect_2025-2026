<?php
require_once 'api_core.php';


function api_login($email, $password) {
    $payload = [
        "email" => $email,
        "password" => $password
    ];
    
    return callAPI('POST', '/login', null, $payload);
}
?>
