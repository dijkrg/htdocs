<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

/* ==============================
   📌 Helperfuncties
============================== */
function formatDateNL($date) {
    if (!$date || $date === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d-m-Y') : '';
}
function parseDateToDB($date) {
    if (!$date) return null;
    $dt = DateTime::createFromFormat('d-m-Y', $date);
    return $dt ? $dt->format('Y-m-d') : null;
}

/* ==============================
   📌 Werkbon ophalen
============================== */
$werkbon_id = intval($_GET['id'] ?? 0);
if ($werkbon_id <= 0) {
    setFlash("❌ Ongeldig werkbon ID.", "error");
    header("Location: werkbonnen.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM werkbonnen WHERE werkbon_id = ?");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$werkbon) {
    setFlash("❌ Werkbon niet gevonden.", "error");
    header("Location: werkbonnen.php");
    exit;
}

/* ==============================
   📌 Dropdown data
============================== */
$klantenRes  = $conn->query("SELECT klant_id, debiteurnummer, bedrijfsnaam FROM klanten ORDER BY debiteurnummer ASC");
$monteursRes = $conn->query("SELECT medewerker_id, voornaam, achternaam FROM medewerkers WHERE rol='Monteur' ORDER BY achternaam ASC");
$typeRes     = $conn->query("SELECT id, naam FROM type_werkzaamheden ORDER BY naam ASC");
$typeOptions = $typeRes ? $typeRes->fetch_all(MYSQLI_ASSOC) : [];

/* ==============================
   📌 Formulier verwerken (UPDATE)
============================== */
if (isset($_POST['opslaan'])) {
    $klant_id      = (int)($_POST['klant_id'] ?? 0);
    $werkadres_id  = $_POST['werkadres_id'] !== "" ? (int)$_POST['werkadres_id'] : null;
    $monteur_id    = $_POST['monteur_id'] !== "" ? (int)$_POST['monteur_id'] : null;
    $uitvoerdatum  = parseDateToDB($_POST['uitvoerdatum'] ?? null);
    $voorkeurdatum = parseDateToDB($_POST['voorkeurdatum'] ?? null);
    $status        = $_POST['status'] ?? "Klaargezet";
    $type_id       = $_POST['type_werkzaamheden_id'] !== "" ? (int)$_POST['type_werkzaamheden_id'] : null;
    $omschrijving  = $_POST['omschrijving'] ?? "";
    $starttijd     = $_POST['starttijd'] ?: null;
    $eindtijd      = $_POST['eindtijd'] ?: null;

    if ($klant_id === 0) {
        setFlash("❌ Selecteer een geldige klant.", "error");
        header("Location: werkbon_bewerk.php?id=".$werkbon_id);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE werkbonnen SET 
            klant_id = ?, 
            werkadres_id = ?, 
            monteur_id = ?, 
            uitvoerdatum = ?, 
            voorkeurdatum = ?, 
            status = ?, 
            type_werkzaamheden_id = ?, 
            omschrijving = ?,
            starttijd = ?, 
            eindtijd = ?
        WHERE werkbon_id = ?
    ");
    $stmt->bind_param(
        "iiisssisssi",
        $klant_id,
        $werkadres_id,
        $monteur_id,
        $uitvoerdatum,
        $voorkeurdatum,
        $status,
        $type_id,
        $omschrijving,
        $starttijd,
        $eindtijd,
        $werkbon_id
    );

    if ($stmt->execute()) {
        setFlash("✅ Werkbon succesvol bijgewerkt.", "success");
        header("Location: werkbon_detail.php?id=".$werkbon_id);
        exit;
    } else {
        setFlash("❌ Fout bij bijwerken: " . $stmt->error, "error");
    }
    $stmt->close();
}

/* ==============================
   📌 Formulier tonen
============================== */
ob_start();
?>
<div class="page-header">
    <h2>Werkbon bewerken #<?= htmlspecialchars($werkbon['werkbonnummer']) ?></h2>
</div>

<form method="post" class="werkbon-form">
    <div class="two-column-form">
        <!-- Linker kolom -->
        <div class="card left-col">
            <h3>Werkbon gegevens</h3>

            <label>Werkbonnummer</label>
            <input type="text" value="<?= htmlspecialchars($werkbon['werkbonnummer']) ?>" readonly>

            <label>Type werkzaamheden</label>
            <select name="type_werkzaamheden_id">
                <option value="">-- Kies type --</option>
                <?php foreach ($typeOptions as $type): ?>
                    <option value="<?= $type['id'] ?>" <?= $type['id']==$werkbon['type_werkzaamheden_id']?'selected':'' ?>>
                        <?= htmlspecialchars($type['naam']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Status</label>
            <select name="status" id="status">
                <?php foreach (["Klaargezet","Ingepland","Compleet","Afgehandeld"] as $status): ?>
                    <option value="<?= $status ?>" <?= $status==$werkbon['status']?'selected':'' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>

            <div id="inplan-velden" style="<?= $werkbon['status']=='Ingepland' ? '' : 'display:none;' ?>">
                <label>Monteur</label>
                <select name="monteur_id">
                    <option value="">-- Geen monteur --</option>
                    <?php while ($m = $monteursRes->fetch_assoc()): ?>
                        <option value="<?= $m['medewerker_id'] ?>" <?= $m['medewerker_id']==$werkbon['monteur_id']?'selected':'' ?>>
                            <?= htmlspecialchars($m['voornaam']." ".$m['achternaam']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Uitvoerdatum</label>
                <input type="text" name="uitvoerdatum" value="<?= formatDateNL($werkbon['uitvoerdatum']) ?>" placeholder="dd-mm-jjjj">

                <label>Starttijd</label>
                <input type="time" name="starttijd" id="starttijd" value="<?= htmlspecialchars($werkbon['starttijd']) ?>">

                <label>Eindtijd</label>
                <input type="time" name="eindtijd" id="eindtijd" value="<?= htmlspecialchars($werkbon['eindtijd']) ?>">
            </div>

            <label>Voorkeurdatum</label>
            <input type="text" name="voorkeurdatum" value="<?= formatDateNL($werkbon['voorkeurdatum']) ?>" placeholder="dd-mm-jjjj">
        </div>

        <!-- Rechter kolom -->
        <div class="card right-col">
            <h3>Klant & Werkadres</h3>

            <label>Klant</label>
            <select name="klant_id" id="klantSelect" required>
                <option value="">-- Selecteer klant --</option>
                <?php while ($k = $klantenRes->fetch_assoc()): ?>
                    <option value="<?= $k['klant_id'] ?>" <?= $k['klant_id']==$werkbon['klant_id']?'selected':'' ?>>
                        <?= htmlspecialchars($k['debiteurnummer']." - ".$k['bedrijfsnaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Werkadres</label>
            <select name="werkadres_id" id="werkadresSelect">
                <option value="">-- Laden... --</option>
            </select>

            <label>Omschrijving</label>
            <textarea name="omschrijving"><?= htmlspecialchars($werkbon['omschrijving']) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">⬅ Annuleren</a>
    </div>
</form>

<script>
// 📅 Inplanvelden tonen/verbergen
document.getElementById('status').addEventListener('change', function() {
    document.getElementById('inplan-velden').style.display = this.value === 'Ingepland' ? '' : 'none';
});

// ⏰ Automatisch eindtijd +30 min
document.getElementById('starttijd').addEventListener('change', function() {
    const start = this.value;
    if (start) {
        const [h, m] = start.split(':');
        const startDate = new Date(0, 0, 0, h, m);
        startDate.setMinutes(startDate.getMinutes() + 30);
        document.getElementById('eindtijd').value = startDate.toTimeString().slice(0,5);
    }
});

// Werkadres dropdown vullen
document.addEventListener('DOMContentLoaded', function() {
    let klantId = document.getElementById('klantSelect').value;
    let werkadresSelect = document.getElementById('werkadresSelect');
    let huidigeWerkadres = <?= $werkbon['werkadres_id'] ? (int)$werkbon['werkadres_id'] : 'null' ?>;

    if (klantId) {
        fetch('/ajax/get_werkadressen.php?klant_id=' + klantId)
            .then(res => res.json())
            .then(data => {
                werkadresSelect.innerHTML = '<option value="">-- Selecteer werkadres --</option>';

                data.forEach(wa => {
                    let opt = document.createElement('option');
                    opt.value = wa.id;         // ← FIX
                    opt.textContent = wa.adres; // ← FIX
                    if (wa.id == huidigeWerkadres) opt.selected = true;
                    werkadresSelect.appendChild(opt);
                });
            });
    } else {
        werkadresSelect.innerHTML = '<option value="">-- Eerst klant kiezen --</option>';
    }
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Werkbon bewerken";
include __DIR__ . "/template/template.php";
