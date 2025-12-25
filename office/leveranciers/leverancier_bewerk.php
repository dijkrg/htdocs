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

$id = (int)$_GET['id'];
$pageTitle = "Leverancier bewerken";

// Ophalen leverancier
$stmt = $conn->prepare("SELECT * FROM leveranciers WHERE leverancier_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$leverancier = $stmt->get_result()->fetch_assoc();

if (!$leverancier) {
    setFlash("Leverancier niet gevonden.", "error");
    header("Location: leveranciers.php");
    exit;
}

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
            UPDATE leveranciers 
            SET naam=?, contactpersoon=?, telefoon=?, email=?, adres=?, postcode=?, plaats=?, land=? 
            WHERE leverancier_id=?
        ");
        $stmt->bind_param("ssssssssi", $naam, $contactpersoon, $telefoon, $email, $adres, $postcode, $plaats, $land, $id);
        if ($stmt->execute()) {
            setFlash("Leverancier succesvol bijgewerkt.", "success");
            header("Location: leveranciers.php");
            exit;
        } else {
            setFlash("Fout bij bijwerken leverancier: " . $stmt->error, "error");
        }
    }
}

ob_start();
?>
<h2>✏️ Leverancier bewerken</h2>

<form method="post" class="form-card">
    <label>Naam *</label>
    <input type="text" name="naam" value="<?= htmlspecialchars($leverancier['naam']) ?>" required>

    <label>Contactpersoon</label>
    <input type="text" name="contactpersoon" value="<?= htmlspecialchars($leverancier['contactpersoon']) ?>">

    <label>Telefoon</label>
    <input type="text" name="telefoon" value="<?= htmlspecialchars($leverancier['telefoon']) ?>">

    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($leverancier['email']) ?>">

    <label>Adres</label>
    <input type="text" name="adres" value="<?= htmlspecialchars($leverancier['adres']) ?>">

    <label>Postcode</label>
    <input type="text" name="postcode" value="<?= htmlspecialchars($leverancier['postcode']) ?>">

    <label>Plaats</label>
    <input type="text" name="plaats" value="<?= htmlspecialchars($leverancier['plaats']) ?>">

    <label>Land</label>
    <input type="text" name="land" value="<?= htmlspecialchars($leverancier['land']) ?>">

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
        <a href="leveranciers.php" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
