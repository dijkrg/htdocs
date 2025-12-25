<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang tot overboekingen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🔄 Voorraad overboeken";

// ─────────────────────────────
// Magazijnen ophalen
// ─────────────────────────────
$magazijnen = $conn->query("SELECT magazijn_id, naam, type FROM magazijnen ORDER BY naam ASC");

// ─────────────────────────────
// Formulierverwerking
// ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artikel_id    = (int)($_POST['artikel_id'] ?? 0);
    $bron_magazijn = (int)($_POST['bron_magazijn'] ?? 0);
    $doel_magazijn = (int)($_POST['doel_magazijn'] ?? 0);
    $aantal        = (int)($_POST['aantal'] ?? 0);
    $opmerking     = trim($_POST['opmerking'] ?? '');

    // Validatie
    if ($artikel_id <= 0 || $bron_magazijn <= 0 || $doel_magazijn <= 0 || $aantal <= 0) {
        setFlash("Vul alle velden correct in.", "error");
        header("Location: transactie_overboeken.php");
        exit;
    }

    if ($bron_magazijn === $doel_magazijn) {
        setFlash("Bron- en doelmagazijn mogen niet hetzelfde zijn.", "error");
        header("Location: transactie_overboeken.php");
        exit;
    }

    $conn->begin_transaction();

    try {
        // ─────────────────────────────
        // 1️⃣ Voorraad BRON ophalen
        // ─────────────────────────────
        $bronRes = $conn->prepare("
            SELECT aantal FROM voorraad_magazijn
            WHERE artikel_id = ? AND magazijn_id = ?
        ");
        $bronRes->bind_param("ii", $artikel_id, $bron_magazijn);
        $bronRes->execute();
        $bronRes->bind_result($bronVoorraad);
        $bronRes->fetch();
        $bronRes->close();

        if ($bronVoorraad === null) $bronVoorraad = 0;
        $nieuwBron = $bronVoorraad - $aantal;

        // (negatieve voorraad toegestaan volgens jouw voorkeur)
        $updBron = $conn->prepare("
            INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE aantal = VALUES(aantal), laatste_update = NOW()
        ");
        $updBron->bind_param("iii", $artikel_id, $bron_magazijn, $nieuwBron);
        $updBron->execute();

        // ─────────────────────────────
        // 2️⃣ Voorraad DOEL ophalen
        // ─────────────────────────────
        $doelRes = $conn->prepare("
            SELECT aantal FROM voorraad_magazijn
            WHERE artikel_id = ? AND magazijn_id = ?
        ");
        $doelRes->bind_param("ii", $artikel_id, $doel_magazijn);
        $doelRes->execute();
        $doelRes->bind_result($doelVoorraad);
        $doelRes->fetch();
        $doelRes->close();

        if ($doelVoorraad === null) $doelVoorraad = 0;
        $nieuwDoel = $doelVoorraad + $aantal;

        $updDoel = $conn->prepare("
            INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE aantal = VALUES(aantal), laatste_update = NOW()
        ");
        $updDoel->bind_param("iii", $artikel_id, $doel_magazijn, $nieuwDoel);
        $updDoel->execute();

        // ─────────────────────────────
        // 3️⃣ Loggen in voorraad_transacties
        // ─────────────────────────────
        $log = $conn->prepare("
            INSERT INTO voorraad_transacties (artikel_id, magazijn_id, datum, type, aantal, opmerking, status)
            VALUES (?, ?, NOW(), 'overboeking', ?, ?, 'geboekt')
        ");

        $opmerkingBron = "Overboeking naar magazijn ID {$doel_magazijn}. " . $opmerking;
        $log->bind_param("iiis", $artikel_id, $bron_magazijn, $aantal, $opmerkingBron);
        $log->execute();

        $opmerkingDoel = "Ontvangen via overboeking van magazijn ID {$bron_magazijn}. " . $opmerking;
        $log->bind_param("iiis", $artikel_id, $doel_magazijn, $aantal, $opmerkingDoel);
        $log->execute();

        $conn->commit();

        // ─────────────────────────────
        // ✅ Flash melding
        // ─────────────────────────────
        $bronNaam = $conn->query("SELECT naam FROM magazijnen WHERE magazijn_id = {$bron_magazijn}")->fetch_assoc()['naam'];
        $doelNaam = $conn->query("SELECT naam FROM magazijnen WHERE magazijn_id = {$doel_magazijn}")->fetch_assoc()['naam'];
        setFlash("Overboeking succesvol van <strong>{$bronNaam}</strong> naar <strong>{$doelNaam}</strong> ✅", "success");
        header("Location: voorraad.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        setFlash("Fout bij overboeken: " . $e->getMessage(), "error");
        header("Location: transactie_overboeken.php");
        exit;
    }
}

ob_start();
?>

<div class="page-header">
    <h2>🔄 Voorraad overboeken</h2>
    <div class="header-actions">
        <a href="voorraad.php" class="btn btn-secondary">⬅ Terug naar voorraad</a>
    </div>
</div>

<div class="card">
    <form method="post" class="form-card">
        <label for="artikel_id">Artikel *</label>
        <select name="artikel_id" id="artikel_id" required>
            <option value="">-- Kies artikel --</option>
            <?php
            $artikelen = $conn->query("SELECT artikel_id, artikelnummer, omschrijving FROM artikelen ORDER BY artikelnummer ASC");
            while ($a = $artikelen->fetch_assoc()):
            ?>
                <option value="<?= $a['artikel_id'] ?>">
                    <?= htmlspecialchars($a['artikelnummer'] . ' — ' . $a['omschrijving']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <div class="grid-2" style="display:grid; gap:12px; grid-template-columns: 1fr 1fr;">
            <div>
                <label for="bron_magazijn">Van magazijn *</label>
                <select name="bron_magazijn" id="bron_magazijn" required>
                    <option value="">-- Kies bronmagazijn --</option>
                    <?php
                    mysqli_data_seek($magazijnen, 0);
                    while ($m = $magazijnen->fetch_assoc()): ?>
                        <option value="<?= (int)$m['magazijn_id'] ?>">
                            <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="doel_magazijn">Naar magazijn *</label>
                <select name="doel_magazijn" id="doel_magazijn" required>
                    <option value="">-- Kies doelmagazijn --</option>
                    <?php
                    mysqli_data_seek($magazijnen, 0);
                    while ($m = $magazijnen->fetch_assoc()): ?>
                        <option value="<?= (int)$m['magazijn_id'] ?>">
                            <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <label for="aantal">Aantal *</label>
        <input type="number" name="aantal" id="aantal" required min="1" step="1">

        <label for="opmerking">Opmerking</label>
        <input type="text" name="opmerking" id="opmerking" placeholder="Bijv. overplaatsing van bestelorder of servicebus">

        <div class="form-actions" style="margin-top:15px;">
            <button type="submit" class="btn btn-primary">📦 Overboeken</button>
            <a href="voorraad.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
