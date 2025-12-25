<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin mag testen
if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang tot testmail.", "error");
    header("Location: ../index.php");
    exit;
}

$bericht = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ontvanger = trim($_POST['ontvanger']);
    if (filter_var($ontvanger, FILTER_VALIDATE_EMAIL)) {
        $result = sendMail(
            $ontvanger,
            "Testmail ABC Brand",
            "<p>Dit is een testmail vanuit het systeem.</p>"
        );
        if ($result === true) {
            $bericht = "✅ Testmail succesvol verstuurd naar: $ontvanger";
        } else {
            $bericht = "❌ Fout bij verzenden: " . htmlspecialchars($result);
        }
    } else {
        $bericht = "❌ Ongeldig e-mailadres.";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Testmail versturen</title>
    <link rel="stylesheet" href="../template/style.css">
</head>
<body>
<div class="content">
    <h2>📧 Testmail versturen</h2>
    <?php if ($bericht): ?>
        <p><?= $bericht ?></p>
    <?php endif; ?>
    <form method="post" class="form-styled" style="max-width:500px;">
        <label>Ontvanger (e-mail)</label>
        <input type="email" name="ontvanger" required>
        <div class="form-actions">
            <button type="submit" class="btn">Verstuur testmail</button>
            <a href="../index.php" class="btn btn-secondary">Terug</a>
        </div>
    </form>
</div>
</body>
</html>
