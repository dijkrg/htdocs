<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// 🔐 Alleen ingelogde gebruikers
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$contract_id = intval($_GET['id'] ?? 0);
if ($contract_id <= 0) {
    setFlash("Ongeldig contract-ID.", "error");
    header("Location: contracten.php");
    exit;
}

/* ======================================================
   CONTRACT OPHALEN
====================================================== */

$stmt = $conn->prepare("
    SELECT c.*, 
           k.bedrijfsnaam AS klant_naam, 
           k.debiteurnummer, 
           w.bedrijfsnaam AS werkadres_naam,
           w.adres AS werkadres_adres,
           w.postcode AS werkadres_postcode,
           w.plaats AS werkadres_plaats,
           t.naam AS contract_type_naam
    FROM contracten c
    LEFT JOIN klanten k ON c.klant_id = k.klant_id
    LEFT JOIN werkadressen w ON c.werkadres_id = w.werkadres_id
    LEFT JOIN contract_types t ON c.contract_type = t.type_id
    WHERE c.contract_id = ?
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

/* ======================================================
   ONDERHOUDSONDERDELEN OPHALEN
====================================================== */

$onderdelen = [];
$res = $conn->prepare("
    SELECT onderdeel 
    FROM contract_onderdelen
    WHERE contract_id = ?
    ORDER BY onderdeel ASC
");
$res->bind_param("i", $contract_id);
$res->execute();
$r = $res->get_result();
while ($o = $r->fetch_assoc()) {
    $onderdelen[] = $o['onderdeel'];
}
$res->close();

/* Helpers */
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return ($d && $d !== '0000-00-00') ? date('d-m-Y', strtotime($d)) : '-'; }

$pageTitle = "Contract details";
ob_start();
?>

<!-- ======================================================
     HEADER
====================================================== -->
<div class="page-header">
    <h2>📄 Contract <?= e($contract['contractnummer']) ?></h2>

    <div class="header-actions">
        <a href="contract_bewerk.php?id=<?= $contract_id ?>" class="btn">✏️ Bewerken</a>
        <a href="klant_detail.php?id=<?= $contract['klant_id'] ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>


<!-- ======================================================
     2-KOLOMMEN: CONTRACT + KLANT
====================================================== -->
<div class="two-columns" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">


    <!-- 🔹 CONTRACTINFO -->
    <div class="card">
        <h3>Contractinformatie</h3>

        <?php
        $statusColor = ($contract['status'] === "Actief") ? "#2e7d32" : "#c62828";
        ?>

        <table class="detail-table">
            <tr><th>Contractnummer</th><td><?= e($contract['contractnummer']) ?></td></tr>
            <tr><th>Status</th>
                <td><span style="color:<?= $statusColor ?>;font-weight:600;"><?= e($contract['status']) ?></span></td>
            </tr>

            <tr><th>Ingangsdatum</th><td><?= fmtDate($contract['ingangsdatum']) ?></td></tr>
            <tr><th>Einddatum</th><td><?= fmtDate($contract['einddatum']) ?></td></tr>

            <tr><th>Dagen vóór onderhoud</th><td><?= e($contract['automatisch_vooruit_dagen']) ?> dagen</td></tr>

            <?php if (!empty($contract['opmerkingen'])): ?>
            <tr><th>Opmerkingen</th><td><?= nl2br(e($contract['opmerkingen'])) ?></td></tr>
            <?php endif; ?>

            <?php if ($contract['status'] === "Inactief"): ?>
                <tr><th>Opzegdatum</th><td><?= fmtDate($contract['datum_opzegging']) ?></td></tr>
                <tr><th>Reden opzegging</th><td><?= e($contract['reden_opzegging']) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>


    <!-- 🔹 KLANTINFO -->
    <div class="card">
        <h3>Klant & Werkadres</h3>

        <table class="detail-table">
            <tr><th>Klant</th>
                <td>
                    <?= e($contract['debiteurnummer']) ?> -
                    <?= e($contract['klant_naam']) ?>
                </td>
            </tr>

            <?php if ($contract['werkadres_id']): ?>
            <tr><th>Werkadres</th>
                <td>
                    <?= e($contract['werkadres_naam']) ?><br>
                    <?= e($contract['werkadres_adres']) ?><br>
                    <?= e($contract['werkadres_postcode']) ?> <?= e($contract['werkadres_plaats']) ?>
                </td>
            </tr>
            <?php else: ?>
            <tr><th>Werkadres</th><td>— Geen werkadres gekoppeld —</td></tr>
            <?php endif; ?>
        </table>
    </div>

</div> <!-- einde 2 kolommen -->


<!-- ======================================================
     ONDERHOUDSONDERDELEN (losse tegel)
====================================================== -->
<div class="card" style="margin-top:20px;">
    <h3>Onderhoudsonderdelen</h3>

    <?php if (empty($onderdelen)): ?>
        <p style="color:#777;">Geen onderdelen geselecteerd.</p>

    <?php else: ?>
        <ul style="padding-left:20px; line-height:1.7;">
            <?php foreach ($onderdelen as $o): ?>
                <li><?= e($o) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>


<style>
.detail-table th { width:180px; color:#333; text-align:left; }
.detail-table td { color:#555; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
