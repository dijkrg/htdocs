<?php
require_once __DIR__ . '/includes/init.php';

/* =========================================
   📌 Helperfuncties voor datumformat
========================================= */
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

/* =========================================
   📌 Dropdown data ophalen
========================================= */
$klantenRes = $conn->query("SELECT klant_id, debiteurnummer, bedrijfsnaam FROM klanten ORDER BY debiteurnummer ASC");
$monteursRes = $conn->query("SELECT medewerker_id, voornaam, achternaam FROM medewerkers WHERE rol='Monteur' ORDER BY achternaam ASC");
$typeRes = $conn->query("SELECT id, naam FROM type_werkzaamheden ORDER BY naam ASC");
$typeOptions = $typeRes ? $typeRes->fetch_all(MYSQLI_ASSOC) : [];

/* =========================================
   📌 Volgend werkbonnummer bepalen
========================================= */
$res = $conn->query("SELECT MAX(werkbonnummer) as maxnr FROM werkbonnen");
$row = $res->fetch_assoc();
$nieuwNummer = $row['maxnr'] ? $row['maxnr'] + 1 : 50582;

/* =========================================
   📌 Formulier verwerken
========================================= */
if (isset($_POST['opslaan'])) {
    $werkbonnummer = $_POST['werkbonnummer'] ?? $nieuwNummer;
    $klant_id      = isset($_POST['klant_id']) ? (int)$_POST['klant_id'] : 0;
    $werkadres_id  = isset($_POST['werkadres_id']) && $_POST['werkadres_id'] !== "" ? (int)$_POST['werkadres_id'] : null;
    $monteur_id    = isset($_POST['monteur_id']) && $_POST['monteur_id'] !== "" ? (int)$_POST['monteur_id'] : null;
    $uitvoerdatum  = parseDateToDB($_POST['uitvoerdatum'] ?? null);
    $voorkeurdatum = parseDateToDB($_POST['voorkeurdatum'] ?? null);
    $status        = $_POST['status'] ?? "Klaargezet";
    $type_id       = isset($_POST['type_werkzaamheden_id']) && $_POST['type_werkzaamheden_id'] !== "" ? (int)$_POST['type_werkzaamheden_id'] : null;
    $omschrijving  = $_POST['omschrijving'] ?? "";

    if ($klant_id === 0) {
        setFlash("❌ Selecteer een geldige klant.", "error");
        header("Location: werkbon_toevoegen.php");
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO werkbonnen 
        (werkbonnummer, klant_id, werkadres_id, monteur_id, uitvoerdatum, voorkeurdatum, status, type_werkzaamheden_id, omschrijving, datum) 
        VALUES (?,?,?,?,?,?,?,?,?,NOW())
    ");
    $stmt->bind_param(
        "siiisssis",
        $werkbonnummer,
        $klant_id,
        $werkadres_id,
        $monteur_id,
        $uitvoerdatum,
        $voorkeurdatum,
        $status,
        $type_id,
        $omschrijving
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        setFlash("✅ Werkbon succesvol toegevoegd.", "success");
        header("Location: werkbon_detail.php?id=" . $newId);
        exit;
    } else {
        setFlash("❌ Fout bij opslaan: " . $stmt->error, "error");
    }
    $stmt->close();
}

/* =========================================
   📌 Formulier tonen
========================================= */
ob_start();
?>
<div class="page-header">
    <h2>Nieuwe Werkbon</h2>
</div>

<form method="post" class="werkbon-form">

    <div class="two-column-form">
        <!-- Linker kolom -->
        <div class="card left-col">
            <h3>Werkbon gegevens</h3>

            <label>Werkbonnummer</label>
            <input type="text" name="werkbonnummer" value="<?= htmlspecialchars($nieuwNummer) ?>" readonly>

            <label>Type werkzaamheden</label>
            <select name="type_werkzaamheden_id" required>
                <option value="">-- Kies type --</option>
                <?php foreach ($typeOptions as $type): ?>
                    <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['naam']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Status</label>
            <select name="status">
                <option value="Klaargezet">Klaargezet</option>
                <option value="Ingepland">Ingepland</option>
                <option value="Compleet">Compleet</option>
                <option value="Afgehandeld">Afgehandeld</option>
            </select>

            <label>Monteur</label>
            <select name="monteur_id">
                <option value="">-- Geen monteur --</option>
                <?php while ($m = $monteursRes->fetch_assoc()): ?>
                    <option value="<?= $m['medewerker_id'] ?>">
                        <?= htmlspecialchars($m['voornaam']." ".$m['achternaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Uitvoerdatum</label>
            <input type="text" name="uitvoerdatum" placeholder="dd-mm-jjjj">

            <label>Voorkeurdatum</label>
            <input type="text" name="voorkeurdatum" placeholder="dd-mm-jjjj">
        </div>

        <!-- Rechter kolom -->
        <div class="card right-col">
            <h3>Klant & Werkadres</h3>

            <label>Klant</label>
            <select name="klant_id" id="klantSelect" required>
                <option value="">-- Selecteer klant --</option>
                <?php while ($k = $klantenRes->fetch_assoc()): ?>
                    <option value="<?= $k['klant_id'] ?>">
                        <?= htmlspecialchars($k['debiteurnummer']." - ".$k['bedrijfsnaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Werkadres</label>
            <select name="werkadres_id" id="werkadresSelect">
                <option value="">-- Eerst klant kiezen --</option>
            </select>

            <label>Omschrijving</label>
            <textarea name="omschrijving" placeholder="Korte beschrijving van de werkzaamheden"></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
        <a href="werkbonnen.php" class="btn btn-secondary">⬅ Annuleren</a>
    </div>
</form>

<script>
// Dynamisch werkadres ophalen
document.getElementById('klantSelect').addEventListener('change', function() {
    let klantId = this.value;
    let werkadresSelect = document.getElementById('werkadresSelect');
    werkadresSelect.innerHTML = '<option value="">-- Laden... --</option>';

    if (klantId) {
        fetch('/ajax/get_werkadressen.php?klant_id=' + klantId)
            .then(res => res.json())
            .then(data => {
                werkadresSelect.innerHTML = '<option value="">-- Selecteer werkadres --</option>';
		data.forEach(wa => {
                    let opt = document.createElement('option');
		    opt.value = wa.id; // ← JUIST!
		    opt.textContent = wa.adres; // ← JUIST!
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
$pageTitle = "Nieuwe Werkbon";
include __DIR__ . "/template/template.php";
