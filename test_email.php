<?php
require 'vendor/autoload.php'; // si PHPMailer est installé via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'b0ec95001@smtp-brevo.com';
    $mail->Password   = 'tqZ5b1N6J4fHYcwP';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('b0ec95001@smtp-brevo.com', 'UpcycleConnect');
    $mail->addAddress('ton_email_perso@gmail.com'); // remplace par ton email
    $mail->Subject = 'Test Brevo SMTP';
    $mail->Body    = 'Ceci est un test d\'envoi depuis UpcycleConnect via Brevo.';

    $mail->send();
    echo '✅ Email envoyé avec succès !';
} catch (Exception $e) {
    echo "❌ Erreur : {$mail->ErrorInfo}";
}