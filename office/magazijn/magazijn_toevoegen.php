<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang.", "error");
    header("Location: magazijnen.php");
    exit;
}

$pageTitle = "➕ Nieuw magazijn";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam']);
    $type = $_POST['type'] ?? 'gebouw';
    $locatie = trim($_POST['locatie']);

    if ($naam === '') {
        setFlash("Naam is verplicht.", "error");
    } else {
        $stmt = $conn->prepare("INSERT INTO magazijnen (naam, type, locatie) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $naam, $type, $locatie);
        $stmt->execute();

        setFlash("Magazijn '$naam' toegevoegd ✅", "success");
        header("Location: magazijnen.php");
        exit;
    }
}

ob_start();
?>
<div class="page-header">
    <h2>➕ Nieuw magazijn</h2>
    <a href="magazijnen.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" class="form-card">
        <label>Naam *</label>
        <input type="text" name="naam" required>

        <label>Type *</label>
        <select name="type" required>
            <option value="gebouw">Gebouw</option>
            <option value="voertuig">Voertuig</option>
        </select>

        <label>Locatie</label>
        <input type="text" name="locatie" placeholder="Adres of omschrijving">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="magazijnen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
