<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager (optioneel)
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: ../login.php");
    exit;
}

$pageTitle = "Nieuwe leverancier toevoegen";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam']);
    $contactpersoon = trim($_POST['contactpersoon']);
    $telefoon = trim($_POST['telefoon']);
    $email = trim($_POST['email']);
    $adres = trim($_POST['adres']);
    $postcode = trim($_POST['postcode']);
    $plaats = trim($_POST['plaats']);
    $land = trim($_POST['land']);

    if (empty($naam)) {
        setFlash("Naam is verplicht.", "error");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO leveranciers (naam, contactpersoon, telefoon, email, adres, postcode, plaats, land)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $naam, $contactpersoon, $telefoon, $email, $adres, $postcode, $plaats, $land);
        if ($stmt->execute()) {
            setFlash("Leverancier succesvol toegevoegd.", "success");
            header("Location: leveranciers.php");
            exit;
        } else {
            setFlash("Fout bij toevoegen leverancier: " . $stmt->error, "error");
        }
    }
}

ob_start();
?>
<h2>➕ Nieuwe leverancier toevoegen</h2>

<form method="post" class="form-card">
    <label>Naam *</label>
    <input type="text" name="naam" required>

    <label>Contactpersoon</label>
    <input type="text" name="contactpersoon">

    <label>Telefoon</label>
    <input type="text" name="telefoon">

    <label>Email</label>
    <input type="email" name="email">

    <label>Adres</label>
    <input type="text" name="adres">

    <label>Postcode</label>
    <input type="text" name="postcode">

    <label>Plaats</label>
    <input type="text" name="plaats">

    <label>Land</label>
    <input type="text" name="land" value="Nederland">

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
        <a href="leveranciers.php" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
