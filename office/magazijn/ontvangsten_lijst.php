<?php
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot ontvangsten.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📥 Ontvangsten overzicht";

// 📦 Haal alle recente ontvangsten op
$sql = "
    SELECT 
        vt.transactie_id,
        vt.datum,
        vt.type,
        vt.aantal,
        vt.opmerking,
        a.artikelnummer,
        a.omschrijving,
        m.naam AS magazijn,
        b.bestelnummer,
        l.naam AS leverancier
    FROM voorraad_transacties vt
    LEFT JOIN artikelen a ON vt.artikel_id = a.artikel_id
    LEFT JOIN magazijnen m ON vt.magazijn_id = m.magazijn_id
    LEFT JOIN bestellingen b ON vt.opmerking LIKE CONCAT('%#', b.bestelling_id, '%')
    LEFT JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    WHERE vt.type = 'ontvangst'
    ORDER BY vt.datum DESC
    LIMIT 250
";
$result = $conn->query($sql);

ob_start();
?>
<div class="page-header">
    <h2>📥 Ontvangsten</h2>
    <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">⬅ Terug naar magazijn</a>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th style="text-align:right;">Aantal</th>
                <th>Magazijn</th>
                <th>Leverancier</th>
                <th>Bestelling</th>
                <th>Opmerking</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($r = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($r['datum'])) ?></td>
                        <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                        <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                        <td style="text-align:right;"><?= (int)$r['aantal'] ?></td>
                        <td><?= htmlspecialchars($r['magazijn']) ?></td>
                        <td><?= htmlspecialchars($r['leverancier'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['bestelnummer'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['opmerking']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">Geen ontvangsten gevonden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
