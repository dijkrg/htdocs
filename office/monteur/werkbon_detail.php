<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /monteur/werkbonnen.php");
    exit;
}

// --------------------------------------------------
// 1) Werkbon ophalen + ownership check (monteur)
// --------------------------------------------------
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
    header("Location: /monteur/werkbonnen.php");
    exit;
}

// Ownership check
if ((int)($werkbon['monteur_id'] ?? 0) !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/monteur_dashboard.php");
    exit;
}

// --------------------------------------------------
// Helpers
// --------------------------------------------------
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
    if ($s === "ingepland") $cls = "wb-badge blauw";
    if ($s === "klaargezet") $cls = "wb-badge oranje";
    if ($s === "compleet" || $s === "afgehandeld") $cls = "wb-badge groen";
    return "<span class='{$cls}'>" . e($status ?: "-") . "</span>";
}

// --------------------------------------------------
// 2) Objecten ophalen
// --------------------------------------------------
$objecten = [];
$q = $conn->prepare("
    SELECT
        o.object_id, o.code, o.omschrijving, o.datum_onderhoud, o.fabricagejaar,
        s.naam AS status_naam
    FROM werkbon_objecten wo
    JOIN objecten o ON wo.object_id = o.object_id
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE wo.werkbon_id = ?
    ORDER BY o.code ASC
");
$q->bind_param("i", $werkbon_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $objecten[] = $r;
$q->close();

// --------------------------------------------------
// 3) Artikelen ophalen
// --------------------------------------------------
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

// --------------------------------------------------
// 4) Uren ophalen (urenregistratie)
// --------------------------------------------------
$uren = [];
$q = $conn->prepare("
    SELECT
        u.*,
        m.voornaam, m.achternaam, m.personeelsnummer,
        us.code AS uursoort_code,
        us.omschrijving AS uursoort_omschrijving
    FROM urenregistratie u
    LEFT JOIN medewerkers m ON u.user_id = m.medewerker_id
    LEFT JOIN uursoorten_uren us ON u.uursoort_id = us.uursoort_id
    WHERE u.werkbon_id = ?
    ORDER BY u.datum ASC, u.starttijd ASC
");
$q->bind_param("i", $werkbon_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $uren[] = $r;
$q->close();

// --------------------------------------------------
// OUTPUT
// --------------------------------------------------
$pageTitle = "Werkbon " . ($werkbon['werkbonnummer'] ?? $werkbon_id);

ob_start();
?>

<div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
    <a href="/monteur/werkbonnen.php" class="btn btn-secondary">⬅ Terug</a>
    <a href="/monteur/handtekening_modal.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">✍️ Handtekening</a>
</div>

<!-- WERKBON GEGEVENS -->
<div class="card">
    <h3>Werkbongegevens</h3>

    <table class="detail-table" style="width:100%;">
        <tr><th style="text-align:left;">Werkbonnummer</th><td><?= e($werkbon['werkbonnummer'] ?? '') ?></td></tr>
        <tr><th style="text-align:left;">Uitvoerdatum</th><td><?= dateNL($werkbon['uitvoerdatum'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Voorkeurdatum</th><td><?= dateNL($werkbon['voorkeurdatum'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Tijd</th><td><?= hm($werkbon['starttijd'] ?? null) ?> – <?= hm($werkbon['eindtijd'] ?? null) ?></td></tr>
        <tr><th style="text-align:left;">Status</th><td><?= statusBadge((string)($werkbon['status'] ?? '')) ?></td></tr>
        <tr><th style="text-align:left;">Type werkzaamheden</th><td><?= e($werkbon['type_naam'] ?? '-') ?></td></tr>
        <tr><th style="text-align:left;">Omschrijving</th><td><?= nl2br(e($werkbon['omschrijving'] ?? '-')) ?></td></tr>

        <tr>
            <th style="text-align:left;">Werk gereed</th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox" id="werkGereedToggle" <?= ((int)($werkbon['werk_gereed'] ?? 0) === 1 ? 'checked' : '') ?>>
                    <span class="toggle-slider"></span>
                </label>
            </td>
        </tr>
    </table>
</div>

<!-- MONTEUR STATUS -->
<div class="card">
    <h3>Monteur status</h3>

    <p style="margin-top:0;">
        <strong>Huidige status:</strong>
        <span id="monteurStatusLabel" class="status-badge" style="margin-left:8px;">
            <?= e(($werkbon['monteur_status'] ?? '') ?: '-') ?>
        </span>
    </p>

    <select id="monteurStatusSelect" class="form-control" style="width:100%; max-width:420px;">
        <option value="">-- kies status --</option>
        <option value="onderweg">Onderweg</option>
        <option value="op_locatie">Op locatie</option>
        <option value="gereed">Gereed</option>
    </select>

    <textarea id="monteurStatusNote" class="form-control" placeholder="Notitie..." style="width:100%; max-width:620px; margin-top:10px;"></textarea>

    <button id="btnUpdateStatus" class="btn" style="margin-top:10px;">Status bijwerken</button>
</div>

<!-- KLANT + WERKADRES -->
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

<!-- OBJECTEN -->
<div class="card">
    <h3>Objecten</h3>

    <div style="margin-bottom:10px;">
        <a href="/monteur/werkbon_object_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Object toevoegen</a>
    </div>

    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Omschrijving</th>
                <th>Onderhoud</th>
                <th>Fabricagejaar</th>
                <th>Status</th>
                <th style="width:120px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($objecten) === 0): ?>
            <tr><td colspan="6" class="text-center">Geen objecten</td></tr>
        <?php else: foreach ($objecten as $o): ?>
            <tr>
                <td><?= e($o['code'] ?? '') ?></td>
                <td><?= e($o['omschrijving'] ?? '') ?></td>
                <td><?= dateNL($o['datum_onderhoud'] ?? null) ?></td>
                <td><?= e($o['fabricagejaar'] ?? '') ?></td>
                <td><?= e($o['status_naam'] ?? '-') ?></td>
                <td>
                    <a class="btn btn-danger"
                       href="/monteur/werkbon_object_verwijder.php?werkbon_id=<?= (int)$werkbon_id ?>&object_id=<?= (int)$o['object_id'] ?>"
                       onclick="return confirm('Object ontkoppelen?')">🗑</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- ARTIKELEN -->
<div class="card">
    <h3>Gebruikte artikelen</h3>

    <div style="margin-bottom:10px;">
        <a href="/monteur/werkbon_artikel_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Artikel toevoegen</a>
    </div>

    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Aantal</th>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th>Verkoopprijs</th>
                <th>Totaal</th>
                <th style="width:160px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($artikelen) === 0): ?>
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

<!-- UREN -->
<div class="card">
    <h3>Urenregistratie</h3>

    <div style="margin-bottom:10px;">
        <a href="/monteur/uren_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn">➕ Uur toevoegen</a>
    </div>

    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Uursoort</th>
                <th>Start</th>
                <th>Einde</th>
                <th>Uren</th>
                <th>Beschrijving</th>
                <th>Status</th>
                <th style="width:190px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($uren) === 0): ?>
            <tr><td colspan="8" class="text-center">Geen uren</td></tr>
        <?php else: foreach ($uren as $u): ?>
            <?php
                $status = (int)($u['goedgekeurd'] ?? 0);
                if ($status === 1)      $badge = '<span class="badge badge-success">Goedgekeurd</span>';
                elseif ($status === -1) $badge = '<span class="badge badge-danger">Afgekeurd</span>';
                else                    $badge = '<span class="badge badge-warning">In behandeling</span>';

                $duurMin = (int)($u['duur_minuten'] ?? 0);
                $duurUur = $duurMin > 0 ? number_format($duurMin / 60, 2, ',', '.') : '0,00';
                $isOwner = ((int)($u['user_id'] ?? 0) === $monteur_id);
            ?>
            <tr>
                <td><?= dateNL($u['datum'] ?? null) ?></td>
                <td><?= e(($u['uursoort_code'] ?? '') . ' - ' . ($u['uursoort_omschrijving'] ?? '')) ?></td>
                <td><?= hm($u['starttijd'] ?? null) ?></td>
                <td><?= hm($u['eindtijd'] ?? null) ?></td>
                <td><?= $duurUur ?></td>
                <td><?= nl2br(e($u['beschrijving'] ?? '')) ?></td>
                <td><?= $badge ?></td>
                <td>
                    <?php if ($status === 1): ?>
                        🔒
                    <?php elseif ($isOwner): ?>
                        <a class="btn" href="/monteur/uren_bewerk.php?id=<?= (int)$u['uur_id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>">✏️</a>
                        <a class="btn btn-danger"
                           href="/monteur/uren_verwijderen.php?id=<?= (int)$u['uur_id'] ?>&werkbon_id=<?= (int)$werkbon_id ?>"
                           onclick="return confirm('Verwijderen?')">🗑</a>
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
// Werk gereed toggle
document.getElementById("werkGereedToggle")?.addEventListener("change", function(){
    fetch("/monteur/werkbon_toggle_gereed.php", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            id: <?= (int)$werkbon_id ?>,
            gereed: this.checked ? 1 : 0
        })
    }).catch(()=>{});
});

// Monteur status update
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
    .catch(() => alert("Fout bij verbinden."));
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
