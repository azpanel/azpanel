<?php

namespace app\controller;

use app\model\Config;
use GuzzleHttp\Client;
use PHPMailer\PHPMailer\PHPMailer;

class Notify
{
    public static function email($recipient, $title, $content)
    {
        $mail = new PHPMailer(true);
        $smtp_configs = Config::group('smtp');

        // $mail->SMTPDebug  = SMTP::DEBUG_SERVER;                  // Enable verbose debug output
        $mail->isSMTP(); // Send using SMTP
        $mail->Host = $smtp_configs['smtp_host']; // Set the SMTP server to send through
        $mail->SMTPAuth = true; // Enable SMTP authentication
        $mail->Encoding = 'base64';
        $mail->Username = $smtp_configs['smtp_username']; // SMTP username
        $mail->Password = $smtp_configs['smtp_password']; // SMTP password
        if ((int) $smtp_configs['smtp_port'] === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Port = $smtp_configs['smtp_port']; // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Recipients
        $mail->setFrom($smtp_configs['smtp_sender'], $smtp_configs['smtp_name']);
        $mail->addAddress($recipient); // Add a recipient

        // Content
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = $content;

        $mail->send();
    }

    public static function telegram($recipient, $content)
    {
        $client = new Client();
        $params = [
            'chat_id' => $recipient,
            'text' => $content,
        ];

        $url = 'https://api.telegram.org/bot' . Config::obtain('telegram_token') . '/sendMessage?' . http_build_query($params);
        $client->post($url);
    }
}
