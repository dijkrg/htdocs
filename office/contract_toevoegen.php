<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// 🔐 Alleen ingelogde gebruikers
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

/* ============================================================
   🧩 DATA OPHALEN
============================================================ */

// Klanten
$klanten = $conn->query("
    SELECT klant_id, debiteurnummer, bedrijfsnaam
    FROM klanten
    ORDER BY bedrijfsnaam ASC
");

// Contracttypes (met jaren-kolom)
$contractTypes = $conn->query("
    SELECT type_id, naam, jaren
    FROM contract_types
    WHERE actief = 1
    ORDER BY naam ASC
");

// Onderhoudsonderdelen
$onderdelenRes = $conn->query("
    SELECT naam
    FROM contract_onderdeel_types
    WHERE actief = 1
    ORDER BY sort_order ASC, naam ASC
");


/* ============================================================
   FORM VERWERKING
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contractnummer  = trim($_POST['contractnummer']);
    $klant_id        = intval($_POST['klant_id']);
    $werkadres_id    = !empty($_POST['werkadres_id']) ? intval($_POST['werkadres_id']) : null;
    $contract_type   = intval($_POST['contract_type']);
    $status          = $_POST['status'];
    $ingangsdatum    = $_POST['ingangsdatum'] ?: null;
    $einddatum       = $_POST['einddatum'] ?: null;
    $onderdelen      = $_POST['onderdelen'] ?? [];
    $automatisch_vooruit_dagen = intval($_POST['automatisch_vooruit_dagen'] ?? 60);
    $opmerkingen     = trim($_POST['opmerkingen'] ?? "");

    // Validatie
    if ($contractnummer === "" || $klant_id === 0 || $contract_type === 0) {
        setFlash("❌ Vul alle verplichte velden in.", "error");
        header("Location: contract_toevoegen.php");
        exit;
    }

    if ($status === "Actief" && empty($onderdelen)) {
        setFlash("❌ Selecteer minstens één onderhoudsonderdeel.", "error");
        header("Location: contract_toevoegen.php");
        exit;
    }

    // Dubbel contractnummer check
    $check = $conn->prepare("SELECT COUNT(*) FROM contracten WHERE contractnummer = ?");
    $check->bind_param("s", $contractnummer);
    $check->execute();
    $check->bind_result($bestaat);
    $check->fetch();
    $check->close();

    if ($bestaat > 0) {
        setFlash("❌ Contractnummer '$contractnummer' bestaat al.", "error");
        header("Location: contract_toevoegen.php");
        exit;
    }

    // Opslaan contract
    $stmt = $conn->prepare("
        INSERT INTO contracten
        (contractnummer, status, klant_id, werkadres_id, contract_type,
         ingangsdatum, einddatum, automatisch_vooruit_dagen, opmerkingen)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssissssis",
        $contractnummer,
        $status,
        $klant_id,
        $werkadres_id,
        $contract_type,
        $ingangsdatum,
        $einddatum,
        $automatisch_vooruit_dagen,
        $opmerkingen
    );

    $stmt->execute();
    $newID = $stmt->insert_id;
    $stmt->close();

    // Opslaan onderhoudsonderdelen
    if (!empty($onderdelen)) {
        $ins = $conn->prepare("
            INSERT INTO contract_onderdelen (contract_id, onderdeel)
            VALUES (?, ?)
        ");
        foreach ($onderdelen as $o) {
            $ins->bind_param("is", $newID, $o);
            $ins->execute();
        }
        $ins->close();
    }

    setFlash("✅ Nieuw contract succesvol toegevoegd.", "success");
    header("Location: contracten.php");
    exit;
}


/* ============================================================
   HTML START
============================================================ */
$pageTitle = "Nieuw contract toevoegen";
ob_start();
?>

<!-- PAGE HEADER -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>➕ Nieuw contract toevoegen</h2>

    <div class="header-actions">
        <button type="submit" form="contractForm" class="btn">💾 Opslaan</button>
        <a href="contracten.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<form method="post" class="object-form" id="contractForm">

<div class="two-column-form">

<!-- LEFT -->
<div class="card left-col">
    <h3>Contractgegevens</h3>

    <label>Contractnummer*</label>
    <input type="text" name="contractnummer" required>

    <label>Contracttype*</label>
    <select name="contract_type" id="contractType" required>
        <option value="">-- Kies type --</option>
        <?php while ($t = $contractTypes->fetch_assoc()): ?>
            <option value="<?= $t['type_id'] ?>" data-years="<?= $t['jaren'] ?>">
                <?= htmlspecialchars($t['naam']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Status</label>
    <select name="status">
        <option value="Actief">🟢 Actief</option>
        <option value="Inactief">🔴 Inactief</option>
    </select>

    <label>Ingangsdatum</label>
    <input type="date" name="ingangsdatum" id="ingangsdatum">

    <label>Einddatum</label>
    <input type="date" name="einddatum" id="einddatum">

    <label>Dagen vóór onderhoud</label>
    <input type="number" name="automatisch_vooruit_dagen" value="60" min="1" max="365">
</div>

<!-- RIGHT -->
<div class="card right-col">
    <h3>Klant & locatie</h3>

    <label>Klant*</label>
    <input type="text" id="zoekKlant" placeholder="🔍 Zoek klant..." 
       style="margin-bottom:6px; padding:7px; width:100%;">

<select name="klant_id" id="klantSelect" required>
    <option value="">-- Kies klant --</option>
    <?php while ($k = $klanten->fetch_assoc()): ?>
        <option value="<?= $k['klant_id'] ?>">
            <?= htmlspecialchars($k['debiteurnummer']." - ".$k['bedrijfsnaam']) ?>
        </option>
    <?php endwhile; ?>
</select>

    <select name="klant_id" id="klantSelect" required>
        <option value="">-- Kies klant --</option>
        <?php while ($k = $klanten->fetch_assoc()): ?>
            <option value="<?= $k['klant_id'] ?>">
                <?= htmlspecialchars($k['debiteurnummer']." - ".$k['bedrijfsnaam']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Werkadres</label>
    <select name="werkadres_id" id="werkadresSelect">
        <option value="">-- Kies eerst een klant --</option>
    </select>

    <h3 style="margin-top:20px;">Onderhoudsonderdelen*</h3>
    <div class="onderdelen-lijst">
        <?php while ($od = $onderdelenRes->fetch_assoc()): ?>
            <label class="od-item">
                <input type="checkbox" name="onderdelen[]" value="<?= htmlspecialchars($od['naam']) ?>">
                <?= htmlspecialchars($od['naam']) ?>
            </label>
        <?php endwhile; ?>
    </div>

    <h3>Bijzonderheden</h3>
    <textarea name="opmerkingen" rows="4"></textarea>
</div>

</div>
</form>

<style>
.two-column-form { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
label { display:block; font-weight:600; margin-top:10px; }
.od-item { display:flex; align-items:center; gap:8px; }
</style>

<script>
// =====================================================
// 🟦 WERKADRESSEN VIA AJAX
// =====================================================
document.getElementById("klantSelect").addEventListener("change", function() {

    const klantId = this.value;
    const select = document.getElementById("werkadresSelect");

    if (!klantId) {
        select.innerHTML = '<option value="">-- Kies eerst een klant --</option>';
        return;
    }

    fetch('/ajax/get_werkadressen.php?klant_id=' + klantId)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Geen werkadres --</option>';

            data.forEach(a => {
                const opt = document.createElement("option");
                opt.value = a.id;              // ← Correct veld
                opt.textContent = a.adres;     // ← Correct veld
                select.appendChild(opt);
            });
        })
        .catch(() => {
            select.innerHTML = '<option>Fout bij laden</option>';
        });
});

// =====================================================
// 🟦 AUTOMATISCHE EINDDATUM
// =====================================================
function berekenEinddatum() {
    const typeSel = document.getElementById("contractType");
    const start   = document.getElementById("ingangsdatum").value;
    const end     = document.getElementById("einddatum");

    const jaren = parseInt(typeSel.selectedOptions[0]?.dataset.years || 0);
    if (!start || !jaren) return;

    const d = new Date(start);
    d.setFullYear(d.getFullYear() + jaren);

    end.value = d.toISOString().split("T")[0];
}

document.getElementById("contractType").addEventListener("change", berekenEinddatum);
document.getElementById("ingangsdatum").addEventListener("change", berekenEinddatum);
</script>

// ===============================================
// 🔍 LIVE ZOEKEN IN KLANTEN SELECT
// ===============================================
document.getElementById("zoekKlant").addEventListener("input", function () {

    const filter = this.value.toLowerCase();
    const select = document.getElementById("klantSelect");
    const options = select.querySelectorAll("option");

    options.forEach(opt => {
        const text = opt.textContent.toLowerCase();
        if (text.includes(filter) || opt.value === "") {
            opt.style.display = "";
        } else {
            opt.style.display = "none";
        }
    });

});


<style>
/* ------- Onderhoudsonderdelen layout ------- */
.onderdelen-lijst {
    display: flex;
    flex-direction: column;
    gap: 1px;
    margin-top: 10px;
    margin-bottom: 15px;
}

.od-item {
    display: flex;
    align-items: center;
    font-size: 14px;
    line-height: 1.2;
    gap: 8px;
    padding: 2px 0;
}

.od-item input[type="checkbox"] {
    margin: 0;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.od-item span {
    display: inline-block;
    margin-left: 1px;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
