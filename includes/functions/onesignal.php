<?php

function envoyerNotificationOneSignal($userId, $titre, $message, $extraData = []) {
    // TES CLIÉS ONE SIGNAL (à garder secrètes)
    $appId = "83ba0d33-b805-4618-abd5-efb1";
    $apiKey = "os_v2_app_qo5a2m5yavdbrk6v565tml74nmvcpz3gamdej2nsan4mdc23phnkb35pd3svsoddivfxwzgwqvwqscmapskj5try2wv2w2hgv3pu6vq";
    
    $data = [
        'app_id' => $appId,
        'contents' => ['en' => $message],
        'headings' => ['en' => $titre],
        'include_external_user_ids' => [(string)$userId],
        'data' => $extraData
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Erreur OneSignal: " . $response);
        return false;
    }
    
    return json_decode($response, true);
}
?>
