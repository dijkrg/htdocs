<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📦 Bestelling details";

// ID ophalen
$bestelling_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bestelling_id <= 0) {
    setFlash("Geen bestelling geselecteerd.", "error");
    header("Location: bestellingen.php");
    exit;
}

// 📋 Bestelling ophalen met leverancier
$bestelling = $conn->query("
    SELECT 
        b.bestelling_id,
        b.bestelnummer,
        b.bestel_datum,
        b.status,
        b.opmerking,
        l.naam AS leverancier_naam,
        l.contactpersoon,
        l.telefoon,
        l.email
    FROM bestellingen b
    LEFT JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    WHERE b.bestelling_id = $bestelling_id
")->fetch_assoc();

if (!$bestelling) {
    setFlash("Bestelling niet gevonden.", "error");
    header("Location: bestellingen.php");
    exit;
}

// Artikelen ophalen
$regels = $conn->query("
    SELECT ba.id, ba.aantal, ba.inkoopprijs,
           a.artikelnummer, a.omschrijving
    FROM bestelling_artikelen ba
    LEFT JOIN artikelen a ON ba.artikel_id = a.artikel_id
    WHERE ba.bestelling_id = $bestelling_id
    ORDER BY ba.id ASC
");


ob_start();
?>

<div class="page-header">
    <h2>📦 Bestelling <?= htmlspecialchars($bestelling['bestelnummer']) ?></h2>
    <div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="bestellingen.php" class="btn btn-secondary">⬅ Terug</a>
        <a href="bestelling_bewerk.php?id=<?= $bestelling_id ?>" class="btn">✏️ Bewerken</a>
        <a href="bestelling_pdf.php?id=<?= $bestelling_id ?>" target="_blank" class="btn btn-accent">📄 PDF</a>
    </div>
</div>

<div class="card">
    <table class="data-table detail-layout">
        <tbody>
            <tr>
                <th>Bestelnummer</th>
                <td><?= htmlspecialchars($bestelling['bestelnummer']) ?></td>
            </tr>
<tr>
    <th>Leverancier</th>
    <td>
        <?php if (!empty($bestelling['leverancier_naam'])): ?>
            <strong><?= htmlspecialchars($bestelling['leverancier_naam']) ?></strong><br>
            <?php if (!empty($bestelling['adres'])): ?>
                <?= htmlspecialchars($bestelling['adres']) ?><br>
            <?php endif; ?>
            <?php if (!empty($bestelling['postcode']) || !empty($bestelling['plaats'])): ?>
                <?= htmlspecialchars(trim(($bestelling['postcode'] ?? '') . ' ' . ($bestelling['plaats'] ?? ''))) ?>
            <?php endif; ?>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
	    <tr>
    		<th>Besteldatum</th>
    	    <td>
        	<?= !empty($bestelling['bestel_datum'])
            	? htmlspecialchars(date('d-m-Y', strtotime($bestelling['bestel_datum'])))
            	: '-' ?>
    	   </td>
	   </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php
                        $kleur = match ($bestelling['status']) {
                            'open' => '#f0ad4e',
                            'gedeeltelijk' => '#5bc0de',
                            'afgehandeld' => '#5cb85c',
                            'geannuleerd' => '#d9534f',
                            default => '#777'
                        };
                    ?>
                    <span style="background:<?= $kleur ?>; color:#fff; padding:3px 8px; border-radius:4px; font-size:13px;">
                        <?= ucfirst($bestelling['status']) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Opmerking</th>
                <td><?= nl2br(htmlspecialchars($bestelling['opmerking'] ?? '-')) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:20px;">
    <h3>📋 Artikelen in deze bestelling</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th style="width:80px; text-align:right;">Aantal</th>
                <th style="width:100px; text-align:right;">Inkoopprijs</th>
                <th style="width:160px; text-align:right;">Totaal</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($regels->num_rows > 0): 
                $totaal = 0;
                while($r = $regels->fetch_assoc()):
                    $regelTotaal = $r['aantal'] * $r['inkoopprijs'];
                    $totaal += $regelTotaal;
            ?>
                <tr>
                    <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                    <td style="text-align:right;"><?= (int)$r['aantal'] ?></td>
                    <td style="text-align:right; white-space:nowrap;">€ <?= number_format($r['inkoopprijs'], 2, ',', '.') ?></td>
                    <td style="text-align:right; font-weight:bold; white-space:nowrap;">€ <?= number_format($regelTotaal, 2, ',', '.') ?></td>
                </tr>
            <?php endwhile; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="4" style="text-align:right;">Totaal bestelling:</td>
                    <td style="text-align:right;">€ <?= number_format($totaal, 2, ',', '.') ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">Geen artikelen gekoppeld.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:20px;">
    <p><strong>📅 Laatste update:</strong> <?= date('d-m-Y H:i') ?></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
