<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// ✅ Toegang
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

// ✅ Klanten + Resultaten ophalen
$klanten = $conn->query("SELECT klant_id, debiteurnummer, bedrijfsnaam FROM klanten ORDER BY bedrijfsnaam ASC");
$res = $conn->query("SELECT naam, kleur FROM object_status ORDER BY naam ASC");
$resultaten = $res && $res->num_rows ? $res->fetch_all(MYSQLI_ASSOC) : [];

// ✅ POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $afbeelding         = null;

    // ✅ Controleer of code al bestaat
    $stmtCheck = $conn->prepare("SELECT object_id FROM objecten WHERE code = ?");
    $stmtCheck->bind_param("s", $code);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        setFlash("❌ Code '$code' bestaat al.", "error");
        header("Location: object_toevoegen.php");
        exit;
    }
    $stmtCheck->close();

    // ✅ Upload afbeelding
    if (!empty($_FILES['afbeelding']['name'])) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (in_array($_FILES['afbeelding']['type'], $allowed, true)) {
            $uploadDir = __DIR__ . '/uploads/objecten/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['afbeelding']['name'], PATHINFO_EXTENSION));
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $target = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['afbeelding']['tmp_name'], $target)) {
                $afbeelding = 'uploads/objecten/' . $filename;
            }
        }
    }

    // ✅ Invoegen
    $stmt = $conn->prepare("
        INSERT INTO objecten 
        (code, omschrijving, klant_id, werkadres_id, resultaat, datum_installatie, datum_onderhoud,
         merk, type, rijkstypekeur, fabricagejaar, beproeving_nen671_3, verdieping, locatie, opmerkingen, afbeelding)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "ssissssssiiissss",
        $code, $omschrijving, $klant_id, $werkadres_id, $resultaat,
        $datum_installatie, $datum_onderhoud, $merk, $type, $rijkstypekeur,
        $fabricagejaar, $beproeving_nen671_3, $verdieping, $locatie, $opmerkingen, $afbeelding
    );

    if ($stmt->execute()) {
        setFlash("✅ Object succesvol toegevoegd.", "success");
        header("Location: objecten.php");
        exit;
    } else {
        setFlash("❌ Fout bij opslaan: " . $stmt->error, "error");
    }
    $stmt->close();
}

$pageTitle = "Nieuw object toevoegen";
ob_start();
?>

<div class="page-header">
    <h2>➕ Nieuw object toevoegen</h2>
</div>

