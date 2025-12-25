<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot voorraadcorrecties.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "⚖️ Voorraadcorrectie";

// 📦 Magazijnen ophalen
$magazijnen = $conn->query("SELECT magazijn_id, naam FROM magazijnen ORDER BY naam ASC");

// 📚 Artikelen ophalen
$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving, voorraad
    FROM artikelen
    ORDER BY artikelnummer ASC
");

// 🧮 Verwerken correctie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artikel_id   = intval($_POST['artikel_id'] ?? 0);
    $magazijn_id  = intval($_POST['magazijn_id'] ?? 0);
    $invoerAantal = (int)($_POST['nieuw_aantal'] ?? 0);
    $reden        = trim($_POST['reden'] ?? '');
    $medewerker_id = $_SESSION['user']['id'] ?? null;

    if ($artikel_id <= 0 || $magazijn_id <= 0 || $reden === '') {
        setFlash("Selecteer een artikel, magazijn en reden.", "error");
        header("Location: transactie_correctie.php");
        exit;
    }

    // 🔁 Richting bepalen (+ of -)
    $mutatie = $invoerAantal;
    if (in_array($reden, ['Voorraadmutatie (-)', 'Breuk', 'Garantie'])) {
        $mutatie = -abs($invoerAantal);
    } elseif (in_array($reden, ['Voorraadmutatie (+)'])) {
        $mutatie = abs($invoerAantal);
    }

    // Huidige voorraad ophalen
    $res = $conn->prepare("SELECT aantal FROM voorraad_magazijn WHERE artikel_id=? AND magazijn_id=?");
    $res->bind_param("ii", $artikel_id, $magazijn_id);
    $res->execute();
    $res->bind_result($huidige);
    $res->fetch();
    $res->close();
    if ($huidige === null) $huidige = 0;

    $nieuwAantal = $huidige + $mutatie;

    // Updaten voorraad in dit magazijn
    $stmt = $conn->prepare("
        INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE aantal = VALUES(aantal), laatste_update = NOW()
    ");
    $stmt->bind_param("iii", $artikel_id, $magazijn_id, $nieuwAantal);
    $stmt->execute();
    $stmt->close();

    // Herbereken totale voorraad
    $updArt = $conn->prepare("
        UPDATE artikelen 
        SET voorraad = (
            SELECT SUM(aantal) FROM voorraad_magazijn WHERE artikel_id = ?
        )
        WHERE artikel_id = ?
    ");
    $updArt->bind_param("ii", $artikel_id, $artikel_id);
    $updArt->execute();
    $updArt->close();

    // Log transactie
    $type = 'correctie';
    $stmt = $conn->prepare("
        INSERT INTO voorraad_transacties (artikel_id, magazijn_id, aantal, type, medewerker_id, opmerking, status, geboekt_op)
        VALUES (?, ?, ?, ?, ?, ?, 'geboekt', NOW())
    ");
    $stmt->bind_param("iiisis", $artikel_id, $magazijn_id, $mutatie, $type, $medewerker_id, $reden);
    $stmt->execute();
    $stmt->close();

    setFlash("Correctie geboekt: artikel #{$artikel_id} — wijziging {$mutatie} ✅", "success");
    header("Location: transactie_correctie.php");
    exit;
}

// 📜 Laatste correcties
$correcties = $conn->query("
    SELECT 
        vt.transactie_id, vt.datum, vt.aantal, vt.opmerking AS reden,
        a.artikelnummer, a.omschrijving, m.naam AS magazijn,
        CONCAT(u.voornaam, ' ', u.achternaam) AS medewerker
    FROM voorraad_transacties vt
    LEFT JOIN artikelen a ON a.artikel_id = vt.artikel_id
    LEFT JOIN magazijnen m ON m.magazijn_id = vt.magazijn_id
    LEFT JOIN medewerkers u ON u.medewerker_id = vt.medewerker_id
    WHERE vt.type = 'correctie'
    ORDER BY vt.datum DESC
    LIMIT 20
");

ob_start();
?>

<div class="page-header">
    <h2>⚖️ Voorraadcorrectie</h2>
    <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">⬅ Terug naar Magazijnbeheer</a>
    </div>
</div>

<div class="card">

    <!-- 🔍 Live zoekveld -->
    <div style="margin-bottom:10px;">
        <label for="artikelFilter">🔍 Zoek artikel</label>
        <input type="text"
               id="artikelFilter"
               placeholder="Zoek op artikelnummer of omschrijving…"
               style="width:100%; margin-top:4px; padding:8px 10px; border:1px solid #cfd8dc; border-radius:6px;">
    </div>

    <!-- 🧮 Compact correctieformulier -->
    <form method="post" id="correctieForm" 
          style="display:grid; gap:10px; grid-template-columns: 2fr 1fr 0.7fr 1fr; align-items:end;">

        <div>
            <label for="artikel_id">Artikel</label>
            <select name="artikel_id" id="artikel_id" required style="width:100%;">
                <option value="">-- Kies artikel --</option>
                <?php mysqli_data_seek($artikelen, 0); while ($a = $artikelen->fetch_assoc()): ?>
                    <option value="<?= $a['artikel_id'] ?>">
                        <?= htmlspecialchars($a['artikelnummer']) ?> — <?= htmlspecialchars($a['omschrijving']) ?>
                        (voorraad: <?= (int)$a['voorraad'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label for="magazijn_id">Magazijn</label>
            <select name="magazijn_id" id="magazijn_id" required>
                <option value="">-- Kies magazijn --</option>
                <?php while ($m = $magazijnen->fetch_assoc()): ?>
                    <option value="<?= $m['magazijn_id'] ?>"><?= htmlspecialchars($m['naam']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label for="nieuw_aantal">Aantal</label>
            <input type="number" name="nieuw_aantal" id="nieuw_aantal" min="1" step="1"
                   required placeholder="aantal" style="width:100%; text-align:left;">
        </div>

        <div>
            <label for="reden">Type mutatie</label>
            <select name="reden" id="reden" required>
                <option value="">-- Kies type --</option>
                <option value="Voorraadmutatie (+)">Voorraadmutatie (+)</option>
                <option value="Voorraadmutatie (-)">Voorraadmutatie (-)</option>
                <option value="Breuk">Breuk</option>
                <option value="Garantie">Garantie</option>
            </select>
        </div>

        <!-- Knoppen -->
        <div style="grid-column: span 4; margin-top:15px; text-align:left;">
            <button type="submit" class="btn btn-primary">✅ Correctie boeken</button>
            <a href="index.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<!-- 📋 Laatste correcties -->
<div class="card" style="margin-top:25px;">
    <h3><i class="fa-solid fa-clock-rotate-left"></i> Laatste 20 correcties</h3>
    <table class="data-table compact-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Artikel</th>
                <th>Omschrijving</th>
                <th style="text-align:right;">Mutatie</th>
                <th>Magazijn</th>
                <th>Type</th>
                <th>Gebruiker</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($correcties->num_rows > 0): ?>
                <?php while ($r = $correcties->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($r['datum'])) ?></td>
                        <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                        <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                        <td style="text-align:right; <?= $r['aantal'] < 0 ? 'color:#d32f2f;' : 'color:#2e7d32;' ?>">
                            <?= (int)$r['aantal'] ?>
                        </td>
                        <td><?= htmlspecialchars($r['magazijn']) ?></td>
                        <td><?= htmlspecialchars($r['reden']) ?></td>
                        <td><?= htmlspecialchars($r['medewerker'] ?? '-') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Nog geen correcties uitgevoerd.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// 🔍 Live filter op dropdown-artikelen
(function(){
    const input   = document.getElementById("artikelFilter");
    const select  = document.getElementById("artikel_id");
    if (!input || !select) return;

    input.addEventListener("input", function() {
        const q = input.value.trim().toLowerCase();
        Array.from(select.options).forEach(opt => {
            const txt = opt.textContent.toLowerCase();
            opt.hidden = !txt.includes(q);
        });
        // Reset selectie bij filter
        select.selectedIndex = [...select.options].findIndex(o => !o.hidden && o.value !== "");
    });
})();

// 🎨 Dynamische kleur bij type mutatie
const redenSelect = document.getElementById("reden");
redenSelect.addEventListener("change", function() {
    if (this.value.includes("(+)")) {
        this.style.background = "#e7f9e9";
        this.style.color = "#2e7d32";
    } else if (this.value.includes("(-)") || this.value === "Breuk" || this.value === "Garantie") {
        this.style.background = "#fdecea";
        this.style.color = "#d32f2f";
    } else {
        this.style.background = "";
        this.style.color = "";
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
