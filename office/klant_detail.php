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

$klant_id = intval($_GET['id'] ?? 0);
if ($klant_id <= 0) {
    setFlash("Ongeldige klant-ID.", "error");
    header("Location: klanten.php");
    exit;
}

// 🧩 Klant ophalen
$stmt = $conn->prepare("SELECT * FROM klanten WHERE klant_id = ?");
$stmt->bind_param("i", $klant_id);
$stmt->execute();
$klant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$klant) {
    setFlash("Klant niet gevonden.", "error");
    header("Location: klanten.php");
    exit;
}

// 🧩 Nieuwste contract ophalen (incl. contracttype naam)
$stmt = $conn->prepare("
    SELECT 
        c.*,
        w.bedrijfsnaam AS werkadres_naam,
        w.plaats AS werkadres_plaats,
        ct.naam AS contract_type_naam
    FROM contracten c
    LEFT JOIN werkadressen w ON c.werkadres_id = w.werkadres_id
    LEFT JOIN contract_types ct ON ct.type_id = c.contract_type
    WHERE c.klant_id = ?
    ORDER BY c.ingangsdatum DESC
    LIMIT 1
");
$stmt->bind_param("i", $klant_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 🧩 Werkadressen ophalen
$stmt = $conn->prepare("
    SELECT *
    FROM werkadressen
    WHERE klant_id = ?
    ORDER BY adres ASC
");
$stmt->bind_param("i", $klant_id);
$stmt->execute();
$werkadressen = $stmt->get_result();
$stmt->close();

// 🧩 Objecten ophalen
$obj_stmt = $conn->prepare("
    SELECT o.*, os.kleur AS status_kleur
    FROM objecten o
    LEFT JOIN object_status os ON os.naam = o.resultaat
    WHERE o.klant_id = ? AND o.verwijderd = 0
    ORDER BY o.code+0 ASC
");
$obj_stmt->bind_param("i", $klant_id);
$obj_stmt->execute();
$objecten = $obj_stmt->get_result();
$obj_stmt->close();

// 🧩 Werkbonnen ophalen
$wb_stmt = $conn->prepare("
    SELECT w.*, t.naam AS type_naam
    FROM werkbonnen w
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.klant_id = ?
    ORDER BY w.uitvoerdatum DESC
");
$wb_stmt->bind_param("i", $klant_id);
$wb_stmt->execute();
$werkbonnen = $wb_stmt->get_result();
$wb_stmt->close();

// Helpers
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ return ($d && $d!=='0000-00-00') ? date('d-m-Y', strtotime($d)) : ''; }

$pageTitle = "Klant details";
ob_start();
?>

<!-- ======================================================
     PAGINA HEADER
======================================================= -->
<div class="page-header">
    <h2>🏷️ Klantdetails</h2>
    <div class="header-actions">
        <a href="klant_bewerk.php?id=<?= $klant_id ?>" class="btn">✏️ Bewerken</a>
        <a href="klanten.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>


<!-- ======================================================
     TOPBLOCK: KLANTGEGEVENS + CONTRACT (2 kolommen)
======================================================= -->
<div class="top-two-columns">

    <!-- 🔹 KLANTGEGEVENS -->
    <div class="card">
        <h3>Klantgegevens</h3>

        <table class="detail-table">
            <tr><th>Debiteurnummer</th><td><?= e($klant['debiteurnummer']) ?></td></tr>
            <tr><th>Bedrijfsnaam</th><td><?= e($klant['bedrijfsnaam']) ?></td></tr>
            <tr><th>Contactpersoon</th><td><?= e($klant['contactpersoon']) ?></td></tr>
            <tr><th>Telefoon</th><td><?= e($klant['telefoonnummer'] ?? $klant['telefoon']) ?></td></tr>
            <tr><th>E-mail</th><td><a href="mailto:<?= e($klant['email']) ?>"><?= e($klant['email']) ?></a></td></tr>
            <tr><th>Adres</th><td><?= e($klant['adres']) ?></td></tr>
            <tr><th>Postcode</th><td><?= e($klant['postcode']) ?></td></tr>
            <tr><th>Plaats</th><td><?= e($klant['plaats']) ?></td></tr>

            <?php if (!empty($klant['opmerkingen'])): ?>
            <tr><th>Opmerkingen</th><td><?= nl2br(e($klant['opmerkingen'])) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>


    <!-- 🔹 CONTRACT -->
    <div class="card">
        <h3>Contract</h3>

        <?php if (!$contract): ?>
            <p style="color:#777;">Geen contract aangemaakt voor deze klant.</p>
        <?php else: ?>

            <?php
                $statusColor = $contract['status']==='Actief' ? '#2e7d32' : '#c62828';
                $statusText  = $contract['status']==='Actief' ? '🟢 Actief' : '🔴 Inactief';
            ?>

            <table class="detail-table">
                <tr><th>Contractnummer</th><td><?= e($contract['contractnummer']) ?></td></tr>

                <tr>
                    <th>Status</th>
                    <td><span style="color:<?= $statusColor ?>;font-weight:600;"><?= $statusText ?></span></td>
                </tr>

                <tr>
                    <th>Contracttype</th>
                    <td><?= e($contract['contract_type_naam'] ?: 'Onbekend type') ?></td>
                </tr>

                <tr><th>Ingangsdatum</th><td><?= fmtDate($contract['ingangsdatum']) ?></td></tr>
                <tr><th>Einddatum</th><td><?= fmtDate($contract['einddatum']) ?></td></tr>

                <?php if (!empty($contract['werkadres_naam'])): ?>
                    <tr><th>Werkadres</th><td><?= e($contract['werkadres_naam']) ?> (<?= e($contract['werkadres_plaats']) ?>)</td></tr>
                <?php endif; ?>

                <?php if (!empty($contract['opmerkingen'])): ?>
                    <tr><th>Opmerkingen</th><td><?= nl2br(e($contract['opmerkingen'])) ?></td></tr>
                <?php endif; ?>
            </table>

            <div style="margin-top:10px;display:flex;gap:8px;">
                <a href="contract_detail.php?id=<?= $contract['contract_id'] ?>" class="btn">📄 Bekijk</a>
                <a href="contract_bewerk.php?id=<?= $contract['contract_id'] ?>" class="btn">✏️ Bewerk</a>
            </div>

        <?php endif; ?>
    </div>

</div> <!-- einde top-two-columns -->



<!-- ======================================================
     WERKADRESSEN
======================================================= -->
<div class="card" style="margin-top:20px;">
    <h3>Werkadressen</h3>

    <?php if ($werkadressen->num_rows === 0): ?>
        <p style="color:#777;">Geen werkadressen toegevoegd.</p>
    <?php else: ?>
        <table class="data-table small-table">
            <thead>
                <tr>
                    <th>Bedrijfsnaam</th>
                    <th>Adres</th>
                    <th>Postcode / Plaats</th>
                    <th style="width:150px;">Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($wa = $werkadressen->fetch_assoc()): ?>
                <tr>
                    <td><?= e($wa['bedrijfsnaam']) ?></td>
                    <td><?= e($wa['adres']) ?></td>
                    <td><?= e($wa['postcode']) ?> <?= e($wa['plaats']) ?></td>
                    <td class="actions">
                        <a href="werkadres_bewerk.php?id=<?= $wa['werkadres_id'] ?>">✏️</a>
                        <a href="werkadres_verwijder.php?id=<?= $wa['werkadres_id'] ?>&klant_id=<?= $klant_id ?>" onclick="return confirm('Verwijderen?')">🗑</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top:10px;">
        <a href="werkadres_toevoegen.php?klant_id=<?= $klant_id ?>" class="btn btn-accent btn-small">➕ Werkadres toevoegen</a>
    </div>
</div>



<!-- ======================================================
     OBJECTEN
======================================================= -->
<div class="card">
    <h3>Objecten</h3>

    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Omschrijving</th>
                <th>Laatste onderhoud</th>
                <th>Status</th>
                <th style="width:140px;">Acties</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($objecten->num_rows == 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#777;">Geen objecten</td></tr>
        <?php endif; ?>

        <?php while ($o = $objecten->fetch_assoc()):
            $datum = (!empty($o['datum_onderhoud']) && $o['datum_onderhoud']!='0000-00-00')
                ? date('d-m-Y', strtotime($o['datum_onderhoud']))
                : '-';

            $kleur = match($o['status_kleur']) {
                'groen' => '#2e7d32',
                'oranje'=> '#f57c00',
                'rood'  => '#d32f2f',
                default => '#999'
            };
        ?>
            <tr>
                <td><?= e($o['code']) ?></td>
                <td><?= e($o['omschrijving']) ?></td>
                <td><?= $datum ?></td>
                <td><span style="background:<?= $kleur ?>;color:white;padding:3px 8px;border-radius:5px;"><?= e($o['resultaat']) ?></span></td>
                <td class="actions">
                    <a href="object_detail.php?id=<?= $o['object_id'] ?>">📄</a>
                    <a href="object_bewerk.php?id=<?= $o['object_id'] ?>">✏️</a>
                    <a href="object_verwijder.php?id=<?= $o['object_id'] ?>&klant_id=<?= $klant_id ?>" onclick="return confirm('Verwijderen?')">🗑</a>
                </td>
            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>
</div>



<!-- ======================================================
     WERKBONNEN
======================================================= -->
<div class="card">
    <h3>Aangemaakte werkbonnen</h3>

    <?php if ($werkbonnen->num_rows == 0): ?>
        <p style="color:#777;">Geen werkbonnen voor deze klant.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Werkbonnummer</th>
                    <th>Uitvoerdatum</th>
                    <th>Type werkzaamheden</th>
                    <th>Status</th>
                    <th>Acties</th>
                </tr>
            </thead>

            <tbody>
            <?php while ($wb = $werkbonnen->fetch_assoc()): ?>
                <tr>
                    <td><?= e($wb['werkbonnummer']) ?></td>
                    <td><?= fmtDate($wb['uitvoerdatum']) ?></td>
                    <td><?= e($wb['type_naam']) ?></td>
                    <td><?= e($wb['status']) ?></td>
                    <td>
                        <a href="werkbon_detail.php?id=<?= $wb['werkbon_id'] ?>" class="btn btn-small">📄</a>
                        <a href="werkbon_bewerk.php?id=<?= $wb['werkbon_id'] ?>" class="btn btn-small">✏️</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>


<!-- ======================================================
     LAYOUT FIXES
======================================================= -->
<style>
.top-two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 900px) {
    .top-two-columns {
        grid-template-columns: 1fr;
    }
}
.detail-table th {
    width: 160px;
    text-align: left;
    color: #333;
}
.detail-table td { 
    color:#555; 
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
