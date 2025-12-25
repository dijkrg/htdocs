<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// 🔐 Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot te bestellen artikelen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "⚠️ Te bestellen artikelen";
ob_start();

// ✅ Query: alle artikelen met voorraad ≤ minimale voorraad
$sql = "
SELECT 
    a.artikel_id,
    a.artikelnummer,
    a.omschrijving,
    a.minimale_voorraad,
    a.leverancier_id,
    COALESCE((
        SELECT SUM(vm.aantal)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ), 0) AS huidige_voorraad,
    l.naam AS leverancier_naam
FROM artikelen a
LEFT JOIN leveranciers l ON a.leverancier_id = l.leverancier_id
WHERE a.minimale_voorraad IS NOT NULL 
  AND a.minimale_voorraad > 0
  AND COALESCE((
        SELECT SUM(vm.aantal)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ), 0) <= a.minimale_voorraad
ORDER BY a.artikelnummer ASC
";

$result = $conn->query($sql);
?>

<div class="page-header">
    <h2>⚠️ Te bestellen artikelen</h2>
    <div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="index.php" class="btn btn-secondary">⬅ Terug naar magazijn</a>
        <a href="../leveranciers/bestelling_toevoegen.php" class="btn">🛒 Nieuwe bestelling</a>
        <a href="te_bestellen_pdf.php" target="_blank" class="btn btn-accent">📄 Download PDF</a>
    </div>
</div>

<div class="card">
<table class="data-table">
    <thead>
        <tr>
            <th>Artikelnummer</th>
            <th>Omschrijving</th>
            <th style="width:100px; text-align:right;">Voorraad</th>
            <th style="width:130px; text-align:right;">Minimale voorraad</th>
            <th style="width:160px;">Status</th>
            <th>Leverancier</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($r = $result->fetch_assoc()): ?>
                <?php
                    $voorraad = (int)$r['huidige_voorraad'];
                    $minimaal = (int)$r['minimale_voorraad'];
                    $status = "<span style='color:#d9534f; font-weight:bold;'>⚠️ Te bestellen</span>";
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                    <td style="text-align:right;"><?= $voorraad ?></td>
                    <td style="text-align:right;"><?= $minimaal ?></td>
                    <td><?= $status ?></td>
                    <td><?= htmlspecialchars($r['leverancier_naam'] ?? '-') ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">Geen artikelen onder minimale voorraad 🎉</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
