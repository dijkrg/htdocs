<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$object_id = (int)($_GET['id'] ?? 0);
if ($object_id <= 0) {
    setFlash("Ongeldig object ID.", "error");
    header("Location: objecten.php");
    exit;
}

// ✅ Object ophalen + klant + werkadres
$stmt = $conn->prepare("
    SELECT o.*, 
           k.debiteurnummer, k.bedrijfsnaam AS klant_naam,
           w.bedrijfsnaam AS werkadres_naam
    FROM objecten o
    LEFT JOIN klanten k ON o.klant_id = k.klant_id
    LEFT JOIN werkadressen w ON o.werkadres_id = w.werkadres_id
    WHERE o.object_id = ?
");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$object = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$object) {
    setFlash("Object niet gevonden.", "error");
    header("Location: objecten.php");
    exit;
}

// ✅ Resultaatkleur bepalen
$kleurRes = '#777';
if (!empty($object['resultaat'])) {
    $res = $conn->prepare("SELECT kleur FROM object_status WHERE naam = ?");
    $res->bind_param("s", $object['resultaat']);
    $res->execute();
    $res->bind_result($kleur);
    if ($res->fetch()) {
        $kleurRes = match($kleur) {
            'groen'  => '#2e7d32',
            'oranje' => '#e67e22',
            'rood'   => '#c62828',
            default  => '#777'
        };
    }
    $res->close();
}

$pageTitle = "Object detail";
ob_start();
?>

<div class="page-header">
    <h2>🔎 Object detail — <?= htmlspecialchars($object['code'] ?? '') ?></h2>
    <div class="header-actions">
        <a href="object_bewerk.php?id=<?= $object_id ?>" class="btn">✏️ Bewerken</a>
        <a href="objecten.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<?php showFlash(); ?>

<div class="two-column-form">
    <!-- 🔹 LINKERKOLOM -->
    <div class="card left-col">
        <h3>Technische gegevens</h3>
        <table class="detail-table">
            <tr><th>Code</th><td><?= htmlspecialchars($object['code']) ?></td></tr>
            <tr><th>Omschrijving</th><td><?= htmlspecialchars($object['omschrijving']) ?></td></tr>
            <tr><th>Merk</th><td><?= htmlspecialchars($object['merk'] ?? '-') ?></td></tr>
            <tr><th>Type</th><td><?= htmlspecialchars($object['type'] ?? '-') ?></td></tr>

            <tr><th>Datum installatie</th>
                <td><?= !empty($object['datum_installatie']) ? date('d-m-Y', strtotime($object['datum_installatie'])) : '-' ?></td></tr>

            <tr><th>Laatste onderhoud</th>
                <td><?= !empty($object['datum_onderhoud']) ? date('d-m-Y', strtotime($object['datum_onderhoud'])) : '-' ?></td></tr>

            <tr><th>Fabricagejaar</th><td><?= htmlspecialchars($object['fabricagejaar'] ?? '-') ?></td></tr>
            <tr><th>Beproeving NEN671-3</th><td><?= htmlspecialchars($object['beproeving_nen671_3'] ?? '-') ?></td></tr>

            <tr>
                <th>Rijkstypekeur</th>
                <td><?= htmlspecialchars($object['rijkstypekeur'] ?? '-') ?></td>
            </tr>

            <tr>
                <th>Uitgebreid onderhoud</th>
                <td><?= $object['uitgebreid_onderhoud'] ? '<span class="badge badge-green">Ja</span>' : '<span class="badge badge-gray">Nee</span>' ?></td>
            </tr>

            <tr>
                <th>Gereviseerd</th>
                <td><?= $object['gereviseerd'] ? '<span class="badge badge-green">Ja</span>' : '<span class="badge badge-gray">Nee</span>' ?></td>
            </tr>

            <tr><th>Verdieping</th><td><?= htmlspecialchars($object['verdieping'] ?? '-') ?></td></tr>
            <tr><th>Locatie</th><td><?= htmlspecialchars($object['locatie'] ?? '-') ?></td></tr>

            <tr>
                <th>Resultaat</th>
                <td>
                    <?php if (!empty($object['resultaat'])): ?>
                        <span class="status-badge" style="background:<?= $kleurRes ?>;">
                            <?= htmlspecialchars($object['resultaat']) ?>
                        </span>
                    <?php else: ?>
                        <em>Geen resultaat</em>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if (!empty($object['opmerkingen'])): ?>
                <tr><th>Opmerkingen</th><td><?= nl2br(htmlspecialchars($object['opmerkingen'])) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 🔹 RECHTERKOLOM -->
    <div class="card right-col">
        <h3>Klant & locatie</h3>
        <table class="detail-table">
            <tr><th>Klant</th><td><?= htmlspecialchars(($object['debiteurnummer'] ?? '') . ' - ' . ($object['klant_naam'] ?? '-')) ?></td></tr>
            <tr><th>Werkadres</th><td><?= htmlspecialchars($object['werkadres_naam'] ?? '-') ?></td></tr>
        </table>

        <h3 style="margin-top:20px;">Afbeelding</h3>
        <div class="image-wrap small-preview">
            <?php if (!empty($object['afbeelding']) && file_exists(__DIR__ . '/' . $object['afbeelding'])): ?>
                <img src="/<?= htmlspecialchars($object['afbeelding']) ?>" alt="Object afbeelding">
            <?php else: ?>
                <div class="img-placeholder">Geen afbeelding</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.two-column-form { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.detail-table th {
    text-align:left;
    width:200px;
    font-weight:600;
    padding:6px 10px;
    vertical-align:top;
}
.detail-table td { padding:6px 10px; }
.badge-green {
    background:#2e7d32;
    color:#fff;
    padding:3px 8px;
    border-radius:5px;
    font-size:13px;
}
.badge-gray {
    background:#777;
    color:#fff;
    padding:3px 8px;
    border-radius:5px;
    font-size:13px;
}
.status-badge {
    display:inline-block;
    padding:4px 10px;
    border-radius:6px;
    font-size:13px;
    color:#fff;
}
.image-wrap.small-preview {
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:130px;
    border:1px dashed #ccc;
    border-radius:8px;
    background:#fafafa;
}
.image-wrap img { max-width:230px; border-radius:6px; }
.img-placeholder { color:#777; font-size:14px; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
