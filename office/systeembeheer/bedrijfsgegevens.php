<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot instellingen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🏢 Bedrijfsgegevens";
$id = 1;

// Ophalen
$stmt = $conn->prepare("SELECT * FROM bedrijfsgegevens WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// Opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("
        UPDATE bedrijfsgegevens 
        SET bedrijfsnaam=?, adres=?, postcode=?, plaats=?, telefoon=?, email=?, website=?, kvk=?, btw_nummer=?, iban=?, logo_pad=?, plaatsnaam_pdf=?, pdf_footer=? 
        WHERE id=?
    ");
    $stmt->bind_param(
        "sssssssssssssi",
        $_POST['bedrijfsnaam'], $_POST['adres'], $_POST['postcode'], $_POST['plaats'],
        $_POST['telefoon'], $_POST['email'], $_POST['website'], $_POST['kvk'],
        $_POST['btw_nummer'], $_POST['iban'], $_POST['logo_pad'],
        $_POST['plaatsnaam_pdf'], $_POST['pdf_footer'], $id
    );
    $stmt->execute();

    setFlash("Bedrijfsgegevens succesvol opgeslagen ✅", "success");
    header("Location: bedrijfsgegevens.php");
    exit;
}

ob_start();
?>

<div class="page-header">
    <h2>🏢 Bedrijfsgegevens</h2>
</div>

<div class="card form-card">
<form method="post">
    <label>Bedrijfsnaam *</label>
    <input type="text" name="bedrijfsnaam" value="<?= htmlspecialchars($data['bedrijfsnaam'] ?? '') ?>" required>

    <label>Adres</label>
    <input type="text" name="adres" value="<?= htmlspecialchars($data['adres'] ?? '') ?>">

    <label>Postcode / Plaats</label>
    <div style="display:flex; gap:10px;">
        <input type="text" name="postcode" value="<?= htmlspecialchars($data['postcode'] ?? '') ?>" style="flex:1;">
        <input type="text" name="plaats" value="<?= htmlspecialchars($data['plaats'] ?? '') ?>" style="flex:2;">
    </div>

    <label>Telefoon / E-mail / Website</label>
    <input type="text" name="telefoon" value="<?= htmlspecialchars($data['telefoon'] ?? '') ?>">
    <input type="email" name="email" value="<?= htmlspecialchars($data['email'] ?? '') ?>">
    <input type="text" name="website" value="<?= htmlspecialchars($data['website'] ?? '') ?>">

    <label>KVK / BTW / IBAN</label>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
        <input type="text" name="kvk" placeholder="KVK" value="<?= htmlspecialchars($data['kvk'] ?? '') ?>">
        <input type="text" name="btw_nummer" placeholder="BTW" value="<?= htmlspecialchars($data['btw_nummer'] ?? '') ?>">
        <input type="text" name="iban" placeholder="IBAN" value="<?= htmlspecialchars($data['iban'] ?? '') ?>">
    </div>

    <label>Logo-pad</label>
    <input type="text" name="logo_pad" value="<?= htmlspecialchars($data['logo_pad'] ?? '') ?>">

    <label>Plaatsnaam PDF</label>
    <input type="text" name="plaatsnaam_pdf" value="<?= htmlspecialchars($data['plaatsnaam_pdf'] ?? '') ?>">

    <label>PDF-voettekst</label>
    <textarea name="pdf_footer" rows="2"><?= htmlspecialchars($data['pdf_footer'] ?? '') ?></textarea>

    <div class="form-actions" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
    </div>
</form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
