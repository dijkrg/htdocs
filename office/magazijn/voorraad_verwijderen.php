<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Alleen beheerders kunnen voorraad verwijderen.", "error");
    header("Location: voorraad.php");
    exit;
}

// ─────────────────────────────
// Validatie ID
// ─────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldige voorraadregel.", "error");
    header("Location: voorraad.php");
    exit;
}
$artikel_id = (int)$_GET['id'];

// Artikel ophalen
$stmt = $conn->prepare("
    SELECT a.artikelnummer, a.omschrijving, v.aantal
    FROM artikelen a
    JOIN voorraad v ON v.artikel_id = a.artikel_id
    WHERE a.artikel_id = ? AND (a.categorie IS NULL OR a.categorie <> 'Administratie')
");
$stmt->bind_param("i", $artikel_id);
$stmt->execute();
$artikel = $stmt->get_result()->fetch_assoc();

if (!$artikel) {
    setFlash("Artikel niet gevonden of niet toegestaan.", "error");
    header("Location: voorraad.php");
    exit;
}

// ─────────────────────────────
// Verwijderen bevestigen
// ─────────────────────────────
if (isset($_POST['bevestig'])) {
    // Log transactie
    $stmt = $conn->prepare("
        INSERT INTO voorraad_transacties (artikel_id, datum, type, aantal, opmerking)
        VALUES (?, NOW(), 'verwijdering', ?, ?)
    ");
    $opm = "Voorraadregel verwijderd door Admin";
    $zero = 0;
    $stmt->bind_param("iis", $artikel_id, $zero, $opm);
    $stmt->execute();

    // Verwijderen uit voorraad
    $conn->query("DELETE FROM voorraad WHERE artikel_id = {$artikel_id}");

    setFlash("Voorraadregel voor artikel '{$artikel['artikelnummer']}' is verwijderd ✅", "success");
    header("Location: voorraad.php");
    exit;
}

$pageTitle = "🗑 Voorraadregel verwijderen";
ob_start();
?>

<div class="page-header">
    <h2>🗑 Voorraadregel verwijderen</h2>
    <a href="voorraad.php" class="btn btn-secondary">⬅ Annuleren</a>
</div>

<div class="card warning">
    <p><strong>Weet je zeker dat je deze voorraadregel wilt verwijderen?</strong></p>
    <p>
        Artikel: <?= htmlspecialchars($artikel['artikelnummer'] . ' — ' . $artikel['omschrijving']) ?><br>
        Huidige voorraad: <?= (int)$artikel['aantal'] ?>
    </p>

    <form method="post">
        <button type="submit" name="bevestig" class="btn btn-danger">🗑 Ja, verwijderen</button>
        <a href="voorraad.php" class="btn btn-secondary">Nee</a>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
