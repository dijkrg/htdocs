<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM medewerkers WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();

        // TODO: E-mail sturen met resetlink
        $resetLink = "https://".$_SERVER['HTTP_HOST']."/wachtwoord_reset.php?token=".$token;

        setFlash("Als dit e-mailadres bestaat, is een herstel-link verstuurd. (Dev: $resetLink)", "info");
    } else {
        setFlash("Als dit e-mailadres bestaat, is een herstel-link verstuurd.", "info");
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Wachtwoord vergeten</title>
    <link rel="stylesheet" href="template/style.css">
</head>
<body class="login-body">
<div class="login-container">
    <div class="login-card">
        <h2>Wachtwoord vergeten</h2>
        <?php showFlash(); ?>
        <form method="post">
            <input type="email" name="email" placeholder="Vul je e-mail in" required>
            <button type="submit">Verstuur herstel-link</button>
        </form>
        <a href="login.php">⬅ Terug naar login</a>
    </div>
</div>
</body>
</html>
