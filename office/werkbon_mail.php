<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . "/mail/mailer.php";
require_once __DIR__ . "/mail/mail_template.php";

$werkbon_id = intval($_GET['id'] ?? 0);
$res = $conn->query("
    SELECT w.*, k.email AS klant_email, k.bedrijfsnaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    WHERE w.werkbon_id=$werkbon_id
");
$werkbon = $res->fetch_assoc();

if (!$werkbon) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: werkbonnen.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $werkbon['klant_email'];
    $title = "Afspraakbevestiging - Werkbon #" . $werkbon['werkbonnummer'];
    $content = "
        <p>Beste relatie,</p>
        <p>Uw afspraak is ingepland:</p>
        <ul>
            <li><b>Werkbon:</b> {$werkbon['werkbonnummer']}</li>
            <li><b>Omschrijving:</b> {$werkbon['omschrijving']}</li>
            <li><b>Datum:</b> {$werkbon['uitvoerdatum']}</li>
        </ul>
        <p>Met vriendelijke groet,<br>
        ABC Brandbeveiliging</p>
    ";

    $body = renderMailTemplate($title, $content);
    if (sendMail($to, $title, $body)) {
        setFlash("Afspraakbevestiging verzonden naar {$to}", "success");
    } else {
        setFlash("Fout bij verzenden mail.", "error");
    }

    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

// Formulier
ob_start();
?>
<h2>Afspraakbevestiging sturen</h2>
<p>Verstuur een afspraakbevestiging naar: <b><?= htmlspecialchars($werkbon['bedrijfsnaam']) ?></b> (<?= htmlspecialchars($werkbon['klant_email']) ?>)</p>
<form method="post">
    <input type="submit" value="Verstuur bevestiging">
</form>
<a href="werkbon_detail.php?id=<?= $werkbon_id ?>">⬅ Terug</a>
<?php
$content = ob_get_clean();
$pageTitle = "Werkbon mailen";
include __DIR__ . "/template/template.php";
