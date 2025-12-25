<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$werkbon_id = intval($_GET['werkbon_id'] ?? 0);
if ($werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: werkbonnen.php");
    exit;
}

// Werkbonstatus ophalen
$stmt = $conn->prepare("SELECT status FROM werkbonnen WHERE werkbon_id = ?");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$status = strtolower($stmt->get_result()->fetch_assoc()['status'] ?? '');
$stmt->close();

if (!$status) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: werkbonnen.php");
    exit;
}

// Magazijnen ophalen
$magazijnen = $conn->query("
    SELECT magazijn_id, naam, type
    FROM magazijnen
    ORDER BY type, naam
");

$errors = [];

if (isset($_POST['opslaan'])) {
    $artikel_id  = intval($_POST['artikel_id'] ?? 0);
    $aantal      = floatval($_POST['aantal'] ?? 0);
    $magazijn_id = intval($_POST['magazijn_id'] ?? 0);

    if ($artikel_id <= 0 || $aantal <= 0 || $magazijn_id <= 0) {
        $errors[] = "Artikel, aantal en magazijn zijn verplicht.";
    }

    if (empty($errors)) {
        // Artikel toevoegen aan werkbon
        $stmt = $conn->prepare("
            INSERT INTO werkbon_artikelen (werkbon_id, artikel_id, aantal)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iid", $werkbon_id, $artikel_id, $aantal);
        $stmt->execute();

        // Alleen voorraad bijwerken bij afgeronde werkbon
        if (in_array($status, ['compleet', 'afgehandeld'])) {
            // Huidige voorraad ophalen uit juiste magazijn
            $res = $conn->prepare("
                SELECT aantal FROM voorraad_magazijn
                WHERE artikel_id = ? AND magazijn_id = ?
            ");
            $res->bind_param("ii", $artikel_id, $magazijn_id);
            $res->execute();
            $res->bind_result($huidig);
            $res->fetch();
            $res->close();

            $nieuwAantal = max(0, (int)$huidig - (int)$aantal);

            // Update voorraad in dat magazijn
            $upd = $conn->prepare("
                UPDATE voorraad_magazijn
                SET aantal = ?, laatste_update = NOW()
                WHERE artikel_id = ? AND magazijn_id = ?
            ");
            $upd->bind_param("iii", $nieuwAantal, $artikel_id, $magazijn_id);
            $upd->execute();

            // Log transactie
            $type = 'verkoop';
            $opmerking = "Verbruik via werkbon #{$werkbon_id}";

            $log = $conn->prepare("
                INSERT INTO voorraad_transacties (artikel_id, magazijn_id, datum, type, aantal, opmerking)
                VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            $log->bind_param("iisis", $artikel_id, $magazijn_id, $type, $aantal, $opmerking);
            $log->execute();
        }

        setFlash("Artikel toegevoegd aan werkbon ✅", "success");
        header("Location: werkbon_detail.php?id=" . $werkbon_id);
        exit;
    }

    foreach ($errors as $err) setFlash($err, "error");
}

ob_start();
?>
<div class="page-header">
    <h2>Artikel toevoegen aan werkbon</h2>
    <div class="header-actions">
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <form method="post" class="form-styled">
        <label for="artikel_zoek">Zoek artikel (nummer of omschrijving)</label>
        <input type="text" id="artikel_zoek" placeholder="Begin met typen...">

        <label for="artikel_id">Gevonden artikelen</label>
        <select name="artikel_id" id="artikel_id" required>
            <option value="">-- Zoek en selecteer artikel --</option>
        </select>

        <label for="aantal">Aantal*</label>
        <input type="number" step="0.01" min="0" name="aantal" id="aantal" required>

        <label for="magazijn_id">Magazijn *</label>
        <select name="magazijn_id" id="magazijn_id" required>
            <option value="">-- Selecteer magazijn --</option>
            <?php while ($m = $magazijnen->fetch_assoc()): ?>
                <option value="<?= $m['magazijn_id'] ?>">
                    <?= htmlspecialchars($m['naam']) ?> (<?= $m['type'] ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <div class="form-actions" style="justify-content:flex-start;">
            <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
            <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<script>
// Live zoeken
document.getElementById('artikel_zoek').addEventListener('input', function() {
    const zoekterm = this.value.trim();
    const dropdown = document.getElementById('artikel_id');

    if (zoekterm.length < 2) {
        dropdown.innerHTML = '<option value="">-- Zoek en selecteer artikel --</option>';
        return;
    }

    fetch('zoek_artikelen.php?q=' + encodeURIComponent(zoekterm))
        .then(res => res.json())
        .then(data => {
            dropdown.innerHTML = '<option value="">-- Selecteer artikel --</option>';
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.artikel_id;
                opt.textContent = `${item.artikelnummer} - ${item.omschrijving} (€ ${item.verkoopprijs})`;
                dropdown.appendChild(opt);
            });
        })
        .catch(err => console.error('Fout bij zoeken:', err));
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Artikel toevoegen aan werkbon";
include __DIR__ . "/template/template.php";
