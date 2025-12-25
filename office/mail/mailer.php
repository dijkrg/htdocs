<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $to, string $subject, string $body, ?string $altBody = null): bool
{
    global $conn;

    $res = $conn->query("SELECT * FROM mailinstellingen LIMIT 1");
    $config = $res ? $res->fetch_assoc() : null;

    if (!$config) {
        error_log("sendMail: geen mailinstellingen gevonden.");
        return false;
    }

    $mail = new PHPMailer(true);
    $ok   = false;
    $fromEmail = $config['van_email'] ?? 'no-reply@example.com';
    $fromName  = $config['van_naam'] ?? '';

    try {
        $mail->CharSet   = 'UTF-8';
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['gebruikersnaam'];
        $mail->Password   = $config['wachtwoord'];
        $mail->Port       = (int)$config['poort'];

        if (!empty($config['beveiliging']) && $config['beveiliging'] !== 'none') {
            $mail->SMTPSecure = $config['beveiliging']; // ssl of tls
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?? strip_tags($body);

        $mail->send();
        $ok = true;
        $status = "verzonden";
        $error  = null;

    } catch (Exception $e) {
        $status = "fout";
        $error  = $mail->ErrorInfo;
        error_log("sendMail: " . $mail->ErrorInfo);
    }

    $stmt = $conn->prepare("
        INSERT INTO mail_log (afzender, ontvanger, onderwerp, bericht, status, foutmelding, verzonden_op)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("ssssss", $fromEmail, $to, $subject, $body, $status, $error);
    $stmt->execute();
    $stmt->close();

    return $ok;
}
