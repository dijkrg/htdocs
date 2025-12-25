<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$werkbon_id = intval($_GET['werkbon_id'] ?? 0);
$object_id  = intval($_GET['object_id'] ?? 0);

if ($object_id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldig object of werkbon.", "error");
    header("Location: /monteur/index.php");
    exit;
}

/* ------------------------------------------------------------
   OBJECT + KLANT + WERKADRES
------------------------------------------------------------ */
$stmt = $conn->prepare("
    SELECT o.*, 
           k.bedrijfsnaam AS klantnaam,
           wa.bedrijfsnaam AS wa_naam,
           wa.adres AS wa_adres,
           wa.postcode AS wa_postcode,
           wa.plaats AS wa_plaats
    FROM objecten o
    LEFT JOIN klanten k ON k.klant_id = o.klant_id
    LEFT JOIN werkadressen wa ON wa.werkadres_id = o.werkadres_id
    WHERE o.object_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$object = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$object) {
    setFlash("Object niet gevonden.", "error");
    header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id);
    exit;
}

/* ------------------------------------------------------------
   RESULTAAT OPTIES UIT object_status
------------------------------------------------------------ */
$res = $conn->query("SELECT naam FROM object_status ORDER BY naam ASC");
$resultaten = array_column($res->fetch_all(MYSQLI_ASSOC), 'naam');

/* ------------------------------------------------------------
   VERWERKING
------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resultaat     = trim($_POST['resultaat']);
    $opmerking     = trim($_POST['opmerking']);
    $locatie       = trim($_POST['locatie']);
    $verdieping    = trim($_POST['verdieping']);
    $merk          = trim($_POST['merk']);
    $type          = trim($_POST['type']);
    $fabricagejaar = ($_POST['fabricagejaar'] !== '') ? intval($_POST['fabricagejaar']) : null;
    $nen671_3      = ($_POST['beproeving_nen671_3'] !== '') ? intval($_POST['beproeving_nen671_3']) : null;
    $datum_onderhoud = date("Y-m-d");

    /* FOTO UPLOAD ---------------------- */
    $afbeelding = $object['afbeelding'];
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (in_array($_FILES['foto']['type'], $allowed)) {

            $dir = __DIR__ . '/../uploads/objecten/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $new = "insp_{$object_id}_" . time() . "." . $ext;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $new)) {
                $afbeelding = "uploads/objecten/" . $new;
            }
        }
    }

    /* UPDATE OBJECT -------------------- */
    $stmt = $conn->prepare("
        UPDATE objecten
        SET resultaat=?, datum_onderhoud=?, opmerkingen=?, 
            locatie=?, verdieping=?, merk=?, type=?, fabricagejaar=?, 
            beproeving_nen671_3=?, afbeelding=?
        WHERE object_id=?
    ");
    $stmt->bind_param(
        "sssssssissi",
        $resultaat,
        $datum_onderhoud,
        $opmerking,
        $locatie,
        $verdieping,
        $merk,
        $type,
        $fabricagejaar,
        $nen671_3,
        $afbeelding,
        $object_id
    );
    $stmt->execute();
    $stmt->close();

    /* KOPPELING MET WERKBON */
    $chk = $conn->prepare("SELECT 1 FROM werkbon_objecten WHERE werkbon_id=? AND object_id=?");
    $chk->bind_param("ii", $werkbon_id, $object_id);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();

    if (!$exists) {
        $ins = $conn->prepare("INSERT INTO werkbon_objecten (werkbon_id, object_id) VALUES (?,?)");
        $ins->bind_param("ii", $werkbon_id, $object_id);
        $ins->execute();
        $ins->close();
    }

    setFlash("✔ Inspectie succesvol opgeslagen", "success");
    header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#objecten");
    exit;
}

/* ------------------------------------------------------------
   FRONTEND
------------------------------------------------------------ */
$pageTitle = "Object inspecteren";
ob_start();
?>

<style>
.page-head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

/* 50/50 voor KLANT | WERKADRES */
.cols-50 {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}

/* 40/60 voor FOTO | OBJECTGEGEVENS */
.cols-30-70 {
    display:grid;
    grid-template-columns:40% 60%;
    gap:15px;
}

.obj-grid {
    display:grid;
    grid-template-columns:150px 1fr;
    gap:8px;
    padding:4px 0;
}

.readonly input, .readonly textarea, .readonly select {
    background:#f3f3f3;
    pointer-events:none;
    color:#666;
}

/* Mobiel */
@media(max-width:850px){
    .cols-50      { grid-template-columns:1fr; }
    .cols-30-70   { grid-template-columns:1fr; }
}
</style>

<div class="page-head">
    <h2>🔧 Inspectie — <?= htmlspecialchars($object['code']) ?></h2>

    <div>
        <button type="button" class="btn" onclick="enableEdit()">✏️ Gegevens wijzigen</button>
        <button form="inspForm" type="submit" class="btn-primary">✔ Opslaan</button>
        <a class="btn btn-secondary" href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>">⬅ Terug</a>
    </div>
