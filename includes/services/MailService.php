<?php

namespace UpcycleConnect\Services;

class MailService
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct(string $apiKey, string $fromEmail, string $fromName = 'UpcycleConnect')
    {
        $this->apiKey = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function send(string $to, string $subject, string $html, string $text = ''): array
    {
        $url = 'https://api.brevo.com/v3/smtp/email';

        $data = [
            'sender' => [
                'email' => $this->fromEmail,
                'name'  => $this->fromName
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'htmlContent' => $html,
        ];

        if (!empty($text)) {
            $data['textContent'] = $text;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            return ['success' => true, 'message' => 'Email envoyé'];
        }

        return ['success' => false, 'message' => "Erreur ($httpCode) : " . $response];
    }
}