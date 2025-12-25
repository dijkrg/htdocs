<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: klanten.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash("Geen klant ID opgegeven.", "error");
    header("Location: klanten.php");
    exit;
}

// Klant ophalen voor bevestiging
$stmt = $conn->prepare("SELECT * FROM klanten WHERE klant_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$klant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klant) {
    setFlash("Klant niet gevonden.", "error");
    header("Location: klanten.php");
    exit;
}

// Verwijderen na bevestiging
if (isset($_POST['verwijder'])) {
    $stmt = $conn->prepare("DELETE FROM klanten WHERE klant_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash("✅ Klant verwijderd.", "success");
    } else {
        setFlash("❌ Fout bij verwijderen: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: klanten.php");
    exit;
}

ob_start();
?>
<h2>Klant verwijderen</h2>
<div class="card">
    <p>Weet je zeker dat je de klant <strong><?= htmlspecialchars($klant['bedrijfsnaam']) ?></strong> wilt verwijderen?</p>
    <form method="post">
        <button type="submit" name="verwijder" class="btn btn-danger">🗑 Verwijderen</button>
        <a href="klanten.php" class="btn btn-secondary">Annuleren</a>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Klant verwijderen";
include __DIR__ . "/template/template.php";
