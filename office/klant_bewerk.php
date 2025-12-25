<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);
$res = $conn->query("SELECT * FROM klanten WHERE klant_id=$id");
if ($res->num_rows == 0) {
    setFlash("Klant niet gevonden!", "error");
    header("Location: klanten.php");
    exit;
}
$klant = $res->fetch_assoc();

if (isset($_POST['opslaan'])) {
    $stmt = $conn->prepare("UPDATE klanten SET 
        debiteurnummer=?, bedrijfsnaam=?, contactpersoon=?, telefoonnummer=?, email=?, adres=?, postcode=?, plaats=?, opmerkingen=? 
        WHERE klant_id=?");
    $stmt->bind_param(
        "sssssssssi",
        $_POST['debiteurnummer'],
        $_POST['bedrijfsnaam'],
        $_POST['contactpersoon'],
        $_POST['telefoonnummer'],
        $_POST['email'],
        $_POST['adres'],
        $_POST['postcode'],
        $_POST['plaats'],
        $_POST['opmerkingen'],
        $id
    );

    if ($stmt->execute()) {
        setFlash("Klant succesvol bijgewerkt!", "success");
    } else {
        setFlash("Fout bij opslaan klant: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: klant_detail.php?id=" . $id);
    exit;
}

ob_start();
?>
<h2>Klant bewerken</h2>
<div class="card">
<form method="post" class="form-table">
    <div class="form-row">
        <label>Debiteurnummer*</label>
        <input type="text" name="debiteurnummer" value="<?= htmlspecialchars($klant['debiteurnummer']) ?>" required>
    </div>
    <div class="form-row">
        <label>Bedrijfsnaam*</label>
        <input type="text" name="bedrijfsnaam" value="<?= htmlspecialchars($klant['bedrijfsnaam']) ?>" required>
    </div>
    <div class="form-row">
        <label>Contactpersoon</label>
        <input type="text" name="contactpersoon" value="<?= htmlspecialchars($klant['contactpersoon']) ?>">
    </div>
    <div class="form-row">
        <label>Telefoon</label>
        <input type="text" name="telefoonnummer" value="<?= htmlspecialchars($klant['telefoonnummer']) ?>">
    </div>
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($klant['email']) ?>">
    </div>
    <div class="form-row">
        <label>Adres</label>
        <input type="text" name="adres" value="<?= htmlspecialchars($klant['adres']) ?>">
    </div>
    <div class="form-row">
        <label>Postcode</label>
        <input type="text" name="postcode" value="<?= htmlspecialchars($klant['postcode']) ?>">
    </div>
    <div class="form-row">
        <label>Plaats</label>
        <input type="text" name="plaats" value="<?= htmlspecialchars($klant['plaats']) ?>">
    </div>
    <div class="form-row">
        <label>Opmerkingen</label>
        <textarea name="opmerkingen"><?= htmlspecialchars($klant['opmerkingen']) ?></textarea>
    </div>
    <div class="form-row">
        <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
        <a href="klant_detail.php?id=<?= $id ?>" class="btn btn-secondary">⬅ Annuleren</a>
    </div>
</form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Klant bewerken";
include __DIR__ . "/template/template.php";
