<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ------------------------------------------------------
// 🔐 Alleen ingelogde gebruikers
// ------------------------------------------------------
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

// ------------------------------------------------------
// 🧩 CONTRACT ID
// ------------------------------------------------------
$contract_id = intval($_GET['id'] ?? 0);
if ($contract_id <= 0) {
    setFlash("Ongeldig contract.", "error");
    header("Location: contracten.php");
    exit;
}

// ------------------------------------------------------
// 🧩 CONTRAC TDETAILS OPHALEN
// ------------------------------------------------------
$stmt = $conn->prepare("
    SELECT *
    FROM contracten
    WHERE contract_id = ?
");
$stmt->bind_param("i", $contract_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    setFlash("Contract niet gevonden.", "error");
    header("Location: contracten.php");
    exit;
}

// ------------------------------------------------------
// 🧩 KLANTEN
// ------------------------------------------------------
$klanten = $conn->query("
    SELECT klant_id, debiteurnummer, bedrijfsnaam
    FROM klanten
    ORDER BY bedrijfsnaam ASC
");

// ------------------------------------------------------
// 🧩 CONTRACTTYPES (incl. jaren)
// ------------------------------------------------------
$contractTypes = $conn->query("
    SELECT type_id, naam, jaren
    FROM contract_types
    WHERE actief = 1
    ORDER BY naam ASC
");

// ------------------------------------------------------
// 🧩 ALLE ONDERDELEN
// ------------------------------------------------------
$alleOnderdelen = $conn->query("
    SELECT naam
    FROM contract_onderdeel_types
    WHERE actief = 1
    ORDER BY sort_order ASC, naam ASC
");

// ------------------------------------------------------
// 🧩 GESELECTEERDE ONDERDELEN
// ------------------------------------------------------
$geselecteerd = [];
$res = $conn->prepare("
    SELECT onderdeel
    FROM contract_onderdelen
    WHERE contract_id = ?
");
$res->bind_param("i", $contract_id);
$res->execute();
$out = $res->get_result();
while ($r = $out->fetch_assoc()) {
    $geselecteerd[] = $r['onderdeel'];
}
$res->close();

// ------------------------------------------------------
// FORM VERWERKING
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contractnummer = trim($_POST['contractnummer']);
    $klant_id       = intval($_POST['klant_id']);
    $werkadres_id   = !empty($_POST['werkadres_id']) ? intval($_POST['werkadres_id']) : null;
    $contract_type  = intval($_POST['contract_type']);
    $status         = $_POST['statusHidden'] ?? $contract['status'];
    $ingangsdatum   = $_POST['ingangsdatum'] ?: null;
    $einddatum      = $_POST['einddatum'] ?: null;
    $opmerkingen    = trim($_POST['opmerkingen']);
    $onderdelen     = $_POST['onderdelen'] ?? [];
    $datum_opzegging = $_POST['datum_opzegging'] ?: null;
    $reden_opzegging = $_POST['reden_opzegging'] ?: null;
    $automatisch_vooruit_dagen = intval($_POST['automatisch_vooruit_dagen'] ?? 60);

    if ($contractnummer === "" || $klant_id === 0 || $contract_type === 0) {
        setFlash("❌ Vul alle verplichte velden in.", "error");
        header("Location: contract_bewerk.php?id=$contract_id");
        exit;
    }

    // Update contract
    $stmt = $conn->prepare("
        UPDATE contracten
        SET contractnummer=?, status=?, klant_id=?, werkadres_id=?, 
            contract_type=?, ingangsdatum=?, einddatum=?, 
            automatisch_vooruit_dagen=?, opmerkingen=?, 
            datum_opzegging=?, reden_opzegging=?, bijgewerkt_op=NOW()
        WHERE contract_id=?
    ");

    $stmt->bind_param(
        "ssissssisssi",
        $contractnummer,
        $status,
        $klant_id,
        $werkadres_id,
        $contract_type,
        $ingangsdatum,
        $einddatum,
        $automatisch_vooruit_dagen,
        $opmerkingen,
        $datum_opzegging,
        $reden_opzegging,
        $contract_id
    );

    $stmt->execute();
    $stmt->close();

    // Onderhoudsonderdelen resetten + opnieuw opslaan
    $conn->query("DELETE FROM contract_onderdelen WHERE contract_id=$contract_id");

    if (!empty($onderdelen)) {
        $ins = $conn->prepare("
            INSERT INTO contract_onderdelen (contract_id, onderdeel)
            VALUES (?,?)
        ");
        foreach ($onderdelen as $o) {
            $ins->bind_param("is", $contract_id, $o);
            $ins->execute();
        }
        $ins->close();
    }

    setFlash("✅ Contract bijgewerkt.", "success");
    header("Location: contract_detail.php?id=$contract_id");
    exit;
}

// ------------------------------------------------------
// HTML START
// ------------------------------------------------------
$pageTitle = "Contract bewerken";
ob_start();
?>

<!-- ============================================================
     HEADER
============================================================ -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>✏️ Contract bewerken — <?= htmlspecialchars($contract['contractnummer']) ?></h2>

    <div>
        <button type="button" class="btn btn-danger" id="btnOpzeggen">❌ Contract opzeggen</button>
    </div>
</div>

<form method="post" id="contractForm" class="object-form">

<input type="hidden" name="statusHidden" id="statusHidden" value="<?= $contract['status'] ?>">
<input type="hidden" name="datum_opzegging" id="datum_opzegging">
<input type="hidden" name="reden_opzegging" id="reden_opzegging">

<div class="two-column-form">

<!-- LEFT -->
<div class="card left-col">
    <h3>Contractgegevens</h3>

    <label>Contractnummer*</label>
    <input type="text" name="contractnummer" required value="<?= htmlspecialchars($contract['contractnummer']) ?>">

    <label>Contracttype*</label>
    <select name="contract_type" id="contractType" required>
        <?php
        mysqli_data_seek($contractTypes, 0);
        while ($t = $contractTypes->fetch_assoc()):
        ?>
            <option 
                value="<?= $t['type_id'] ?>"
                data-years="<?= $t['jaren'] ?>"
                <?= $contract['contract_type']==$t['type_id'] ? "selected" : "" ?>
            >
                <?= htmlspecialchars($t['naam']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Ingangsdatum</label>
    <input type="date" id="ingangsdatum" name="ingangsdatum"
           value="<?= htmlspecialchars($contract['ingangsdatum']) ?>">

    <label>Einddatum</label>
    <input type="date" id="einddatum" name="einddatum"
           value="<?= htmlspecialchars($contract['einddatum']) ?>">

    <label>Dagen vóór onderhoud</label>
    <input type="number" name="automatisch_vooruit_dagen" min="1" max="365"
           value="<?= htmlspecialchars($contract['automatisch_vooruit_dagen']) ?>">
</div>

<!-- RIGHT -->
<div class="card right-col">
    <h3>Klant & locatie</h3>

    <label>Klant*</label>
    <select name="klant_id" id="klantSelect" required>
        <?php
        mysqli_data_seek($klanten, 0);
        while ($k = $klanten->fetch_assoc()):
        ?>
            <option value="<?= $k['klant_id'] ?>"
                <?= $k['klant_id']==$contract['klant_id'] ? "selected" : "" ?>>
                <?= htmlspecialchars($k['debiteurnummer']." - ".$k['bedrijfsnaam']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Werkadres</label>
    <select name="werkadres_id" id="werkadresSelect">
        <option value="">-- Kies klant eerst --</option>
    </select>

    <h3 style="margin-top:20px;">Onderhoudsonderdelen</h3>
    <div class="onderdelen-lijst">
        <?php
        mysqli_data_seek($alleOnderdelen, 0);
        while ($od = $alleOnderdelen->fetch_assoc()):
            $chk = in_array($od['naam'], $geselecteerd) ? "checked" : "";
        ?>
        <label class="od-item">
            <input type="checkbox" name="onderdelen[]" value="<?= $od['naam'] ?>" <?= $chk ?>>
            <span><?= htmlspecialchars($od['naam']) ?></span>
        </label>
        <?php endwhile; ?>
    </div>

    <h3 style="margin-top:20px;">Bijzonderheden</h3>
    <textarea name="opmerkingen" rows="4"><?= htmlspecialchars($contract['opmerkingen']) ?></textarea>
</div>

</div>

<div class="form-actions">
    <button class="btn">💾 Opslaan</button>
    <a href="contract_detail.php?id=<?= $contract_id ?>" class="btn btn-secondary">⬅ Terug</a>
</div>

</form>

<!-- ============================================================
     POPUP CONTRACT OPZEGGEN
============================================================ -->
<div id="popupOpzegging" class="abcb-popup-overlay" style="display:none;">
    <div class="abcb-popup">
        <h2>❌ Contract opzeggen</h2>

        <label>Datum opzegging*</label>
        <input type="date" id="popup_datum_opzeg">

        <label>Reden*</label>
        <select id="popup_reden">
            <option value="">-- Kies reden --</option>
            <option value="Geen verlenging">Geen verlenging</option>
            <option value="Faillissement">Faillissement</option>
            <option value="Bedrijfsovername">Bedrijfsovername</option>
            <option value="Overig">Overig (extra toelichting)</option>
        </select>

        <textarea id="popup_extra" placeholder="Extra toelichting bij 'Overig'"></textarea>

        <div class="abcb-popup-buttons">
            <button id="popupSave" class="btn">Bevestigen</button>
            <button id="popupCancel" class="btn btn-secondary">Annuleren</button>
        </div>
    </div>
</div>

<style>
.two-column-form {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}
.od-item {
    display:flex;
    align-items:center;
    gap:8px;
    padding:2px 0;
}
.abcb-popup-overlay {
    position:fixed; inset:0;
    background:rgba(0,0,0,.45);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:9999;
}
.abcb-popup {
    background:white;
    width:420px;
    padding:20px;
    border-radius:12px;
    box-shadow:0 0 15px rgba(0,0,0,.25);
}
.abcb-popup-buttons {
    margin-top:15px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

/* ------- Onderhoudsonderdelen layout ------- */
.onderdelen-lijst {
    display: flex;
    flex-direction: column;
    gap: 1px;                 /* kleine afstand tussen regels */
    margin-top: 10px;
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
    flex-shrink: 0;           /* voorkomt verschuiven */
}

.od-item span {
    display: inline-block;
    margin-left: 1px;
}
</style>

<!-- ============================================================
     JS — Werkadressen + Einddatum + Popup
============================================================ -->
<script>
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

// ----------------------------------------------------------
// AUTOMATISCHE EINDDATUM
// ----------------------------------------------------------
function berekenEinddatum() {
    const sel = document.getElementById("contractType");
    const start = document.getElementById("ingangsdatum").value;
    const end = document.getElementById("einddatum");

    const jaren = parseInt(sel.selectedOptions[0]?.dataset.years || 0);
    if (!start || !jaren) return;

    const d = new Date(start);
    d.setFullYear(d.getFullYear() + jaren);

    end.value = d.toISOString().substring(0,10);
}

document.getElementById("contractType").addEventListener("change", berekenEinddatum);
document.getElementById("ingangsdatum").addEventListener("change", berekenEinddatum);

// ----------------------------------------------------------
// POPUP CONTR ACT OPZEGGEN
// ----------------------------------------------------------
document.getElementById("btnOpzeggen").addEventListener("click", () => {
    document.getElementById("popupOpzegging").style.display = "flex";
});

document.getElementById("popupCancel").addEventListener("click", () => {
    document.getElementById("popupOpzegging").style.display = "none";
});

document.getElementById("popupSave").addEventListener("click", () => {

    const reden = document.getElementById("popup_reden").value;
    const datum = document.getElementById("popup_datum_opzeg").value;
    const extra = document.getElementById("popup_extra").value;

    if (!reden || !datum) {
        alert("Vul zowel datum als reden in.");
        return;
    }

    let finalReason = reden;
    if (reden === "Overig") {
        if (extra.trim() === "") {
            alert("Toelichting verplicht bij 'Overig'.");
            return;
        }
        finalReason = extra;
    }

    document.getElementById("reden_opzegging").value = finalReason;
    document.getElementById("datum_opzegging").value = datum;
    document.getElementById("statusHidden").value = "Inactief";

    document.getElementById("popupOpzegging").style.display = "none";
    document.getElementById("contractForm").submit();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
