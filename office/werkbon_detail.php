<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/init.php';
requireLogin();

$rol = $_SESSION['user']['rol'] ?? '';
$isMonteur = ($rol === 'Monteur');
$isManager = ($rol === 'Manager');
$isAdmin   = ($rol === 'Admin');

$werkbon_id = (int)($_GET['id'] ?? 0);
if ($werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: werkbonnen.php");
    exit;
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function dateNL(?string $date): string {
    if (!$date || $date === "0000-00-00") return "-";
    $ts = strtotime($date);
    return $ts ? date("d-m-Y", $ts) : (string)$date;
}
function hm(?string $t): string {
    if (!$t) return '-';
    return substr((string)$t, 0, 5);
}

/* ============================================================
   OPSLAAN (status + omschrijving) - alleen Admin/Manager
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isMonteur) {
    $newStatus = (string)($_POST['status'] ?? '');
    $newOmschrijving = (string)($_POST['omschrijving'] ?? '');

    // alleen toestaan wat in je enum zit
    $allowed = ['Klaargezet','Ingepland','Compleet','Afgehandeld','Contract'];
    if (!in_array($newStatus, $allowed, true)) {
        setFlash("Ongeldige status.", "error");
        header("Location: werkbon_detail.php?id=" . $werkbon_id);
        exit;
    }

    $u = $conn->prepare("UPDATE werkbonnen SET status = ?, omschrijving = ? WHERE werkbon_id = ? LIMIT 1");
    $u->bind_param("ssi", $newStatus, $newOmschrijving, $werkbon_id);
    $u->execute();
    $u->close();

    setFlash("Werkbon opgeslagen.", "success");
    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

/* ============================================================
   1) Werkbon laden
============================================================ */
$stmt = $conn->prepare("
    SELECT w.*, 
           k.debiteurnummer, k.bedrijfsnaam AS klantnaam, k.adres AS klant_adres, 
           k.postcode AS klant_postcode, k.plaats AS klant_plaats,
           wa.bedrijfsnaam AS wa_bedrijfsnaam, wa.adres AS wa_adres, 
           wa.postcode AS wa_postcode, wa.plaats AS wa_plaats,
           m.voornaam, m.achternaam,
           t.naam AS type_naam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN medewerkers m ON w.monteur_id = m.medewerker_id
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$werkbon) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: werkbonnen.php");
    exit;
}

