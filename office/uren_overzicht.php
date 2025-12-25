<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

if ($_SESSION['user']['rol'] !== 'Manager') {
    setFlash("⛔ Geen rechten voor urenbeheer.", "error");
    header("Location: index.php");
    exit;
}

$pageTitle = "Urenregistratie – Overzicht";

/* ---------------------------------------------------------
   Filters ophalen
---------------------------------------------------------- */
$filter_monteur = intval($_GET['monteur'] ?? 0);
$filter_status  = $_GET['status'] ?? "";
$filter_uursoort = intval($_GET['uursoort'] ?? 0);
$filter_start = $_GET['start'] ?? "";
$filter_eind  = $_GET['eind'] ?? "";

$where = [];
$params = [];
$types = "";

/* Monteur filter */
if ($filter_monteur > 0) {
    $where[] = "u.user_id = ?";
    $params[] = $filter_monteur;
    $types .= "i";
}

/* Status filter */
if ($filter_status !== "" && in_array($filter_status, ["0", "1", "-1"])) {
    $where[] = "u.goedgekeurd = ?";
    $params[] = intval($filter_status);
    $types .= "i";
}

/* Uursoort filter */
if ($filter_uursoort > 0) {
    $where[] = "u.uursoort_id = ?";
    $params[] = $filter_uursoort;
    $types .= "i";
}

/* Datum van/tot */
if (!empty($filter_start)) {
    $where[] = "u.datum >= ?";
    $params[] = $filter_start;
    $types .= "s";
}
if (!empty($filter_eind)) {
    $where[] = "u.datum <= ?";
    $params[] = $filter_eind;
    $types .= "s";
}

$where_sql = "";
if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

/* ---------------------------------------------------------
   Query uitvoeren
---------------------------------------------------------- */
$sql = "
    SELECT u.*, m.voornaam, m.achternaam,
           s.code AS s_code, s.omschrijving AS s_oms,
           wb.werkbonnummer
    FROM urenregistratie u
    LEFT JOIN medewerkers m ON m.medewerker_id = u.user_id
    LEFT JOIN uursoorten_uren s ON s.uursoort_id = u.uursoort_id
    LEFT JOIN werkbonnen wb ON wb.werkbon_id = u.werkbon_id
    $where_sql
    ORDER BY u.datum DESC, u.starttijd DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* Monteurs laden */
$monteurs = $conn->query("
    SELECT medewerker_id, voornaam, achternaam 
    FROM medewerkers 
    WHERE rol = 'Monteur' 
    ORDER BY achternaam
");

/* Uursoorten laden */
$uursoorten = $conn->query("
    SELECT uursoort_id, code, omschrijving 
    FROM uursoorten_uren
    ORDER BY code
");

ob_start();
?>

<div class="page-header">
    <h2>Urenregistratie – Overzicht</h2>
</div>

<div class="card">
    <form method="get" class="form-grid">

        <div>
            <label>Monteur</label>
            <select name="monteur">
                <option value="">-- Alle --</option>
                <?php while ($m = $monteurs->fetch_assoc()): ?>
                    <option value="<?= $m['medewerker_id'] ?>" 
                        <?= ($filter_monteur == $m['medewerker_id']) ? "selected" : "" ?>>
                        <?= htmlspecialchars($m['achternaam'] . ", " . $m['voornaam']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="">-- Alle --</option>
                <option value="0"  <?= ($filter_status==="0" ? "selected" : "") ?>>🟡 In behandeling</option>
                <option value="1"  <?= ($filter_status==="1" ? "selected" : "") ?>>🟢 Goedgekeurd</option>
                <option value="-1" <?= ($filter_status==="−1" ? "selected" : "") ?>>🔴 Afgekeurd</option>
            </select>
        </div>

        <div>
            <label>Uursoort</label>
            <select name="uursoort">
                <option value="">-- Alle --</option>
                <?php while ($u = $uursoorten->fetch_assoc()): ?>
                    <option value="<?= $u['uursoort_id'] ?>"
                        <?= ($filter_uursoort == $u['uursoort_id']) ? "selected" : "" ?>>
                        <?= $u['code'] ?> – <?= htmlspecialchars($u['omschrijving']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label>Vanaf</label>
            <input type="date" name="start" value="<?= htmlspecialchars($filter_start) ?>">
        </div>

        <div>
            <label>Tot</label>
            <input type="date" name="eind" value="<?= htmlspecialchars($filter_eind) ?>">
        </div>

        <div class="full">
            <button class="btn-primary">Filter toepassen</button>
        </div>

    </form>
</div>

<div class="card" style="margin-top:15px;">
    <?php if ($result->num_rows == 0): ?>
        <p><em>Geen uren gevonden.</em></p>
    <?php else: ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Tijd</th>
                <th>Monteur</th>
                <th>Uursoort</th>
                <th>Werkbon</th>
                <th>Duur</th>
                <th>Status</th>
                <th style="text-align:center;">Acties</th>
            </tr>
        </thead>
        <tbody>

        <?php while ($r = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($r['datum']) ?></td>
                <td><?= substr($r['starttijd'],0,5) ?>–<?= substr($r['eindtijd'],0,5) ?></td>
                <td><?= htmlspecialchars($r['voornaam'] . " " . $r['achternaam']) ?></td>
                <td><?= htmlspecialchars($r['s_code']) ?> – <?= htmlspecialchars($r['s_oms']) ?></td>
                <td>
                    <?php if ($r['werkbon_id']): ?>
                        #<?= $r['werkbonnummer'] ?>
                    <?php else: ?>
                        <em>Geen</em>
                    <?php endif; ?>
                </td>
                <td><?= number_format($r['duur_minuten'] / 60, 2) ?> u</td>

                <td>
                    <?php if ($r['goedgekeurd'] == 1): ?>
                        <span class="badge badge-success">Goedgekeurd</span>
                    <?php elseif ($r['goedgekeurd'] == -1): ?>
                        <span class="badge badge-danger">Afgekeurd</span>
                    <?php else: ?>
                        <span class="badge badge-warning">In behandeling</span>
                    <?php endif; ?>
                </td>

                <td class="actions">

                    <a href="uren_goedkeuren.php?id=<?= $r['uur_id'] ?>"
                       class="btn-small btn-success"
                       title="Goedkeuren">✔</a>

                    <a href="uren_afkeuren.php?id=<?= $r['uur_id'] ?>"
                       class="btn-small btn-danger"
                       title="Afkeuren">✘</a>

                </td>

            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>

    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
