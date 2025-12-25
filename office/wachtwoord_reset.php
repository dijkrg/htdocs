<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Ongeldige link.");
}

$stmt = $conn->prepare("SELECT * FROM password_resets WHERE token=? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$reset = $res->fetch_assoc();

if (!$reset) {
    die("Ongeldige of verlopen link.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wachtwoord = $_POST['wachtwoord'] ?? '';
    $wachtwoord2 = $_POST['wachtwoord2'] ?? '';

    if ($wachtwoord !== $wachtwoord2) {
        setFlash("Wachtwoorden komen niet overeen.", "error");
    } elseif (strlen($wachtwoord) < 6) {
        setFlash("Wachtwoord moet minimaal 6 tekens lang zijn.", "error");
    } else {
        $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE medewerkers SET wachtwoord=? WHERE email=?");
        $stmt->bind_param("ss", $hash, $reset['email']);
        $stmt->execute();

        // Token ongeldig maken
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email=?");
        $stmt->bind_param("s", $reset['email']);
        $stmt->execute();

        setFlash("Je wachtwoord is succesvol gewijzigd. Je kunt nu inloggen.", "success");
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Nieuw wachtwoord instellen</title>
    <link rel="stylesheet" href="template/style.css">
</head>
<body class="login-body">
<div class="login-container">
    <div class="login-card">
        <h2>Nieuw wachtwoord instellen</h2>
        <?php showFlash(); ?>
        <form method="post">
            <input type="password" name="wachtwoord" placeholder="Nieuw wachtwoord" required>
            <input type="password" name="wachtwoord2" placeholder="Herhaal wachtwoord" required>
            <button type="submit">Opslaan</button>
        </form>
    </div>
</div>
</body>
</html>
