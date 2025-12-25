<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$object_id = (int)($_GET['id'] ?? 0);
if ($object_id <= 0) {
    setFlash("Ongeldig object ID.", "error");
    header("Location: objecten.php");
    exit;
}

// ✅ Object ophalen
$stmt = $conn->prepare("SELECT * FROM objecten WHERE object_id = ?");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$object = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$object) {
    setFlash("Object niet gevonden.", "error");
    header("Location: objecten.php");
    exit;
}

// ✅ Klanten ophalen
$klanten = $conn->query("SELECT klant_id, debiteurnummer, bedrijfsnaam FROM klanten ORDER BY bedrijfsnaam ASC");

// ✅ Resultaatopties
$resultaat_opties = [];
$resR = $conn->query("SELECT naam FROM object_resultaat ORDER BY naam ASC");
if ($resR && $resR->num_rows > 0) {
    while ($r = $resR->fetch_assoc()) $resultaat_opties[] = $r['naam'];
} else {
    $resultaat_opties = ['Onderhouden','Revisie','Servicewissel','Niet gebruiksklaar','Afkeur','Storing','Reparatie','Nieuw geleverd'];
}

// ✅ POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $val) $object[$key] = $val;

    $code               = trim($_POST['code']);
    $omschrijving       = trim($_POST['omschrijving']);
    $klant_id           = (int)$_POST['klant_id'];
    $werkadres_id       = !empty($_POST['werkadres_id']) ? (int)$_POST['werkadres_id'] : null;
    $resultaat          = trim($_POST['resultaat']);
    $datum_installatie  = !empty($_POST['datum_installatie']) ? $_POST['datum_installatie'] : null;
    $datum_onderhoud    = !empty($_POST['datum_onderhoud']) ? $_POST['datum_onderhoud'] : null;
    $merk               = trim($_POST['merk']);
    $type               = trim($_POST['type']);
    $rijkstypekeur      = trim($_POST['rijkstypekeur']);
    $fabricagejaar      = !empty($_POST['fabricagejaar']) ? (int)$_POST['fabricagejaar'] : null;
    $beproeving_nen671_3= !empty($_POST['beproeving_nen671_3']) ? (int)$_POST['beproeving_nen671_3'] : null;
    $verdieping         = trim($_POST['verdieping']);
    $locatie            = trim($_POST['locatie']);
    $opmerkingen        = trim($_POST['opmerkingen']);
    $uitgebreid_onderhoud = isset($_POST['uitgebreid_onderhoud']) ? 1 : 0;
    $gereviseerd          = isset($_POST['gereviseerd']) ? 1 : 0;

    if ($rijkstypekeur === '' || $rijkstypekeur === '0') $rijkstypekeur = null;
    if (empty($fabricagejaar) || $fabricagejaar === 0) $fabricagejaar = null;
    if (empty($beproeving_nen671_3) || $beproeving_nen671_3 === 0) $beproeving_nen671_3 = null;

    // ✅ Controle op dubbele code
    $stmtCheck = $conn->prepare("SELECT object_id FROM objecten WHERE code = ? AND object_id <> ?");
    $stmtCheck->bind_param("si", $code, $object_id);
    $stmtCheck->execute();
    $dupRes = $stmtCheck->get_result();
    if ($dupRes->num_rows > 0) {
        setFlash("❌ Code '$code' bestaat al bij een ander object.", "error");
    } else {
        // ✅ Afbeelding upload
        $afbeelding = $object['afbeelding'] ?? null;
        if (!empty($_FILES['afbeelding']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            if (in_array($_FILES['afbeelding']['type'], $allowed, true)) {
                $uploadDir = __DIR__ . '/uploads/objecten/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['afbeelding']['name'], PATHINFO_EXTENSION));
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['afbeelding']['tmp_name'], $target)) {
                    if (!empty($afbeelding) && file_exists(__DIR__ . '/' . $afbeelding)) unlink(__DIR__ . '/' . $afbeelding);
                    $afbeelding = 'uploads/objecten/' . $filename;
                }
            }
        }

        // ✅ Updaten inclusief nieuwe velden
        $stmt = $conn->prepare("
            UPDATE objecten 
            SET code=?, omschrijving=?, klant_id=?, werkadres_id=?, resultaat=?, 
                datum_installatie=?, datum_onderhoud=?, merk=?, type=?, rijkstypekeur=?, 
                fabricagejaar=?, beproeving_nen671_3=?, verdieping=?, locatie=?, opmerkingen=?, 
                afbeelding=?, uitgebreid_onderhoud=?, gereviseerd=?
            WHERE object_id=?
        ");
        $stmt->bind_param(
            "ssissssssiiissssiii",
            $code, $omschrijving, $klant_id, $werkadres_id, $resultaat,
            $datum_installatie, $datum_onderhoud, $merk, $type, $rijkstypekeur,
            $fabricagejaar, $beproeving_nen671_3, $verdieping, $locatie, $opmerkingen,
            $afbeelding, $uitgebreid_onderhoud, $gereviseerd, $object_id
        );

if ($stmt->execute()) {
    setFlash("✅ Object succesvol bijgewerkt.", "success");
    header("Location: objecten.php");
    exit;
        } else {
            setFlash("❌ Fout bij bijwerken: " . $stmt->error, "error");
        }
        $stmt->close();
    }
    $stmtCheck->close();
}

