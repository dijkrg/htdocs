<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* --------------------------------------------------
   Helpers
-------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

function dateNL(?string $date): string {
    if (!$date || $date === "0000-00-00") return "-";
    $ts = strtotime($date);
    return $ts ? date("d-m-Y", $ts) : (string)$date;
}
function hm(?string $time): string {
    if (!$time) return "-";
    return substr($time, 0, 5);
}
function statusBadge(string $status): string {
    $s = strtolower(trim($status));
    $cls = "wb-badge grijs";
    if ($s === "ingepland")  $cls = "wb-badge blauw";
    if ($s === "klaargezet") $cls = "wb-badge oranje";
    if ($s === "compleet" || $s === "afgehandeld") $cls = "wb-badge groen";
    return "<span class='{$cls}'>" . e($status ?: "-") . "</span>";
}

/* --------------------------------------------------
   1) Werkbon ophalen + ownership check
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT
        w.*,
        k.debiteurnummer,
        k.bedrijfsnaam AS klantnaam,
        k.adres  AS klant_adres,
        k.postcode AS klant_postcode,
        k.plaats AS klant_plaats,

        wa.bedrijfsnaam AS wa_bedrijfsnaam,
        wa.adres  AS wa_adres,
        wa.postcode AS wa_postcode,
        wa.plaats AS wa_plaats,

        m.voornaam AS monteur_voornaam,
        m.achternaam AS monteur_achternaam,

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
    header("Location: /monteur/mijn_planning.php");
    exit;
}

if ((int)($werkbon['monteur_id'] ?? 0) !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* --------------------------------------------------
   2) Objecten ophalen (JOUW REGEL):
   - als werkadres_id op werkbon staat -> alleen objecten van dat werkadres
   - anders -> alleen klant-objecten ZONDER werkadres (werkadres_id NULL/0)
   - nooit verwijderd
-------------------------------------------------- */
$objecten = [];

$klant_id     = (int)($werkbon['klant_id'] ?? 0);
$werkadres_id = (int)($werkbon['werkadres_id'] ?? 0);

// detecteer kolommen in objecten (veilig)
$cols = [];
$cr = $conn->query("SHOW COLUMNS FROM objecten");
while ($cr && ($c = $cr->fetch_assoc())) {
    $cols[strtolower((string)$c['Field'])] = true;
}

$hasDatumOnderhoud = isset($cols['datum_onderhoud']);
$hasStatusId       = isset($cols['status_id']);
$hasVerwijderd     = isset($cols['verwijderd']);
$hasWerkadresId    = isset($cols['werkadres_id']); // bestaat bij jou

$where  = "";
$types  = "";
$params = [];

// Kies filter: werkadres > klant-zonder-werkadres
if ($werkadres_id > 0 && $hasWerkadresId) {
    $where = "o.werkadres_id = ?";
    $types = "i";
    $params = [$werkadres_id];
} else {
    // klant-objecten die NIET aan een werkadres hangen
    $where = "o.klant_id = ?";
    $types = "i";
    $params = [$klant_id];

    if ($hasWerkadresId) {
        $where .= " AND (o.werkadres_id IS NULL OR o.werkadres_id = 0)";
    }
}

$sqlObj = "
    SELECT
        o.object_id,
        o.code,
        o.omschrijving" .
        ($hasDatumOnderhoud ? ", o.datum_onderhoud" : ", NULL AS datum_onderhoud") . "
        " . ($hasStatusId ? ", s.naam AS status_naam" : ", NULL AS status_naam") . "
    FROM objecten o
    " . ($hasStatusId ? "LEFT JOIN object_status s ON o.status_id = s.status_id" : "") . "
    WHERE $where
";

if ($hasVerwijderd) {
    $sqlObj .= " AND (o.verwijderd = 0 OR o.verwijderd IS NULL) ";
}

$sqlObj .= " ORDER BY o.code ASC";

$q = $conn->prepare($sqlObj);
$q->bind_param($types, ...$params);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $objecten[] = $r;
$q->close();

