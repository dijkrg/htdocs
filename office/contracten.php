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

/* ======================================================
   FILTERS / ZOEKEN
====================================================== */

$zoek = trim($_GET['zoek'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = "";

// Zoekterm zoeken in contractnummer, klantnaam, type
if ($zoek !== "") {
    $where[] = "(c.contractnummer LIKE ? 
                OR k.bedrijfsnaam LIKE ? 
                OR t.naam LIKE ?)";
    $zoekTerm = "%$zoek%";
    $params[] = $zoekTerm;
    $params[] = $zoekTerm;
    $params[] = $zoekTerm;
    $types .= "sss";
}

// Status filter
if ($statusFilter === "Actief" || $statusFilter === "Inactief") {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereSQL = $where ? ("WHERE " . implode(" AND ", $where)) : "";

/* ======================================================
   CONTRACTEN OPHALEN
====================================================== */

$sql = "
    SELECT 
        c.contract_id,
        c.contractnummer,
        c.status,
        c.ingangsdatum,
        c.einddatum,
        t.naam AS contract_type_naam,
        k.bedrijfsnaam AS klantnaam,
        k.debiteurnummer
    FROM contracten c
    LEFT JOIN klanten k ON c.klant_id = k.klant_id
    LEFT JOIN contract_types t ON c.contract_type = t.type_id
    $whereSQL
    ORDER BY c.contractnummer DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* ======================================================
   TEMPLATE
====================================================== */

$pageTitle = "Contracten";
ob_start();
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>📄 Contracten</h2>
    <a href="contract_toevoegen.php" class="btn btn-accent">➕ Nieuw contract</a>
</div>

<!-- ======================================================
     ZOEK + FILTERS
====================================================== -->
<div class="card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
        
        <div>
            <label>Zoeken</label>
            <input type="text" name="zoek" value="<?= htmlspecialchars($zoek) ?>" 
                placeholder="Contractnummer / klant / type" 
                style="padding:6px 10px; width:250px;">
        </div>

        <div>
            <label>Status</label>
            <select name="status" style="padding:6px 10px;">
                <option value="">Alle</option>
                <option value="Actief" <?= $statusFilter==="Actief"?"selected":"" ?>>🟢 Actief</option>
                <option value="Inactief" <?= $statusFilter==="Inactief"?"selected":"" ?>>🔴 Inactief</option>
            </select>
        </div>

        <button type="submit" class="btn">🔍 Filter</button>
        <a href="contracten.php" class="btn btn-secondary">Reset</a>

    </form>
</div>

<!-- ======================================================
     CONTRACTEN TABEL
====================================================== -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
		<th>Contractnummer</th>
                <th>Klant</th>
                <th>Contracttype</th>
                <th>Ingang</th>
                <th>Einde</th>
                <th>Status</th>
                <th style="width:140px;">Acties</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($result->num_rows == 0): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#777;">Geen contracten gevonden.</td>
            </tr>
        <?php endif; ?>

        <?php while ($c = $result->fetch_assoc()):
            $statusColor = $c['status'] === "Actief" ? "#2e7d32" : "#c62828";
        ?>
            <tr>
                <td><?= $c['contract_id'] ?></td>

                <td>
                    <a href="contract_detail.php?id=<?= $c['contract_id'] ?>" class="link">
                        <?= htmlspecialchars($c['contractnummer']) ?>
                    </a>
                </td>

                <td>
                    <?= htmlspecialchars($c['debiteurnummer']) ?> -
                    <?= htmlspecialchars($c['klantnaam']) ?>
                </td>

                <td><?= htmlspecialchars($c['contract_type_naam'] ?: "—") ?></td>

                <td><?= $c['ingangsdatum'] !== "0000-00-00" ? date("d-m-Y", strtotime($c['ingangsdatum'])) : "-" ?></td>
                <td><?= $c['einddatum'] !== "0000-00-00" ? date("d-m-Y", strtotime($c['einddatum'])) : "-" ?></td>

                <td>
                    <span style="color:<?= $statusColor ?>; font-weight:600;">
                        <?= htmlspecialchars($c['status']) ?>
                    </span>
                </td>

                <td class="actions">
                    <a href="contract_detail.php?id=<?= $c['contract_id'] ?>">📄</a>
                    <a href="contract_bewerk.php?id=<?= $c['contract_id'] ?>">✏️</a>
                </td>
            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>
</div>

<style>
.data-table td a.link { color:#2954cc; font-weight:600; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
