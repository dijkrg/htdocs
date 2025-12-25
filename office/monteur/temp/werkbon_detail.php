<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Rol bepalen NA session_start()
$rol = $_SESSION['user']['rol'] ?? '';
$isMonteur = ($rol === 'Monteur');

/* 📌 Datum formatteren */
function formatDateNL($date) {
    if (!$date || $date === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d-m-Y') : '';
}

$werkbon_id = intval($_GET['id'] ?? 0);

if ($werkbon_id === 0) {
    setFlash("Geen werkbon ID opgegeven.", "error");
    header("Location: werkbonnen.php");
    exit;
}

// 📌 Werkbon ophalen + type werkzaamheden JOIN
$stmt = $conn->prepare("
    SELECT w.*, 
           k.debiteurnummer, k.bedrijfsnaam AS klantnaam, k.adres AS klant_adres, k.postcode AS klant_postcode, k.plaats AS klant_plaats,
           wa.bedrijfsnaam AS wa_bedrijfsnaam, wa.adres AS wa_adres, wa.postcode AS wa_postcode, wa.plaats AS wa_plaats,
           m.voornaam, m.achternaam,
           t.naam AS type_naam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN medewerkers m ON w.monteur_id = m.medewerker_id
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.werkbon_id = ?
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

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// 📌 Artikelen ophalen
$artikelen = $conn->query("
    SELECT wa.*, a.artikelnummer, a.omschrijving, a.verkoopprijs
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON wa.artikel_id = a.artikel_id
    WHERE wa.werkbon_id = $werkbon_id
");

// 📌 Urenregistraties ophalen
$uren = $conn->query("
    SELECT u.*, m.voornaam, m.achternaam, m.personeelsnummer,
           us.code AS uursoort_code, us.omschrijving AS uursoort_omschrijving
    FROM werkbon_uren u
    LEFT JOIN medewerkers m ON u.monteur_id = m.medewerker_id
    LEFT JOIN uursoorten us ON u.uursoort_id = us.uursoort_id
    WHERE u.werkbon_id = $werkbon_id
    ORDER BY u.datum, u.begintijd
");

// 📌 Objecten ophalen
$objecten = $conn->query("
    SELECT o.object_id, o.code, o.omschrijving, o.datum_onderhoud, o.fabricagejaar, s.naam AS status_naam
    FROM werkbon_objecten wo
    JOIN objecten o ON wo.object_id = o.object_id
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE wo.werkbon_id = $werkbon_id
    ORDER BY o.code ASC
");

ob_start();
?>
<div class="header-actions">

<?php if (!$isMonteur): ?>
    <!-- ADMIN / MANAGER / PLANNING -->
    <a href="werkbon_pdf.php?id=<?= $werkbon_id ?>" target="_blank" class="btn">📄 PDF</a>
    <a href="werkbon_bewerk.php?id=<?= $werkbon_id ?>" class="btn">✏️ Bewerken</a>
    <a href="werkbonnen.php" class="btn btn-secondary">⬅ Terug</a>

<?php else: ?>
    <!-- MONTEUR -->
    <a href="/monteur/monteur_werkbon.php" class="btn btn-secondary">⬅ Mijn werkbonnen</a>
<?php endif; ?>

</div>

<!-- Tegel 1: Werkbongegevens -->
<div class="card">
    <h3>Werkbongegevens</h3>
    <table class="detail-table">
        <tr><th>Werkbonnummer</th><td><?= e($werkbon['werkbonnummer']) ?></td></tr>
        <tr><th>Uitvoerdatum</th><td><?= formatDateNL($werkbon['uitvoerdatum']) ?></td></tr>
        <tr><th>Voorkeurdatum</th><td><?= formatDateNL($werkbon['voorkeurdatum']) ?></td></tr>
        <tr><th>Monteur</th><td><?= e(($werkbon['voornaam'] ?? '')." ".($werkbon['achternaam'] ?? '')) ?></td></tr>
        <tr><th>Status</th><td><span id="statusText"><?= e($werkbon['status']) ?></span></td></tr>
        <tr><th>Type werkzaamheden</th><td><?= e($werkbon['type_naam']) ?></td></tr>
        <tr><th>Omschrijving</th><td><?= nl2br(e($werkbon['omschrijving'])) ?></td></tr>
        <tr>
            <th>Werk gereed</th>
            <td>
                <label class="toggle-switch" title="Zet werk gereed aan/uit">
                    <input 
                        type="checkbox" 
                        id="werkGereedToggle"
                        <?= (int)($werkbon['werk_gereed'] ?? 0) === 1 ? 'checked' : '' ?>
                    >
                    <span class="toggle-slider"></span>
                </label>
            </td>
        </tr>
    </table>
</div>

<?php if ($isMonteur): ?>
<div class="card">
    <h3>Monteur status</h3>

    <div id="monteurStatusBlock">
        <p><strong>Huidige status:</strong>
            <span id="monteurStatusLabel" class="status-badge status-<?= e($werkbon['monteur_status'] ?: 'leeg') ?>">
                <?= e($werkbon['monteur_status'] ?: '-') ?>
            </span>
        </p>

        <div style="margin-top:10px;">
            <label><strong>Wijzig status:</strong></label>
            <select id="monteurStatusSelect" class="form-control">
                <option value="">-- kies status --</option>
                <option value="onderweg">Onderweg</option>
                <option value="op_locatie">Op locatie</option>
                <option value="gereed">Gereed</option>
            </select>
        </div>

        <textarea id="monteurStatusNote"
                  class="form-control"
                  placeholder="Optionele notitie (bijv. vertraging, extra info)"
                  style="margin-top:10px;"></textarea>

        <button class="btn btn-primary" id="btnUpdateStatus" style="margin-top:10px;">
            Status bijwerken
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Tegel 2 + 3 naast elkaar -->
<div class="two-column-form">
    <!-- Klant -->
    <div class="card left-col">
        <h3>Klant</h3>
        <p><strong><?= e($werkbon['debiteurnummer']) ?> - <?= e($werkbon['klantnaam']) ?></strong></p>
        <p><?= e($werkbon['klant_adres']) ?><br><?= e($werkbon['klant_postcode']).' '.e($werkbon['klant_plaats']) ?></p>
    </div>

    <!-- Werkadres -->
    <div class="card right-col">
        <h3>Werkadres</h3>
        <p><strong><?= e($werkbon['wa_bedrijfsnaam']) ?></strong></p>
        <p><?= e($werkbon['wa_adres']) ?><br><?= e($werkbon['wa_postcode']).' '.e($werkbon['wa_plaats']) ?></p>
    </div>
</div>

<!-- Objecten -->
<div class="card">
    <h3>Objecten</h3>
    <?php if ($isMonteur): ?>
    <a href="werkbon_object_toevoegen.php?werkbon_id=<?= $werkbon_id ?>" class="btn">➕ Object toevoegen</a>
     <?php endif; ?>
    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Omschrijving</th>
                <th>Datum onderhoud</th>
                <th>Fabricagejaar</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($objecten->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center; color:#777;">Geen objecten gekoppeld</td></tr>
        <?php else: ?>
            <?php while ($o = $objecten->fetch_assoc()): ?>
                <tr>
                    <td><?= e($o['code']) ?></td>
                    <td><?= e($o['omschrijving']) ?></td>
                    <td><?= formatDateNL($o['datum_onderhoud']) ?></td>
                    <td><?= e($o['fabricagejaar']) ?></td>
                    <td><?= e($o['status_naam'] ?? '-') ?></td>
                    <td>
<?php if ($isMonteur): ?>
    <a href="werkbon_object_verwijder.php?werkbon_id=<?= $werkbon_id ?>&object_id=<?= $o['object_id'] ?>"
       class="btn btn-danger"
       onclick="return confirm('Object ontkoppelen?')">🗑</a>
<?php endif; ?>
                   </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Artikelen -->
<div class="card">
    <h3>Gebruikte artikelen</h3>
    <?php if ($isMonteur): ?>
    <a href="werkbon_artikel_toevoegen.php?werkbon_id=<?= $werkbon_id ?>" class="btn">➕ Artikel toevoegen</a>
<?php endif; ?>
    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Aantal</th>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th>Verkoopprijs</th>
                <th>Totaal</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($artikelen->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center; color:#777;">Geen artikelen toegevoegd</td></tr>
        <?php else: ?>
            <?php while ($a = $artikelen->fetch_assoc()): ?>
                <tr>
                    <td><?= e($a['aantal']) ?></td>
                    <td><?= e($a['artikelnummer']) ?></td>
                    <td><?= e($a['omschrijving']) ?></td>
                    <td>€ <?= number_format($a['verkoopprijs'], 2, ',', '.') ?></td>
                    <td>€ <?= number_format($a['verkoopprijs'] * $a['aantal'], 2, ',', '.') ?></td>
                    <td>
                        <a href="werkbon_artikel_bewerk.php?id=<?= $a['id'] ?>&werkbon_id=<?= $werkbon_id ?>" class="btn">✏️</a>
                        <a href="werkbon_artikel_verwijder.php?id=<?= $a['id'] ?>&werkbon_id=<?= $werkbon_id ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Weet je zeker dat je dit artikel wilt verwijderen?')">🗑</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Urenregistratie -->
<div class="card">
    <h3>Urenregistratie</h3>
    <a href="uren_toevoegen.php?werkbon_id=<?= $werkbon_id ?>" class="btn">➕ Uur toevoegen</a>

    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Monteur</th>
                <th>Uursoort</th>
                <th>Begintijd</th>
                <th>Eindtijd</th>
                <th>Totaal uren</th>
                <th>Opmerkingen</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($uren->num_rows === 0): ?>
            <tr>
                <td colspan="9" style="text-align:center; color:#777;">Geen urenregistraties toegevoegd</td>
            </tr>
        <?php else: ?>
            <?php while ($u = $uren->fetch_assoc()): ?>
                <tr>
                    <td><?= date("d-m-Y", strtotime($u['datum'])) ?></td>
                    <td><?= e($u['voornaam']." ".$u['achternaam'])." (".e($u['personeelsnummer']).")" ?></td>
                    <td><?= e($u['uursoort_code']." - ".$u['uursoort_omschrijving']) ?></td>
                    <td><?= e(substr($u['begintijd'],0,5)) ?></td>
                    <td><?= e(substr($u['eindtijd'],0,5)) ?></td>
                    <td><?= number_format($u['totaal_uren'], 2, ',', '.') ?></td>
                    <td><?= nl2br(e($u['opmerkingen'])) ?></td>
                    <td>
                        <?php
                        if ($u['goedgekeurd'] === 'in_behandeling') echo "⏳ In behandeling";
                        elseif ($u['goedgekeurd'] === 'goedgekeurd') echo "✅ Goedgekeurd";
                        else echo "❌ Afgewezen";
                        ?>
                    </td>
                    <td>
                        <?php if ($u['goedgekeurd'] !== 'goedgekeurd'): ?>
                            <a href="uren_bewerk.php?id=<?= $u['werkbon_uur_id'] ?>&werkbon_id=<?= $werkbon_id ?>" class="btn">✏️</a>
                            <a href="uren_verwijder.php?id=<?= $u['werkbon_uur_id'] ?>&werkbon_id=<?= $werkbon_id ?>" class="btn btn-danger" onclick="return confirm('Uur verwijderen?')">🗑</a>
                        <?php else: ?> 🔒 <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
(function() {
    const toggle = document.getElementById('werkGereedToggle');
    if (!toggle) return;
    const statusText = document.getElementById('statusText');
    const werkbonId  = <?= (int)$werkbon_id ?>;

    toggle.addEventListener('change', async function() {
        const gereed = this.checked ? 1 : 0;
        this.disabled = true;

        try {
            const resp = await fetch('werkbon_toggle_gereed.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ id: werkbonId, gereed })
            });
            const data = await resp.json();
            if (data.ok) {
                statusText.textContent = data.status || '';
            } else {
                this.checked = !this.checked;
                alert(data.msg || 'Bijwerken mislukt.');
            }
        } catch (e) {
            this.checked = !this.checked;
            alert('Fout: ' + e.message);
        } finally {
            this.disabled = false;
        }
    });
})();
</script>

<script>
document.getElementById("btnUpdateStatus")?.addEventListener("click", function () {
    const status = document.getElementById("monteurStatusSelect").value.trim();
    const note   = document.getElementById("monteurStatusNote").value.trim();

    if (!status) {
        alert("Kies een status.");
        return;
    }

    fetch("/monteur/update_monteur_status.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            id: <?= (int)$werkbon_id ?>,
            status: status,
            note: note
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) {
            alert(data.msg || "Fout bij opslaan");
            return;
        }

        // UI bijwerken
        document.getElementById("monteurStatusLabel").textContent = status;
        document.getElementById("monteurStatusLabel").className = "status-badge status-" + status;

        alert("Status bijgewerkt!");
    })
    .catch(e => alert("Netwerkfout: " + e));
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Werkbon detail";
include __DIR__ . "/template/template.php";