/* --------------------------------------------------
   3) Artikelen ophalen
-------------------------------------------------- */
$artikelen = [];
$q = $conn->prepare("
    SELECT
        wa.id, wa.aantal,
        a.artikelnummer, a.omschrijving, a.verkoopprijs
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON wa.artikel_id = a.artikel_id
    WHERE wa.werkbon_id = ?
    ORDER BY a.omschrijving ASC
");
$q->bind_param("i", $werkbon_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $artikelen[] = $r;
$q->close();

/* --------------------------------------------------
   4) Uren ophalen (werkbon_uren)
-------------------------------------------------- */
$uren = [];

/* uursoorten tabel: we proberen netjes code/omschrijving te tonen,
   maar fallback naar 'naam' als die bestaat. */
$usCols = [];
$cr = $conn->query("SHOW COLUMNS FROM uursoorten");
if ($cr) {
    while ($c = $cr->fetch_assoc()) {
        $usCols[strtolower((string)$c['Field'])] = true;
    }
}

$usCodeField = isset($usCols['code']) ? 'us.code' : (isset($usCols['uursoort_code']) ? 'us.uursoort_code' : 'NULL');
$usOmsField  = isset($usCols['omschrijving']) ? 'us.omschrijving' : (isset($usCols['naam']) ? 'us.naam' : 'NULL');

$q = $conn->prepare("
    SELECT
        u.werkbon_uur_id,
        u.monteur_id,
        u.uursoort_id,
        u.datum,
        u.begintijd,
        u.eindtijd,
        u.totaal_uren,
        u.opmerkingen,
        u.goedgekeurd,
        {$usCodeField} AS uursoort_code,
        {$usOmsField}  AS uursoort_omschrijving
    FROM werkbon_uren u
    LEFT JOIN uursoorten us ON u.uursoort_id = us.uursoort_id
    WHERE u.werkbon_id = ?
    ORDER BY u.datum ASC, u.begintijd ASC
");
$q->bind_param("i", $werkbon_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $uren[] = $r;
$q->close();

/* --------------------------------------------------
   OUTPUT
-------------------------------------------------- */
$pageTitle = "Werkbon " . ($werkbon['werkbonnummer'] ?? (string)$werkbon_id);

ob_start();
?>

<style>
.two-column-form{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:16px;
  margin-bottom:20px;
}
@media(max-width:768px){
  .two-column-form{ grid-template-columns:1fr; }
}
.header-actions .form-control{
  padding:10px 12px;
  border-radius:10px;
}
</style>

<div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; align-items:center;">
    <a href="/monteur/mijn_planning.php" class="btn btn-secondary">⬅ Terug</a>
    <a href="/monteur/handtekening_modal.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">✍️ Handtekening</a>
    <a href="/monteur/pdf_werkbon.php?id=<?= (int)$werkbon_id ?>" target="_blank" class="btn">📄 PDF</a>
</div>

<div class="card" id="werkbongegevens">
    <h3>Werkbongegevens</h3>
    <table class="detail-table" style="width:100%;">
        <tr><th style="text-align:left;">Werkbonnummer</th><td><?= e($werkbon['werkbonnummer'] ?? '') ?></td></tr>
        <tr><th style="text-align:left;">Uitvoerdatum</th><td><?= dateNL($werkbon['uitvoerdatum'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Voorkeurdatum</th><td><?= dateNL($werkbon['voorkeurdatum'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Tijd</th><td><?= hm($werkbon['starttijd'] ?? null) ?> – <?= hm($werkbon['eindtijd'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Status</th><td><?= statusBadge((string)($werkbon['status'] ?? '')) ?></td></tr>
        <tr><th style="text-align:left;">Type werkzaamheden</th><td><?= e($werkbon['type_naam'] ?? '-') ?></td></tr>
        <tr><th style="text-align:left;">Omschrijving</th><td><?= nl2br(e($werkbon['omschrijving'] ?? '-')) ?></td></tr>
    </table>
</div>

<div class="two-column-form">
    <div class="card left-col">
        <h3>Klant</h3>
        <p><strong><?= e($werkbon['debiteurnummer'] ?? '') ?> - <?= e($werkbon['klantnaam'] ?? '-') ?></strong></p>
        <p><?= e($werkbon['klant_adres'] ?? '-') ?><br><?= e($werkbon['klant_postcode'] ?? '') ?> <?= e($werkbon['klant_plaats'] ?? '') ?></p>
    </div>

    <div class="card right-col">
        <h3>Werkadres</h3>
        <p><strong><?= e($werkbon['wa_bedrijfsnaam'] ?? '-') ?></strong></p>
        <p><?= e($werkbon['wa_adres'] ?? '-') ?><br><?= e($werkbon['wa_postcode'] ?? '') ?> <?= e($werkbon['wa_plaats'] ?? '') ?></p>
    </div>
</div>

<div class="card" id="objecten">
    <h3>Objecten (<?= count($objecten) ?>)</h3>

    <div style="margin-bottom:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="/monteur/object_nieuw.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Object toevoegen</a>
        <span style="opacity:.7; font-size:13px;">
            <?php if (!empty($werkbon['werkadres_id'])): ?>
                
            <?php else: ?>
                Alleen klant-objecten zonder werkadres
            <?php endif; ?>
        </span>
    </div>

    <table class="data-table small-table">
        <thead>
        <tr>
            <th>Code</th>
            <th>Omschrijving</th>
            <th>Onderhoud</th>
            <th>Status</th>
            <th style="width:260px;">Acties</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$objecten): ?>
            <tr><td colspan="5" class="text-center">Geen objecten gevonden</td></tr>
        <?php else: foreach ($objecten as $o): ?>
            <tr data-objrow="<?= (int)$o['object_id'] ?>">
                <td><?= e($o['code'] ?? '') ?></td>
                <td><?= e($o['omschrijving'] ?? '') ?></td>
                <td class="col-onderhoud"><?= dateNL($o['datum_onderhoud'] ?? null) ?></td>
                <td class="col-status"><?= e($o['status_naam'] ?? '-') ?></td>
                <td style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn"
                       href="/monteur/object_detail.php?object_id=<?= (int)$o['object_id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>">
                       📄
                    </a>

                    <button type="button"
                            class="btn btn-secondary btnOnderhouden"
                            data-object-id="<?= (int)$o['object_id'] ?>">
                        ✅ (vandaag)
                    </button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Gebruikte artikelen</h3>
    <div style="margin-bottom:10px;">
        <a href="/monteur/werkbon_artikel_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Artikel toevoegen</a>
    </div>

    <table class="data-table small-table">
        <thead>
        <tr>
            <th>Aantal</th><th>Artikelnummer</th><th>Omschrijving</th><th>Verkoopprijs</th><th>Totaal</th><th style="width:160px;">Acties</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$artikelen): ?>
            <tr><td colspan="6" class="text-center">Geen artikelen</td></tr>
        <?php else: foreach ($artikelen as $a): ?>
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
                <td>
                    <a class="btn" href="/monteur/werkbon_artikel_bewerk.php?id=<?= (int)$a['id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>">✏️</a>
                    <a class="btn btn-danger"
                       href="/monteur/werkbon_artikel_verwijder.php?id=<?= (int)$a['id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>"
                       onclick="return confirm('Verwijderen?')">🗑</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Urenregistratie</h3>
    <div style="margin-bottom:10px;">
        <a href="/monteur/uur_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Uur toevoegen</a>
    </div>

    <table class="data-table small-table">
        <thead>
        <tr>
            <th>Datum</th>
            <th>Uursoort</th>
            <th>Start</th>
            <th>Einde</th>
            <th>Uren</th>
            <th>Opmerkingen</th>
            <th>Status</th>
            <th style="width:190px;">Acties</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$uren): ?>
            <tr><td colspan="8" class="text-center">Geen uren</td></tr>
        <?php else: foreach ($uren as $u): ?>
            <?php
                // enum: afgewezen | in_behandeling | goedgekeurd
                $g = (string)($u['goedgekeurd'] ?? 'in_behandeling');
                if ($g === 'goedgekeurd')      $badge = '<span class="badge badge-success">Goedgekeurd</span>';
                elseif ($g === 'afgewezen')    $badge = '<span class="badge badge-danger">Afgekeurd</span>';
                else                           $badge = '<span class="badge badge-warning">In behandeling</span>';

                $start = hm($u['begintijd'] ?? null);
                $einde = hm($u['eindtijd'] ?? null);
                $urenDec = number_format((float)($u['totaal_uren'] ?? 0), 2, ',', '.');

                // eigenaar = monteur_id
                $isOwner = ((int)($u['monteur_id'] ?? 0) === $monteur_id);

                // als goedgekeurd: lock
                $locked = ($g === 'goedgekeurd');
            ?>
            <tr>
                <td><?= dateNL($u['datum'] ?? null) ?></td>
                <td><?= e(($u['uursoort_code'] ?? '') . ' - ' . ($u['uursoort_omschrijving'] ?? '')) ?></td>
                <td><?= e($start) ?></td>
                <td><?= e($einde) ?></td>
                <td><?= e($urenDec) ?></td>
                <td><?= nl2br(e($u['opmerkingen'] ?? '')) ?></td>
                <td><?= $badge ?></td>
                <td>
                    <?php if ($locked): ?>
                        🔒
                    <?php elseif ($isOwner): ?>
                        <a class="btn"
   href="/monteur/uur_bewerken.php?id=<?= (int)$u['werkbon_uur_id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>">
   ✏️
</a>

<a class="btn btn-danger"
   href="/monteur/uur_verwijderen.php?id=<?= (int)$u['werkbon_uur_id'] ?>"
   onclick="return confirm('Verwijderen?')">
   🗑
</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
// Object “Onderhouden (vandaag)”
document.querySelectorAll('.btnOnderhouden').forEach(btn => {
  btn.addEventListener('click', () => {
    const objectId = btn.dataset.objectId;
    btn.disabled = true;

    fetch('/monteur/ajax_object_onderhouden.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        werkbon_id: <?= (int)$werkbon_id ?>,
        object_id: objectId
      })
    })
    .then(r => r.json())
    .then(d => {
      if (!d?.ok) { alert('Fout: ' + (d?.msg || 'onbekend')); return; }

      const row = document.querySelector(`tr[data-objrow="${objectId}"]`);
      if (row) {
        const onderhoud = row.querySelector('.col-onderhoud');
        if (onderhoud && d.today) onderhoud.textContent = d.today.split('-').reverse().join('-');

        const status = row.querySelector('.col-status');
        if (status && d.status_set) status.textContent = 'onderhouden';
      }
    })
    .catch(() => alert('Verbinding mislukt.'))
    .finally(() => { btn.disabled = false; });
  });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
