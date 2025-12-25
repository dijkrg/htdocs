<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot voorraadbeheer.", "error");
    header("Location: /index.php");
    exit;
}

$pageTitle = "📦 Voorraadbeheer";

// ─────────────────────────────
// Filters
// ─────────────────────────────
$q           = trim($_GET['q'] ?? '');
$toonAlles   = isset($_GET['toon']) && $_GET['toon'] === 'alles';
$magazijn_id = isset($_GET['magazijn_id']) ? (int)$_GET['magazijn_id'] : 0;

$where = [];
$params = [];
$types  = "";

// Zoekterm
if ($q !== '') {
    $where[] = "(a.artikelnummer LIKE ? OR a.omschrijving LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like;
    $types .= "ss";
}

// ─────────────────────────────
// Magazijnen ophalen
// ─────────────────────────────
$magazijnen = $conn->query("SELECT magazijn_id, naam, type FROM magazijnen ORDER BY type, naam");

// ─────────────────────────────
// SQL
// ─────────────────────────────
$sql = "
    SELECT 
        a.artikel_id,
        a.artikelnummer,
        a.omschrijving,
        a.inkoopprijs,
        a.verkoopprijs,
        a.btw_tarief,
        COALESCE(SUM(vm.aantal), 0) AS voorraad,
        MAX(vm.laatste_update) AS laatste_update
    FROM artikelen a
    LEFT JOIN voorraad_magazijn vm ON vm.artikel_id = a.artikel_id
";

if ($magazijn_id > 0) {
    $sql .= " AND vm.magazijn_id = ?";
    $params[] = $magazijn_id;
    $types   .= "i";
}

$sql .= " WHERE 1 ";

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " GROUP BY a.artikel_id ";

if (!$toonAlles) {
    $sql .= " HAVING voorraad > 0 ";
}

$sql .= " ORDER BY a.artikelnummer ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$aantalResultaten = $result->num_rows;

ob_start();
?>

<!-- 🔹 HEADER -->
<div class="page-header" style="align-items:flex-start;">
    <h2>📦 Voorraadbeheer</h2>
    <div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="index.php" class="btn btn-secondary">⬅ Terug naar Magazijnbeheer</a>
        <?php if ($toonAlles): ?>
            <a href="voorraad.php?<?= http_build_query(array_merge($_GET, ['toon' => 'metvoorraad'])) ?>" class="btn btn-primary">
                📦 Alleen artikelen met voorraad
            </a>
        <?php else: ?>
            <a href="voorraad.php?<?= http_build_query(array_merge($_GET, ['toon' => 'alles'])) ?>" class="btn btn-secondary">
                📋 Toon alle artikelen
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 🔎 FILTER -->
<div class="card" style="margin-top:10px;">
    <form method="get" class="form-inline" 
          style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; align-items:end;">

        <div>
            <label for="q">Artikelnummer of omschrijving</label>
            <input type="text" name="q" id="q" placeholder="Zoekterm..." value="<?= htmlspecialchars($q) ?>">
        </div>

        <div>
            <label for="magazijn_id">Magazijn</label>
            <select name="magazijn_id" id="magazijn_id">
                <option value="0">— Alle magazijnen —</option>
                <?php mysqli_data_seek($magazijnen, 0);
                while ($m = $magazijnen->fetch_assoc()): ?>
                    <option value="<?= $m['magazijn_id'] ?>" <?= ($magazijn_id == $m['magazijn_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
            <button type="submit" class="btn">🔎 Zoeken</button>
            <a href="voorraad.php" class="btn btn-secondary">✖ Reset</a>
        </div>
    </form>
</div>

<!-- 📋 RESULTATEN -->
<div class="card">
    <div style="font-size:14px; margin-bottom:8px; color:#333;">
        <strong><?= $aantalResultaten ?></strong> artikel<?= $aantalResultaten == 1 ? '' : 'en' ?> weergegeven
        <?php if ($magazijn_id): 
            $naam = $conn->query("SELECT naam FROM magazijnen WHERE magazijn_id = $magazijn_id")->fetch_row()[0];
            echo "(filter: " . htmlspecialchars($naam) . ")";
        endif; ?>
    </div>

    <table class="data-table compact-table">
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th style="text-align:right;">Voorraad</th>
                <th style="text-align:right;">Inkoop (€)</th>
                <th style="text-align:right;">Verkoop (€)</th>
                <th>Laatste update</th>
                <th style="width:120px;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($aantalResultaten > 0): ?>
                <?php while ($r = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                        <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                        <td style="text-align:right; font-weight:bold; color:<?= ($r['voorraad'] <= 5 ? '#d9534f' : '#333') ?>;">
                            <?= (int)$r['voorraad'] ?>
                        </td>
                        <td style="text-align:right;">€ <?= number_format($r['inkoopprijs'], 2, ',', '.') ?></td>
                        <td style="text-align:right;">€ <?= number_format($r['verkoopprijs'], 2, ',', '.') ?></td>
                        <td><?= $r['laatste_update'] ? date('d-m-Y H:i', strtotime($r['laatste_update'])) : '-' ?></td>
                        <td class="actions">
                            <a href="../artikel_detail.php?id=<?= $r['artikel_id'] ?>" title="Details">📄</a>
                            <a href="voorraad_bewerk.php?id=<?= $r['artikel_id'] ?>" title="Bewerken">✏️</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Geen artikelen gevonden<?= $toonAlles ? '.' : ' met voorraad.' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
