function stripe_verify_payment($sessionId) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/checkout/sessions/" . $sessionId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, getenv('STRIPE_SECRET_KEY') . ':');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'paid' => ($data['payment_status'] === 'paid'),
            'amount' => $data['amount_total'] / 100,
            'user_id' => $data['metadata']['user_id'] ?? null
        ];
    }
    return ['success' => false];
}