foreach (['rijkstypekeur','fabricagejaar','beproeving_nen671_3'] as $f)
    if (isset($object[$f]) && ($object[$f] === 0 || $object[$f] === '0')) $object[$f] = '';

$pageTitle = "Object bewerken";
ob_start();
?>

<div class="page-header">
    <h2>✏️ Object bewerken — <?= htmlspecialchars($object['code'] ?? '') ?></h2>
</div>

<?php showFlash(); ?>

<form method="post" enctype="multipart/form-data" class="object-form">
    <div class="two-column-form">

        <!-- 🔹 LINKER KOLOM -->
        <div class="card left-col">
            <h3>Technische gegevens</h3>

            <label>Code*</label>
            <input type="text" name="code" value="<?= htmlspecialchars($object['code']) ?>" required>

            <label>Omschrijving*</label>
            <input type="text" name="omschrijving" value="<?= htmlspecialchars($object['omschrijving']) ?>" required>

            <div class="two-cols">
                <div>
                    <label>Type</label>
                    <input type="text" name="type" value="<?= htmlspecialchars($object['type'] ?? '') ?>">
                </div>
                <div>
                    <label>Merk</label>
                    <input type="text" name="merk" value="<?= htmlspecialchars($object['merk'] ?? '') ?>">
                </div>
            </div>

            <div class="two-cols">
                <div>
                    <label>Datum installatie</label>
                    <input type="date" name="datum_installatie" value="<?= htmlspecialchars($object['datum_installatie']) ?>">
                </div>
                <div>
                    <label>Datum onderhoud</label>
                    <input type="date" name="datum_onderhoud" value="<?= htmlspecialchars($object['datum_onderhoud']) ?>">
                </div>
            </div>

            <div class="two-cols">
                <div>
                    <label>Fabricagejaar</label>
                    <input type="number" name="fabricagejaar" min="1900" max="<?= date('Y') ?>" 
                           value="<?= htmlspecialchars($object['fabricagejaar'] ?? '') ?>">
                </div>
                <div>
                    <label>Beproeving NEN671-3</label>
                    <input type="number" name="beproeving_nen671_3" min="1900" max="<?= date('Y') ?>" 
                           value="<?= htmlspecialchars($object['beproeving_nen671_3'] ?? '') ?>">
                </div>
            </div>

            <!-- 🔹 3 kolommen: Rijkstypekeur | Uitgebreid onderhoud | Gereviseerd -->
            <div class="three-cols">
                <div>
                    <label>Rijkstypekeur</label>
                    <input type="text" name="rijkstypekeur" value="<?= htmlspecialchars($object['rijkstypekeur'] ?? '') ?>">
                </div>
                <div class="form-check" style="margin-top:28px;">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="uitgebreid_onderhoud" value="1" 
                            <?= !empty($object['uitgebreid_onderhoud']) ? 'checked' : '' ?>>
                        <span>Uitgebreid onderhoud</span>
                    </label>
                </div>
                <div class="form-check" style="margin-top:28px;">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="gereviseerd" value="1" 
                            <?= !empty($object['gereviseerd']) ? 'checked' : '' ?>>
                        <span>Gereviseerd</span>
                    </label>
                </div>
            </div>

            <div class="two-cols">
                <div>
                    <label>Verdieping</label>
                    <input type="text" name="verdieping" value="<?= htmlspecialchars($object['verdieping'] ?? '') ?>">
                </div>
                <div>
                    <label>Locatie</label>
                    <input type="text" name="locatie" value="<?= htmlspecialchars($object['locatie'] ?? '') ?>">
                </div>
            </div>

            <label>Opmerkingen</label>
            <textarea name="opmerkingen" rows="3"><?= htmlspecialchars($object['opmerkingen'] ?? '') ?></textarea>
        </div>

        <!-- 🔹 RECHTER KOLOM -->
        <div class="card right-col">
            <h3>Klant & locatie</h3>
            <label>Klant*</label>
            <select name="klant_id" id="klantSelect" required>
                <option value="">-- Kies klant --</option>
                <?php
                $klanten->data_seek(0);
                while ($k = $klanten->fetch_assoc()): ?>
                    <option value="<?= $k['klant_id'] ?>" <?= ($k['klant_id'] == $object['klant_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['debiteurnummer'].' - '.$k['bedrijfsnaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Werkadres</label>
            <select name="werkadres_id" id="werkadresSelect">
                <option value="">-- Laden... --</option>
            </select>

            <label>Resultaat*</label>
            <select name="resultaat" required>
                <option value="">-- Kies resultaat --</option>
                <?php foreach ($resultaat_opties as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= ($object['resultaat'] === $opt) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <h3 style="margin-top:18px;">Afbeelding</h3>
            <div class="image-wrap">
                <?php if (!empty($object['afbeelding']) && file_exists(__DIR__ . '/' . $object['afbeelding'])): ?>
                    <img id="previewImg" src="/<?= htmlspecialchars($object['afbeelding']) ?>" alt="Object afbeelding">
                <?php else: ?>
                    <div id="previewPlaceholder" class="img-placeholder">Geen afbeelding</div>
                    <img id="previewImg" src="" alt="" style="display:none;">
                <?php endif; ?>
            </div>
            <input type="file" name="afbeelding" id="afbeeldingInput" accept="image/*" style="margin-top:8px;">
        </div>
    </div>

<div class="form-actions">
    <button type="submit" class="btn">💾 Opslaan</button>
    <a href="objecten.php" class="btn btn-secondary">⬅ Terug</a>
</div>
</form>

<style>
.two-column-form { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.two-cols { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.three-cols { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
.image-wrap { display:flex; align-items:center; justify-content:center; min-height:130px; border:1px dashed #ccc; border-radius:8px; background:#fafafa; }
.image-wrap img { max-width:230px; border-radius:6px; }
.img-placeholder { color:#777; font-size:14px; }
label { font-weight:600; display:block; margin-top:8px; }
input, select, textarea { width:100%; padding:7px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
textarea { resize:vertical; }
.form-actions { margin-top:20px; display:flex; gap:10px; }
</style>

<script>
// ✅ Live afbeelding preview
document.getElementById('afbeeldingInput').addEventListener('change', function(e){
    const file = e.target.files[0];
    const img = document.getElementById('previewImg');
    const ph = document.getElementById('previewPlaceholder');
    if (file) {
        const reader = new FileReader();
        reader.onload = ev => { img.src = ev.target.result; img.style.display='block'; if(ph) ph.style.display='none'; };
        reader.readAsDataURL(file);
    } else {
        img.src=''; img.style.display='none'; if(ph) ph.style.display='block';
    }
});

// ✅ Werkadressen laden
document.addEventListener('DOMContentLoaded', function() {
  const klantSel = document.getElementById('klantSelect');
  const waSel    = document.getElementById('werkadresSelect');
  const huidigeWA = <?= !empty($object['werkadres_id']) ? (int)$object['werkadres_id'] : 'null' ?>;

  function loadWerkadressen(klantId, selectedId = null) {
    if (!klantId) {
      waSel.innerHTML = '<option value="">-- Eerst klant kiezen --</option>';
      return;
    }

    waSel.innerHTML = '<option value="">-- Laden... --</option>';

    fetch('/ajax/get_werkadressen.php?klant_id=' + encodeURIComponent(klantId))
      .then(r => r.json())
      .then(data => {
        waSel.innerHTML = '<option value="">-- Selecteer werkadres --</option>';

        if (!Array.isArray(data) || data.length === 0) {
          waSel.innerHTML = '<option value="">(Geen werkadressen)</option>';
          return;
        }

        data.forEach(wa => {
          // jouw endpoint geeft: { id, nummer, adres }
          const id = wa.id ?? wa.werkadres_id;
          const label = wa.adres ?? wa.volledig ?? wa.label ?? '';

          const opt = document.createElement('option');
          opt.value = String(id ?? '');
          opt.textContent = label || ('Werkadres #' + opt.value);

          if (selectedId !== null && String(id) === String(selectedId)) {
            opt.selected = true;
          }
          waSel.appendChild(opt);
        });
      })
      .catch(() => {
        waSel.innerHTML = '<option value="">(Kon werkadressen niet laden)</option>';
      });
  }

  // init load
  if (klantSel.value) loadWerkadressen(klantSel.value, huidigeWA);

  // bij klant wissel
  klantSel.addEventListener('change', () => loadWerkadressen(klantSel.value, null));
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