</div>

<!-- KLANT & WERKADRES (50/50 LAYOUT) -->
<div class="cols-50">

    <div class="card">
        <h3>Klant</h3>
        <p><strong><?= htmlspecialchars($object['klantnaam']) ?></strong></p>
    </div>

    <div class="card">
        <h3>Werkadres</h3>
        <?php if ($object['wa_naam']): ?>
            <p>
                <strong><?= htmlspecialchars($object['wa_naam']) ?></strong><br>
                <?= htmlspecialchars($object['wa_adres']) ?><br>
                <?= htmlspecialchars($object['wa_postcode']) ?> <?= htmlspecialchars($object['wa_plaats']) ?>
            </p>
        <?php else: ?>
            <p><em>Geen werkadres gekoppeld</em></p>
        <?php endif; ?>
    </div>

</div>

<form id="inspForm" method="post" enctype="multipart/form-data">

<!-- FOTO | OBJECTGEGEVENS (30/70 LAYOUT) -->
<div class="cols-30-70" style="margin-top:15px;">

    <!-- FOTO -->
    <div class="card">
        <h3>Foto</h3>

        <?php if ($object['afbeelding']): ?>
            <img src="/<?= $object['afbeelding'] ?>" style="width:100%;max-width:220px;border-radius:8px;">
        <?php endif; ?>

        <input type="file" name="foto" accept="image/*" capture="environment" style="margin-top:10px;">
    </div>

    <!-- OBJECTGEGEVENS -->
    <div class="card readonly" id="objFields">
        <h3>Objectgegevens</h3>

        <div class="obj-grid"><strong>Code</strong>
            <input value="<?= htmlspecialchars($object['code']) ?>" readonly>
        </div>

        <div class="obj-grid"><strong>Omschrijving</strong>
            <input value="<?= htmlspecialchars($object['omschrijving']) ?>" readonly>
        </div>

        <div class="obj-grid"><strong>Merk</strong>
            <input name="merk" value="<?= htmlspecialchars($object['merk']) ?>">
        </div>

        <div class="obj-grid"><strong>Fabricagejaar</strong>
            <input type="number" name="fabricagejaar" value="<?= htmlspecialchars($object['fabricagejaar']) ?>">
        </div>

        <div class="obj-grid"><strong>Laatste onderhoud</strong>
            <input value="<?= $object['datum_onderhoud'] ? date('d-m-Y', strtotime($object['datum_onderhoud'])) : '-' ?>" readonly>
        </div>

        <div class="obj-grid"><strong>NEN671-3</strong>
            <input type="number" name="beproeving_nen671_3" value="<?= htmlspecialchars($object['beproeving_nen671_3']) ?>">
        </div>

        <div class="obj-grid"><strong>Locatie</strong>
            <input name="locatie" value="<?= htmlspecialchars($object['locatie']) ?>">
        </div>

        <div class="obj-grid"><strong>Verdieping</strong>
            <input name="verdieping" value="<?= htmlspecialchars($object['verdieping']) ?>">
        </div>

    </div>
</div>

<!-- RESULTAAT -->
<div class="card" style="margin-top:15px;">
    <h3>Inspectie resultaat</h3>

    <label>Resultaat*</label>
    <select name="resultaat" id="resultaatSelect" required>
        <option value="">-- Kies resultaat --</option>
        <?php foreach ($resultaten as $r): ?>
            <option value="<?= $r ?>" <?= ($object['resultaat']===$r?'selected':'') ?>><?= $r ?></option>
        <?php endforeach; ?>
    </select>

    <div id="opmerkingBlock" style="display:none;margin-top:10px;">
        <label>Opmerkingen / Advies*</label>
        <textarea name="opmerking" id="opmerkingField" rows="4"
        placeholder="Beschrijving verplicht bij afkeur, storing of revisie"><?= 
        htmlspecialchars($object['opmerkingen']) ?></textarea>
    </div>
</div>

</form>

<script>
function enableEdit(){
    document.getElementById("objFields").classList.remove("readonly");
}

/* ███ Toon/verberg opmerkingen afhankelijk van resultaat */
function updateOpmerking() {
    const val = document.getElementById('resultaatSelect').value;
    const block = document.getElementById('opmerkingBlock');

    const verplicht = ['Afkeur', 'Niet gebruiksklaar', 'Reparatie', 'Revisie', 'Storing'];

    if (verplicht.includes(val)) {
        block.style.display = 'block';
        document.getElementById('opmerkingField').required = true;
    } else {
        block.style.display = 'none';
        document.getElementById('opmerkingField').required = false;
    }
}

document.getElementById('resultaatSelect').addEventListener('change', updateOpmerking);
window.onload = updateOpmerking;
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/monteur_template.php';
?>
