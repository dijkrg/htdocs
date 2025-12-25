<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ✅ Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang tot artikelen.", "error");
    header("Location: index.php");
    exit;
}

$pageTitle = "📦 Artikelen overzicht";
ob_start();

// ✅ Actuele voorraad uit voorraad_magazijn (som per artikel)
$sql = "
SELECT 
    a.artikel_id,
    a.artikelnummer,
    a.omschrijving,
    a.inkoopprijs,
    a.verkoopprijs,
    a.btw_tarief,
    a.minimale_voorraad,
    COALESCE((
        SELECT SUM(vm.aantal)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ), 0) AS huidige_voorraad
FROM artikelen a
WHERE (a.categorie IS NULL OR a.categorie <> 'Administratie')
ORDER BY a.artikelnummer ASC
";

$result = $conn->query($sql);
?>

<div class="page-header">
    <h2>📦 Artikelen</h2>
    <div class="header-actions">
        <a href="artikel_toevoegen.php" class="btn">➕ Nieuw artikel</a>
        <a href="leveranciers/bestelling_toevoegen.php" class="btn btn-accent">🛒 Nieuwe bestelling</a>
    </div>
</div>

<!-- 🔍 Live zoekbalk -->
<div class="filter-bar" style="margin-bottom:12px; display:flex; gap:10px; align-items:center;">
    <input type="text" id="zoekInput" placeholder="🔎 Zoeken op artikelnummer of omschrijving..." 
           style="flex:1; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:15px;">
    <button id="resetZoek" class="btn btn-secondary" style="padding:8px 12px;">✖ Reset</button>
</div>

<div class="card">
<table class="data-table" id="artikelenTabel">
    <thead>
        <tr>
            <th>Artikelnummer</th>
            <th>Omschrijving</th>
            <th style="width:100px;">Inkoop (€)</th>
            <th style="width:100px;">Verkoop (€)</th>
            <th style="width:80px;">BTW (%)</th>
            <th style="width:90px; text-align:right;">Voorraad</th>
            <th style="width:160px;">Status</th>
            <th style="width:120px;">Acties</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($artikel = $result->fetch_assoc()): ?>
            <?php
                $voorraad = (int)$artikel['huidige_voorraad'];
                $minimaal = max(0, (int)($artikel['minimale_voorraad'] ?? 0));

                // 🎯 Statuslogica
                if ($voorraad <= 0 && $minimaal > 0) {
                    $voorraadKleur = '#000'; // voorraadwaarde in zwart
                    $statusHtml = "<span style='color:#d9534f; font-weight:bold;'>⚠️ Te bestellen</span>";
                } elseif ($voorraad <= 0 && $minimaal == 0) {
                    $voorraadKleur = '#000';
                    $statusHtml = "<span style='color:#000; font-weight:bold;'>-</span>";
                } elseif ($voorraad > 0 && $voorraad <= $minimaal) {
                    $voorraadKleur = '#d9534f';
                    $statusHtml = "<span style='color:#d9534f; font-weight:bold;'>⚠️ Te bestellen</span>";
                } else {
                    $voorraadKleur = '#2e7d32';
                    $statusHtml = "<span style='color:#2e7d32;'>✔️ Op voorraad</span>";
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($artikel['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($artikel['omschrijving']) ?></td>
                <td style="text-align:right;">€ <?= number_format((float)$artikel['inkoopprijs'], 2, ',', '.') ?></td>
                <td style="text-align:right;">€ <?= number_format((float)$artikel['verkoopprijs'], 2, ',', '.') ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($artikel['btw_tarief']) ?></td>
                <td style="text-align:right; font-weight:bold; color:<?= $voorraadKleur ?>;">
                    <?= $voorraad ?>
                </td>
                <td style="text-align:center;"><?= $statusHtml ?></td>
                <td class="actions">
                    <a href="artikel_detail.php?id=<?= $artikel['artikel_id'] ?>" title="Details">📄</a>
                    <a href="artikel_bewerk.php?id=<?= $artikel['artikel_id'] ?>" title="Bewerken">✏️</a>
                    <a href="artikel_verwijder.php?id=<?= $artikel['artikel_id'] ?>" 
                       title="Verwijderen" 
                       onclick="return confirm('Artikel verwijderen?')">🗑</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="8" style="text-align:center;">Geen artikelen gevonden.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- ⚡ JavaScript live filter -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const zoekInput = document.getElementById('zoekInput');
    const resetBtn  = document.getElementById('resetZoek');
    const tabel     = document.getElementById('artikelenTabel');
    const rijen     = tabel.getElementsByTagName('tr');

    zoekInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        for (let i = 1; i < rijen.length; i++) { // Skip header
            const cellen = rijen[i].getElementsByTagName('td');
            if (cellen.length > 1) {
                const nummer = cellen[0].textContent.toLowerCase();
                const omschr = cellen[1].textContent.toLowerCase();
                rijen[i].style.display = (nummer.includes(filter) || omschr.includes(filter)) ? '' : 'none';
            }
        }
    });

    resetBtn.addEventListener('click', function() {
        zoekInput.value = '';
        for (let i = 1; i < rijen.length; i++) rijen[i].style.display = '';
        zoekInput.focus();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
?>
