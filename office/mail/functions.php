<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

function sendMail($to, $subject, $message, $isHtml = true) {
    require __DIR__ . '/../db.php';

    $res = $conn->query("SELECT * FROM mailinstellingen LIMIT 1");
    $settings = $res->fetch_assoc();
    if (!$settings) {
        return "Mailinstellingen niet geconfigureerd.";
    }

    $headers = "From: ".$settings['van_naam']." <".$settings['van_email'].">\r\n";
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    }

    $status = "mislukt";
    if (mail($to, $subject, $message, $headers)) {
        $status = "verzonden";
    }

    // Opslaan in mail_log
    $stmt = $conn->prepare("INSERT INTO mail_log (ontvanger, onderwerp, bericht, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $to, $subject, $message, $status);
    $stmt->execute();

    return $status === "verzonden";
}
