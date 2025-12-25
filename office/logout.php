<?php
// Start sessie
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/init.php';

// 1️⃣ Database remember-token ongeldig maken
if (!empty($_SESSION['user']['id'])) {
    $uid = (int)$_SESSION['user']['id'];

    $stmt = $conn->prepare("
        UPDATE medewerkers
        SET remember_token = NULL,
            remember_fingerprint = NULL,
            remember_expires = NULL
        WHERE medewerker_id = ?
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
}

// 2️⃣ Sessie verwijderen
$_SESSION = [];
session_unset();
session_destroy();

// 3️⃣ ALLE mogelijke cookie-varianten verwijderen
$cookieNames = ['remember_me', 'remember_email', 'PHPSESSID'];

foreach ($cookieNames as $name) {
    // domain: huidige domain
    setcookie($name, '', time() - 3600, "/", "", false, true);

    // domain: .abcbrandbeveiliging.nl
    setcookie($name, '', time() - 3600, "/", ".abcbrandbeveiliging.nl", false, true);

    // domain: office.abcbrandbeveiliging.nl
    setcookie($name, '', time() - 3600, "/", "office.abcbrandbeveiliging.nl", false, true);

    // domain: abcbrandbeveiliging.nl
    setcookie($name, '', time() - 3600, "/", "abcbrandbeveiliging.nl", false, true);
}

// 4️⃣ Redirect
header("Location: /login.php");
exit;