/* ============================================================
   2) Objecten / Artikelen / Uren
============================================================ */
$objecten = $conn->query("
    SELECT o.object_id, o.code, o.omschrijving, o.datum_onderhoud, 
           o.fabricagejaar, s.naam AS status_naam
    FROM werkbon_objecten wo
    JOIN objecten o ON wo.object_id = o.object_id
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE wo.werkbon_id = " . (int)$werkbon_id . "
    ORDER BY o.code ASC
");

$artikelen = $conn->query("
    SELECT wa.id, wa.aantal, a.artikelnummer, a.omschrijving, a.verkoopprijs
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON wa.artikel_id = a.artikel_id
    WHERE wa.werkbon_id = " . (int)$werkbon_id . "
    ORDER BY a.omschrijving ASC
");

$uren = $conn->query("
    SELECT u.*, 
           m.voornaam, m.achternaam, m.personeelsnummer,
           us.code AS uursoort_code, us.omschrijving AS uursoort_omschrijving
    FROM urenregistratie u
    LEFT JOIN medewerkers m ON u.user_id = m.medewerker_id
    LEFT JOIN uursoorten_uren us ON u.uursoort_id = us.uursoort_id
    WHERE u.werkbon_id = " . (int)$werkbon_id . "
    ORDER BY u.datum ASC, u.starttijd ASC
");

/* ============================================================
   OUTPUT
============================================================ */
$pageTitle = "Werkbon detail";
ob_start();
?>

<style>
/* ✅ klant/werkadres naast elkaar */
.two-column-form{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:16px;
  margin-top: 16px;
}
@media (max-width: 900px){
  .two-column-form{ grid-template-columns: 1fr; }
}

/* ✅ labels links uitlijnen */
.detail-table th{
  text-align:left !important;
  vertical-align: top;
  width: 210px;
  white-space: nowrap;
}

/* form in detail-table */
.detail-table td .form-control,
.detail-table td select,
.detail-table td textarea{
  width: 100%;
  max-width: 650px;
}
.header-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom: 14px;
  align-items:center;
}
</style>

<div class="header-actions">
<?php if (!$isMonteur): ?>
    <a href="werkbon_pdf.php?id=<?= (int)$werkbon_id ?>" target="_blank" class="btn">📄 PDF</a>
    <a href="werkbon_bewerk.php?id=<?= (int)$werkbon_id ?>" class="btn">✏️ Bewerken</a>
    <a href="werkbonnen.php" class="btn btn-secondary">⬅ Terug</a>
<?php else: ?>
    <a href="/monteur/mijn_planning.php" class="btn btn-secondary">⬅ Mijn planning</a>
<?php endif; ?>
</div>

<!-- ✅ OPSLAAN FORM (Admin/Manager) -->
<?php if (!$isMonteur): ?>
<form method="post" class="card" style="margin-bottom:16px;">
    <h3 style="margin-top:0;">Opslaan</h3>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
        <span style="opacity:.7; font-size:13px;">(Status + Omschrijving)</span>
    </div>
<?php endif; ?>

<!-- -------------------------------------------------- -->
<!-- WERKBONGEGEVENS -->
<!-- -------------------------------------------------- -->
<div class="card">
<h3>Werkbongegevens</h3>

<table class="detail-table" style="width:100%;">
    <tr><th>Werkbonnummer</th><td><?= e($werkbon['werkbonnummer'] ?? '') ?></td></tr>
    <tr><th>Uitvoerdatum</th><td><?= dateNL($werkbon['uitvoerdatum'] ?? null) ?></td></tr>
    <tr><th>Voorkeurdatum</th><td><?= dateNL($werkbon['voorkeurdatum'] ?? null) ?></td></tr>
    <tr><th>Tijd</th><td><?= hm($werkbon['starttijd'] ?? null) ?> – <?= hm($werkbon['eindtijd'] ?? null) ?></td></tr>
    <tr><th>Monteur</th><td><?= e(trim(($werkbon['voornaam'] ?? '') . " " . ($werkbon['achternaam'] ?? ''))) ?></td></tr>

    <tr>
        <th>Status</th>
        <td>
        <?php if (!$isMonteur): ?>
            <select name="status" class="form-control" style="max-width:280px;">
                <?php
                $allowed = ['Klaargezet','Ingepland','Compleet','Afgehandeld','Contract'];
                $cur = (string)($werkbon['status'] ?? 'Klaargezet');
                foreach ($allowed as $s):
                ?>
                    <option value="<?= e($s) ?>" <?= $cur === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <?= e($werkbon['status'] ?? '') ?>
        <?php endif; ?>
        </td>
    </tr>

    <tr><th>Type werkzaamheden</th><td><?= e($werkbon['type_naam'] ?? '-') ?></td></tr>

    <tr>
        <th>Omschrijving</th>
        <td>
        <?php if (!$isMonteur): ?>
            <textarea name="omschrijving" class="form-control" rows="5"><?= e($werkbon['omschrijving'] ?? '') ?></textarea>
        <?php else: ?>
            <?= nl2br(e($werkbon['omschrijving'] ?? '')) ?>
        <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th>Werk gereed</th>
        <td>
            <label class="toggle-switch">
                <input type="checkbox" id="werkGereedToggle" <?= ((int)($werkbon['werk_gereed'] ?? 0) === 1 ? 'checked' : '') ?>>
                <span class="toggle-slider"></span>
            </label>
        </td>
    </tr>
</table>
</div>

<?php if (!$isMonteur): ?>
</form>
<?php endif; ?>

<!-- -------------------------------------------------- -->
<!-- MONTEUR STATUS -->
<!-- -------------------------------------------------- -->
<?php if ($isMonteur): ?>
<div class="card">
<h3>Monteur status</h3>

<p style="margin-top:0;"><strong>Huidige status:</strong>
   <span id="monteurStatusLabel" class="status-badge" style="margin-left:8px;">
       <?= e(($werkbon['monteur_status'] ?? '') ?: '-') ?>
   </span>
</p>

<select id="monteurStatusSelect" class="form-control" style="max-width:320px;">
    <option value="">-- kies status --</option>
    <option value="onderweg">Onderweg</option>
    <option value="op_locatie">Op locatie</option>
    <option value="gereed">Gereed</option>
</select>

<textarea id="monteurStatusNote" class="form-control" placeholder="Notitie..." style="max-width:650px;"></textarea>

<button id="btnUpdateStatus" class="btn btn-primary" style="margin-top:10px;">Status bijwerken</button>
</div>
<?php endif; ?>

<!-- -------------------------------------------------- -->
<!-- KLANT + WERKADRES -->
<!-- -------------------------------------------------- -->
<div class="two-column-form">
    <div class="card">
        <h3>Klant</h3>
        <p><strong><?= e($werkbon['debiteurnummer'] ?? '') ?> - <?= e($werkbon['klantnaam'] ?? '-') ?></strong></p>
        <p><?= e($werkbon['klant_adres'] ?? '-') ?><br><?= e($werkbon['klant_postcode'] ?? '') . ' ' . e($werkbon['klant_plaats'] ?? '') ?></p>
    </div>

    <div class="card">
        <h3>Werkadres</h3>
        <?php if (!empty($werkbon['wa_bedrijfsnaam']) || !empty($werkbon['wa_adres'])): ?>
            <p><strong><?= e($werkbon['wa_bedrijfsnaam'] ?? '-') ?></strong></p>
            <p><?= e($werkbon['wa_adres'] ?? '-') ?><br><?= e($werkbon['wa_postcode'] ?? '') . ' ' . e($werkbon['wa_plaats'] ?? '') ?></p>
        <?php else: ?>
            <p><em>Geen werkadres.</em></p>
        <?php endif; ?>
    </div>
</div>

<!-- -------------------------------------------------- -->
<!-- OBJECTEN -->
<!-- -------------------------------------------------- -->
<div class="card">
<h3>Objecten</h3>

<table class="data-table small-table">
<thead>
<tr>
    <th>Code</th>
    <th>Omschrijving</th>
    <th>Onderhoud</th>
    <th>Fabricagejaar</th>
    <th>Status</th>
    <th>Acties</th>
</tr>
</thead>
<tbody>
<?php if (!$objecten || $objecten->num_rows === 0): ?>
<tr><td colspan="6" class="text-center">Geen objecten</td></tr>
<?php else: while ($o = $objecten->fetch_assoc()): ?>
<tr>
    <td><?= e($o['code'] ?? '') ?></td>
    <td><?= e($o['omschrijving'] ?? '') ?></td>
    <td><?= dateNL($o['datum_onderhoud'] ?? null) ?></td>
    <td><?= e($o['fabricagejaar'] ?? '') ?></td>
    <td><?= e(($o['status_naam'] ?? '') ?: '-') ?></td>
    <td>
        <?php if ($isMonteur): ?>
            <a class="btn btn-danger"
               href="werkbon_object_verwijder.php?werkbon_id=<?= (int)$werkbon_id ?>&object_id=<?= (int)$o['object_id'] ?>"
               onclick="return confirm('Object ontkoppelen?')">🗑</a>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>

<!-- -------------------------------------------------- -->
<!-- ARTIKELEN -->
<!-- -------------------------------------------------- -->
<div class="card">
<h3>Gebruikte artikelen</h3>

<table class="data-table small-table">
<thead>
<tr>
    <th>Aantal</th>
    <th>Artikelnummer</th>
    <th>Omschrijving</th>
    <th>Verkoopprijs</th>
    <th>Totaal</th>
</tr>
</thead>
<tbody>
<?php if (!$artikelen || $artikelen->num_rows === 0): ?>
<tr><td colspan="5" class="text-center">Geen artikelen</td></tr>
<?php else: while ($a = $artikelen->fetch_assoc()): ?>
<?php
$prijs = (float)($a['verkoopprijs'] ?? 0);
$aantal = (float)($a['aantal'] ?? 0);
$totaal = $prijs * $aantal;
?>
<tr>
    <td><?= e($a['aantal'] ?? '') ?></td>
    <td><?= e($a['artikelnummer'] ?? '') ?></td>
    <td><?= e($a['omschrijving'] ?? '') ?></td>
    <td>€ <?= number_format($prijs, 2, ',', '.') ?></td>
    <td>€ <?= number_format($totaal, 2, ',', '.') ?></td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>

<!-- -------------------------------------------------- -->
<!-- UREN -->
<!-- -------------------------------------------------- -->
<div class="card">
<h3>Urenregistratie</h3>

<table class="data-table small-table">
<thead>
<tr>
    <th>Datum</th>
    <th>Monteur</th>
    <th>Uursoort</th>
    <th>Start</th>
    <th>Einde</th>
    <th>Uren</th>
    <th>Beschrijving</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
<?php if (!$uren || $uren->num_rows === 0): ?>
<tr><td colspan="8" class="text-center">Geen uren</td></tr>
<?php else: while ($u = $uren->fetch_assoc()): ?>
<?php
$status = (int)($u['goedgekeurd'] ?? 0);
if ($status === 1)       $badge = '<span class="badge badge-success">Goedgekeurd</span>';
elseif ($status === -1)  $badge = '<span class="badge badge-danger">Afgekeurd</span>';
else                     $badge = '<span class="badge badge-warning">In behandeling</span>';

$duurMin = (int)($u['duur_minuten'] ?? 0);
$duurUur = $duurMin > 0 ? number_format($duurMin / 60, 2, ',', '.') : '0,00';
?>
<tr>
    <td><?= dateNL($u['datum'] ?? null) ?></td>
    <td><?= e(($u['voornaam'] ?? '').' '.($u['achternaam'] ?? '')) ?> (<?= e($u['personeelsnummer'] ?? '') ?>)</td>
    <td><?= e(($u['uursoort_code'] ?? '').' - '.($u['uursoort_omschrijving'] ?? '')) ?></td>
    <td><?= e(substr((string)($u['starttijd'] ?? ''), 0, 5)) ?></td>
    <td><?= e(substr((string)($u['eindtijd'] ?? ''), 0, 5)) ?></td>
    <td><?= $duurUur ?></td>
    <td><?= nl2br(e($u['beschrijving'] ?? '')) ?></td>
    <td><?= $badge ?></td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>

<!-- -------------------------------------------------- -->
<!-- SCRIPTS -->
<!-- -------------------------------------------------- -->
<script>
document.getElementById("werkGereedToggle")?.addEventListener("change", function(){
    fetch("werkbon_toggle_gereed.php", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            id: <?= (int)$werkbon_id ?>,
            gereed: this.checked ? 1 : 0
        })
    });
});
</script>

<?php if ($isMonteur): ?>
<script>
document.getElementById("btnUpdateStatus")?.addEventListener("click", function(){
    const s = document.getElementById("monteurStatusSelect").value;
    const note = document.getElementById("monteurStatusNote").value;

    if (!s) { alert("Kies een status."); return; }

    fetch("/monteur/update_monteur_status.php", {
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            id: <?= (int)$werkbon_id ?>,
            status: s,
            note: note
        })
    })
    .then(r => r.json())
    .then(d => {
        if (!d || !d.ok) { alert("Fout: " + (d?.msg || "onbekend")); return; }
        document.getElementById("monteurStatusLabel").textContent = s;
        alert("Status bijgewerkt!");
    })
    .catch(() => alert("Verbinding mislukt."));
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