<form method="post" enctype="multipart/form-data" class="object-form">
    <div class="two-column-form">

        <!-- 🔹 LINKER KOLOM -->
        <div class="card left-col">
            <h3>Technische gegevens</h3>

            <label>Code*</label>
            <input type="text" name="code" required>

            <label>Omschrijving*</label>
            <input type="text" name="omschrijving" required>

            <div class="two-cols">
                <div>
                    <label>Type</label>
                    <input type="text" name="type">
                </div>
                <div>
                    <label>Merk</label>
                    <input type="text" name="merk">
                </div>
            </div>

            <div class="two-cols">
                <div>
                    <label>Datum installatie</label>
                    <input type="date" name="datum_installatie">
                </div>
                <div>
                    <label>Datum onderhoud</label>
                    <input type="date" name="datum_onderhoud">
                </div>
            </div>

            <div class="two-cols">
                <div>
                    <label>Fabricagejaar</label>
                    <input type="number" name="fabricagejaar" min="1900" max="<?= date('Y') ?>">
                </div>
                <div>
                    <label>Beproeving NEN671-3</label>
                    <input type="number" name="beproeving_nen671_3" min="1900" max="<?= date('Y') ?>">
                </div>
            </div>

            <label>Rijkstypekeur</label>
            <input type="text" name="rijkstypekeur">

            <div class="two-cols">
                <div>
                    <label>Verdieping</label>
                    <input type="text" name="verdieping">
                </div>
                <div>
                    <label>Locatie</label>
                    <input type="text" name="locatie">
                </div>
            </div>

            <label>Opmerkingen</label>
            <textarea name="opmerkingen" rows="3"></textarea>
        </div>

        <!-- 🔹 RECHTER KOLOM -->
        <div class="card right-col">
            <h3>Klant & locatie</h3>

            <div style="display:flex;gap:8px;align-items:center;">
                <label style="flex:1;">Klant*</label>
                <input type="text" id="klantZoek" placeholder="🔍 Snel zoeken..." 
                       style="flex:2;padding:6px 8px;border:1px solid #ccc;border-radius:6px;">
            </div>

            <select name="klant_id" id="klantSelect" required style="margin-top:6px;">
                <option value="">-- Kies klant --</option>
                <?php while ($k = $klanten->fetch_assoc()): ?>
                    <option value="<?= $k['klant_id'] ?>">
                        <?= htmlspecialchars($k['debiteurnummer'] . ' - ' . $k['bedrijfsnaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Werkadres</label>
            <select name="werkadres_id" id="werkadresSelect">
                <option value="">-- Eerst klant kiezen --</option>
            </select>

            <label>Resultaat*</label>
            <select name="resultaat" id="resultaatSelect" required>
                <option value="">-- Kies resultaat --</option>
                <?php foreach ($resultaten as $r):
                    $kleur = match($r['kleur']) {
                        'groen'  => '#27ae60',
                        'oranje' => '#e67e22',
                        'rood'   => '#c0392b',
                        default  => '#333'
                    }; ?>
                    <option value="<?= htmlspecialchars($r['naam']) ?>" 
                            data-color="<?= $kleur ?>" 
                            style="color: <?= $kleur ?>;">
                        <?= htmlspecialchars($r['naam']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <h3 style="margin-top:18px;">Afbeelding</h3>
            <div class="image-wrap">
                <div id="previewPlaceholder" class="img-placeholder">Geen afbeelding</div>
                <img id="previewImg" src="" alt="" style="display:none;">
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
.image-wrap { display:flex; align-items:center; justify-content:center; min-height:120px; border:1px dashed #ccc; border-radius:8px; background:#fafafa; }
.image-wrap img { max-width:150px; border-radius:6px; }
.img-placeholder { color:#777; font-size:14px; }
label { font-weight:600; display:block; margin-top:8px; }
input, select, textarea { width:100%; padding:7px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
textarea { resize:vertical; }
.form-actions { margin-top:20px; display:flex; gap:10px; }
</style>

<script>
// ✅ Live afbeelding preview
document.getElementById('afbeeldingInput').addEventListener('change', e => {
    const file = e.target.files[0];
    const img = document.getElementById('previewImg');
    const ph = document.getElementById('previewPlaceholder');
    if (file) {
        const reader = new FileReader();
        reader.onload = ev => {
            img.src = ev.target.result;
            img.style.display = 'block';
            ph.style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        img.src = '';
        img.style.display = 'none';
        ph.style.display = 'block';
    }
});

// ✅ Werkadressen laden
document.addEventListener('DOMContentLoaded', () => {
    const klantSel = document.getElementById('klantSelect');
    const waSel = document.getElementById('werkadresSelect');

    function loadWerkadressen(klantId) {
        if (!klantId) {
            waSel.innerHTML = '<option value="">-- Eerst klant kiezen --</option>';
            return;
        }
        fetch('get_werkadressen.php?klant_id=' + encodeURIComponent(klantId))
            .then(r => r.json())
            .then(data => {
                waSel.innerHTML = '<option value="">-- Selecteer werkadres --</option>';
                data.forEach(wa => {
                    const opt = document.createElement('option');
                    opt.value = wa.werkadres_id;
                    opt.textContent = wa.volledig;
                    waSel.appendChild(opt);
                });
            })
            .catch(() => { waSel.innerHTML = '<option>(Fout bij laden)</option>'; });
    }

    klantSel.addEventListener('change', () => loadWerkadressen(klantSel.value));

    // ✅ Snelle zoekfunctie
    const zoek = document.getElementById('klantZoek');
    zoek.addEventListener('input', () => {
        const term = zoek.value.toLowerCase();
        Array.from(klantSel.options).forEach(opt => {
            opt.style.display = opt.textContent.toLowerCase().includes(term) ? 'block' : 'none';
        });
    });
});

// ✅ Tekstkleur bij resultaat
const sel = document.getElementById('resultaatSelect');
function updateSelectColor() {
    const kleur = sel.options[sel.selectedIndex]?.dataset.color || '#333';
    sel.style.color = kleur;
}
sel.addEventListener('change', updateSelectColor);
updateSelectColor();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
