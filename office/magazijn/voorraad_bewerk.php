<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang tot voorraadbeheer.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📦 Voorraad bewerken";

// ─────────────────────────────
// Validatie artikel_id
// ─────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldig artikel.", "error");
    header("Location: voorraad.php");
    exit;
}
$artikel_id = (int)$_GET['id'];

// ─────────────────────────────
// Artikel ophalen (altijd toegestaan)
// ─────────────────────────────
$stmt = $conn->prepare("
    SELECT a.artikel_id, a.artikelnummer, a.omschrijving, COALESCE(v.aantal, 0) AS aantal
    FROM artikelen a
    LEFT JOIN voorraad v ON v.artikel_id = a.artikel_id
    WHERE a.artikel_id = ?
");
$stmt->bind_param("i", $artikel_id);
$stmt->execute();
$artikel = $stmt->get_result()->fetch_assoc();

if (!$artikel) {
    setFlash("Artikel niet gevonden.", "error");
    header("Location: voorraad.php");
    exit;
}

// ─────────────────────────────
// Formulierverwerking
// ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nieuwAantal = (int)$_POST['aantal'];
    $opmerking   = trim($_POST['opmerking'] ?? '');
    $huidig      = (int)$artikel['aantal'];

    // Controleer of voorraadregel bestaat
    $check = $conn->prepare("SELECT COUNT(*) FROM voorraad WHERE artikel_id = ?");
    $check->bind_param("i", $artikel_id);
    $check->execute();
    $check->bind_result($bestaat);
    $check->fetch();
    $check->close();

    if ($bestaat > 0) {
        // Update bestaande voorraad
        $stmt = $conn->prepare("UPDATE voorraad SET aantal = ?, laatste_update = NOW() WHERE artikel_id = ?");
        $stmt->bind_param("ii", $nieuwAantal, $artikel_id);
    } else {
        // Voeg nieuwe voorraadregel toe
        $stmt = $conn->prepare("INSERT INTO voorraad (artikel_id, aantal, laatste_update) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $artikel_id, $nieuwAantal);
    }
    $stmt->execute();

    // Log transactie
    $type = 'correctie';
    $magazijn_id = 2; // standaard Maarsbergen
    $stmt = $conn->prepare("
        INSERT INTO voorraad_transacties (artikel_id, magazijn_id, datum, type, aantal, opmerking)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->bind_param("iisis", $artikel_id, $magazijn_id, $type, $nieuwAantal, $opmerking);
    $stmt->execute();

    setFlash("Voorraad aangepast van {$huidig} naar {$nieuwAantal} ✅", "success");
    header("Location: voorraad.php");
    exit;
}

ob_start();
?>

<div class="page-header">
    <h2>✏️ Voorraad bewerken</h2>
    <a href="voorraad.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" class="form-card">
        <p><strong>Artikel:</strong> <?= htmlspecialchars($artikel['artikelnummer'] . ' — ' . $artikel['omschrijving']) ?></p>
        <p><strong>Huidige voorraad:</strong> <?= (int)$artikel['aantal'] ?></p>

        <label for="aantal">Nieuw aantal *</label>
        <input type="number" name="aantal" id="aantal" required min="0" value="<?= (int)$artikel['aantal'] ?>">

        <label for="opmerking">Opmerking</label>
        <input type="text" name="opmerking" id="opmerking" placeholder="Bijv. handmatige correctie">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="voorraad.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
