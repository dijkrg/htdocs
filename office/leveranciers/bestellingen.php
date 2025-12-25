<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot bestellingen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📦 Bestellingen";

// ─────────────────────────────
// Filters
// ─────────────────────────────
$leverancier_id = isset($_GET['leverancier_id']) ? (int)$_GET['leverancier_id'] : 0;
$statusFilter   = $_GET['status'] ?? '';
$zoekterm       = trim($_GET['q'] ?? '');
$datumVan       = $_GET['datum_van'] ?? '';
$datumTot       = $_GET['datum_tot'] ?? '';

$where = [];
$params = [];
$types  = "";

// Filter leverancier
if ($leverancier_id > 0) {
    $where[] = "b.leverancier_id = ?";
    $params[] = $leverancier_id;
    $types .= "i";
}

// Filter status
if ($statusFilter !== '') {
    $where[] = "b.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

// Filter zoekterm
if ($zoekterm !== '') {
    $like = "%$zoekterm%";
    $where[] = "(b.bestelnummer LIKE ? OR l.naam LIKE ? OR b.opmerking LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

// Filter datums
if ($datumVan !== '') {
    $where[] = "DATE(b.besteldatum) >= ?";
    $params[] = $datumVan;
    $types .= "s";
}
if ($datumTot !== '') {
    $where[] = "DATE(b.besteldatum) <= ?";
    $params[] = $datumTot;
    $types .= "s";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// ─────────────────────────────
// Leveranciers ophalen
// ─────────────────────────────
$leveranciers = $conn->query("
    SELECT leverancier_id, naam 
    FROM leveranciers 
    ORDER BY naam ASC
");

// ─────────────────────────────
// Bestellingen ophalen
// ─────────────────────────────
$sql = "
    SELECT 
        b.bestelling_id, b.bestelnummer, b.besteldatum, b.status, b.opmerking,
        l.naam AS leverancier_naam,
        COUNT(ba.id) AS regels
    FROM bestellingen b
    LEFT JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    LEFT JOIN bestelling_artikelen ba ON ba.bestelling_id = b.bestelling_id
    $whereSql
    GROUP BY b.bestelling_id
    ORDER BY b.besteldatum DESC
    LIMIT 200
";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

function sel($a, $b) { return $a == $b ? 'selected' : ''; }

ob_start();
?>

<!-- 🔹 HEADER -->
<div class="page-header" style="align-items:flex-start;">
    <h2>📦 Bestellingen</h2>
    <div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="../magazijn/index.php" class="btn btn-secondary">⬅ Terug naar Magazijnbeheer</a>
        <a href="bestelling_toevoegen.php" class="btn btn-primary">➕ Nieuwe bestelling</a>
    </div>
</div>

<!-- 🔎 FILTER -->
<div class="card" style="margin-top:10px;">
    <form method="get" class="form-inline"
          style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; align-items:end;">

        <div>
            <label for="leverancier_id">Leverancier</label>
            <select name="leverancier_id" id="leverancier_id">
                <option value="0">— alle —</option>
                <?php while ($l = $leveranciers->fetch_assoc()): ?>
                    <option value="<?= $l['leverancier_id'] ?>" <?= sel($leverancier_id, $l['leverancier_id']) ?>>
                        <?= htmlspecialchars($l['naam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">— alle —</option>
                <option value="open" <?= sel($statusFilter, 'open') ?>>Open</option>
                <option value="gedeeltelijk" <?= sel($statusFilter, 'gedeeltelijk') ?>>Gedeeltelijk</option>
                <option value="afgehandeld" <?= sel($statusFilter, 'afgehandeld') ?>>Afgehandeld</option>
                <option value="geannuleerd" <?= sel($statusFilter, 'geannuleerd') ?>>Geannuleerd</option>
            </select>
        </div>

        <div>
            <label for="datum_van">Datum van</label>
            <input type="date" name="datum_van" id="datum_van" value="<?= htmlspecialchars($datumVan) ?>">
        </div>

        <div>
            <label for="datum_tot">Datum tot</label>
            <input type="date" name="datum_tot" id="datum_tot" value="<?= htmlspecialchars($datumTot) ?>">
        </div>

        <div style="grid-column: span 2;">
            <label for="q">Zoeken</label>
            <input type="text" name="q" id="q" placeholder="bestelnummer, leverancier of opmerking" value="<?= htmlspecialchars($zoekterm) ?>">
        </div>

        <div style="align-self:end;">
            <button type="submit" class="btn">🔎 Filter</button>
            <a href="bestellingen.php" class="btn btn-secondary">✖ Reset</a>
        </div>
    </form>
</div>

<!-- 📋 OVERZICHT -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Bestelnummer</th>
                <th>Leverancier</th>
                <th>Datum</th>
                <th>Status</th>
                <th style="width:60px;">Regels</th>
                <th>Opmerking</th>
                <th style="width:160px;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($r = $result->fetch_assoc()): ?>
                    <?php
                        $kleur = match ($r['status']) {
                            'open' => '#f0ad4e',
                            'gedeeltelijk' => '#5bc0de',
                            'afgehandeld' => '#5cb85c',
                            'geannuleerd' => '#d9534f',
                            default => '#777'
                        };
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['bestelnummer']) ?></td>
                        <td><?= htmlspecialchars($r['leverancier_naam']) ?></td>
                        <td><?= date('d-m-Y', strtotime($r['besteldatum'])) ?></td>
                        <td><span style="color:<?= $kleur ?>; font-weight:bold;"><?= ucfirst($r['status']) ?></span></td>
                        <td style="text-align:center;"><?= (int)$r['regels'] ?></td>
                        <td><?= htmlspecialchars($r['opmerking'] ?? '-') ?></td>
                        <td class="actions" style="white-space:nowrap; text-align:center;">
                            <a href="bestelling_detail.php?id=<?= $r['bestelling_id'] ?>" class="btn-icon" title="Bekijk details">📄</a>
                            <a href="bestelling_bewerk.php?id=<?= $r['bestelling_id'] ?>" class="btn-icon" title="Bewerken">✏️</a>
                            <a href="bestelling_pdf.php?id=<?= $r['bestelling_id'] ?>" class="btn-icon" target="_blank" title="Download PDF">🧾</a>
                            <a href="../magazijn/ontvangsten.php?bestelling_id=<?= $r['bestelling_id'] ?>" class="btn-icon" title="Ontvangst boeken">📥</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Geen bestellingen gevonden.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
