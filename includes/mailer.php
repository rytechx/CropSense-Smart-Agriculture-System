<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function cropsenseSendOtp(string $email, string $name, string $otp): bool
{
    $config = require __DIR__ . '/../config/mail.local.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        if ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Port = (int) $config['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'CropSense Verification Code';

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:550px;margin:auto;padding:30px;'>
                <h2 style='color:#0f6b46;'>CropSense Security</h2>

                <p>Hello {$safeName},</p>

                <p>Your CropSense verification code is:</p>

                <div style='
                    background:#eef7f1;
                    padding:20px;
                    text-align:center;
                    border-radius:12px;
                    font-size:34px;
                    font-weight:bold;
                    letter-spacing:8px;
                    color:#0f6b46;
                '>
                    {$safeOtp}
                </div>

                <p>This code expires in 5 minutes.</p>

                <p>
                    If you did not attempt to sign in,
                    please ignore this email.
                </p>
            </div>
        ";

        $mail->AltBody =
            "Your CropSense verification code is {$otp}. "
            . "This code expires in 5 minutes.";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'CropSense Mail Error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}