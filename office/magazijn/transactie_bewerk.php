<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot voorraadtransacties.", "error");
    header("Location: ../index.php");
    exit;
}

// ─────────────────────────────────────────────
// ID ophalen & validatie
// ─────────────────────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldige transactie.", "error");
    header("Location: transacties.php");
    exit;
}
$transactie_id = (int)$_GET['id'];

// ─────────────────────────────────────────────
// Huidige transactie ophalen
// ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT t.*, a.artikelnummer, a.omschrijving, a.categorie
    FROM voorraad_transacties t
    JOIN artikelen a ON a.artikel_id = t.artikel_id
    WHERE t.transactie_id = ?
");
$stmt->bind_param("i", $transactie_id);
$stmt->execute();
$transactie = $stmt->get_result()->fetch_assoc();

if (!$transactie) {
    setFlash("Transactie niet gevonden.", "error");
    header("Location: transacties.php");
    exit;
}

// Artikelen ophalen (geen categorie Administratie)
$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving
    FROM artikelen
    WHERE categorie <> 'Administratie'
    ORDER BY artikelnummer ASC
");

// ─────────────────────────────────────────────
// Formulierverwerking
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artikel_id = (int)($_POST['artikel_id'] ?? 0);
    $type       = trim($_POST['type'] ?? '');
    $aantal     = (int)($_POST['aantal'] ?? 0);
    $opmerking  = trim($_POST['opmerking'] ?? '');

    $geldigeTypes = ['ontvangst', 'uitgifte', 'correctie', 'overboeking'];

    if (!$artikel_id || !in_array($type, $geldigeTypes) || $aantal === 0) {
        setFlash("Controleer alle velden: artikel, type en aantal zijn verplicht.", "error");
    } else {
        // Huidige voorraad ophalen
        $res = $conn->prepare("SELECT aantal FROM voorraad WHERE artikel_id = ?");
        $res->bind_param("i", $artikel_id);
        $res->execute();
        $res->bind_result($huidig);
        $res->fetch();
        $res->close();

        // Oud aantal & type terugdraaien vóór wijziging
        $oudType   = $transactie['type'];
        $oudAantal = (int)$transactie['aantal'];
        $voorraadNaUndo = $huidig;

        switch ($oudType) {
            case 'ontvangst':
                $voorraadNaUndo -= $oudAantal;
                break;
            case 'uitgifte':
                $voorraadNaUndo += $oudAantal;
                break;
            case 'correctie':
                // correctie overschrijft direct, dus niet terug te draaien op basis van oudAantal
                break;
            case 'overboeking':
                $voorraadNaUndo += $oudAantal;
                break;
        }

        // Nieuw voorraad berekenen
        $nieuwAantal = $voorraadNaUndo;
        switch ($type) {
            case 'ontvangst':
                $nieuwAantal += $aantal;
                break;
            case 'uitgifte':
                $nieuwAantal -= $aantal;
                break;
            case 'correctie':
                $nieuwAantal = $aantal;
                break;
            case 'overboeking':
                $nieuwAantal -= $aantal;
                break;
        }

        // Update transactie
        $upd = $conn->prepare("
            UPDATE voorraad_transacties
            SET artikel_id = ?, type = ?, aantal = ?, opmerking = ?
            WHERE transactie_id = ?
        ");
        $upd->bind_param("isisi", $artikel_id, $type, $aantal, $opmerking, $transactie_id);
        $upd->execute();

        // Update voorraad
        $updateVoorraad = $conn->prepare("UPDATE voorraad SET aantal = ?, laatste_update = NOW() WHERE artikel_id = ?");
        $updateVoorraad->bind_param("ii", $nieuwAantal, $artikel_id);
        $updateVoorraad->execute();

        setFlash("Transactie succesvol bijgewerkt ✅", "success");
        header("Location: transacties.php");
        exit;
    }
}

$pageTitle = "✏️ Transactie bewerken";

ob_start();
?>

<div class="page-header">
    <h2>✏️ Transactie bewerken</h2>
    <a href="transacties.php" class="btn btn-secondary">⬅ Terug naar overzicht</a>
</div>

<div class="card">
    <form method="post" class="form-card">
        <label for="artikel_id">Artikel *</label>
        <select name="artikel_id" id="artikel_id" required>
            <option value="">-- Kies artikel --</option>
            <?php while ($a = $artikelen->fetch_assoc()): ?>
                <option value="<?= $a['artikel_id'] ?>" <?= $a['artikel_id'] == $transactie['artikel_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['artikelnummer']) ?> — <?= htmlspecialchars($a['omschrijving']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="type">Type *</label>
        <select name="type" id="type" required>
            <?php
            $types = [
                'ontvangst' => 'Ontvangst (inkomend)',
                'uitgifte' => 'Uitgifte (uitgaand)',
                'correctie' => 'Correctie (handmatig instellen)',
                'overboeking' => 'Overboeking (naar ander magazijn)',
            ];
            foreach ($types as $value => $label):
            ?>
                <option value="<?= $value ?>" <?= $value == $transactie['type'] ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="aantal">Aantal *</label>
        <input type="number" name="aantal" id="aantal" required min="1" value="<?= htmlspecialchars($transactie['aantal']) ?>">

        <label for="opmerking">Opmerking</label>
        <input type="text" name="opmerking" id="opmerking" value="<?= htmlspecialchars($transactie['opmerking']) ?>">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="transacties.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
