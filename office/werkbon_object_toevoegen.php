<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

// Helper
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

/* --------------------------------------------------
   1) Werkbon ophalen + ownership + klant + werkadres
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT
        w.werkbon_id,
        w.werkbonnummer,
        w.klant_id,
        w.werkadres_id,
        w.monteur_id,
        k.bedrijfsnaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    WHERE w.werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$werkbon) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

if ((int)$werkbon['monteur_id'] !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

$klant_id     = (int)($werkbon['klant_id'] ?? 0);
$werkadres_id = (int)($werkbon['werkadres_id'] ?? 0);

if ($klant_id <= 0) {
    setFlash("Werkbon heeft geen geldige klant.", "error");
    header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id);
    exit;
}

/* --------------------------------------------------
   2) Detecteer of objecten.werkadres_id bestaat
-------------------------------------------------- */
$hasWerkadresCol = false;
$colCheck = $conn->query("SHOW COLUMNS FROM objecten LIKE 'werkadres_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasWerkadresCol = true;
}

/* --------------------------------------------------
   3) Objecten ophalen: klant of werkadres, excl. al gekoppeld
-------------------------------------------------- */
$zoek = trim((string)($_GET['zoek'] ?? ''));

// Basis WHERE: alleen objecten van klant, OF (werkadres match als kolom bestaat)
$where = "(
    o.klant_id = ?
";
$params = [$klant_id];
$types  = "i";

if ($hasWerkadresCol && $werkadres_id > 0) {
    $where .= " OR o.werkadres_id = ? ";
    $params[] = $werkadres_id;
    $types   .= "i";
}
$where .= ")";

// Zoekfilter
$zoekLike = null;
if ($zoek !== '') {
    $zoekLike = "%" . $zoek . "%";
    $where .= " AND (o.code LIKE ? OR o.omschrijving LIKE ? OR o.locatie LIKE ?)";
    $params[] = $zoekLike;
    $params[] = $zoekLike;
    $params[] = $zoekLike;
    $types   .= "sss";
}

// Exclude: al gekoppeld aan deze werkbon
$sql = "
    SELECT
        o.object_id, o.code, o.omschrijving, o.locatie, o.verdieping
    FROM objecten o
    LEFT JOIN werkbon_objecten wo
        ON wo.object_id = o.object_id AND wo.werkbon_id = ?
    WHERE wo.object_id IS NULL
      AND $where
    ORDER BY o.code ASC
";
$params = array_merge([$werkbon_id], $params);
$types  = "i" . $types;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$objecten = $stmt->get_result(); // mysqli_result
$stmt->close();

/* --------------------------------------------------
   4) Objecten koppelen (bulk) + extra veiligheid: check scope
-------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toevoegen']) && !empty($_POST['object_ids'])) {

    $object_ids = array_values(array_filter(array_map('intval', (array)$_POST['object_ids']), fn($v) => $v > 0));
    if (!$object_ids) {
        setFlash("Geen objecten geselecteerd.", "error");
        header("Location: /monteur/werkbon_object_toevoegen.php?werkbon_id=" . $werkbon_id);
        exit;
    }

    // 4a) Veiligheidsfilter: alleen IDs die binnen klant/werkadres scope vallen
    $placeholders = implode(',', array_fill(0, count($object_ids), '?'));
    $scopeWhere = "o.klant_id = ?";
    $scopeParams = [$klant_id];
    $scopeTypes  = "i";

    if ($hasWerkadresCol && $werkadres_id > 0) {
        $scopeWhere .= " OR o.werkadres_id = ?";
        $scopeParams[] = $werkadres_id;
        $scopeTypes   .= "i";
    }

    $scopeSql = "
        SELECT o.object_id
        FROM objecten o
        WHERE o.object_id IN ($placeholders)
          AND ($scopeWhere)
    ";

    $scopeStmt = $conn->prepare($scopeSql);
    $scopeTypesFull = str_repeat('i', count($object_ids)) . $scopeTypes;
    $scopeValues = array_merge($object_ids, $scopeParams);
    $scopeStmt->bind_param($scopeTypesFull, ...$scopeValues);
    $scopeStmt->execute();
    $scopeRes = $scopeStmt->get_result();
    $allowedIds = [];
    while ($row = $scopeRes->fetch_assoc()) {
        $allowedIds[] = (int)$row['object_id'];
    }
    $scopeStmt->close();

    if (!$allowedIds) {
        setFlash("Geen van de geselecteerde objecten hoort bij deze klant/werkadres.", "error");
        header("Location: /monteur/werkbon_object_toevoegen.php?werkbon_id=" . $werkbon_id);
        exit;
    }

    // 4b) Bestaande koppelingen eruit filteren
    $ph = implode(',', array_fill(0, count($allowedIds), '?'));
    $checkSql = "SELECT object_id FROM werkbon_objecten WHERE werkbon_id = ? AND object_id IN ($ph)";
    $checkStmt = $conn->prepare($checkSql);
    $checkTypes = "i" . str_repeat('i', count($allowedIds));
    $checkValues = array_merge([$werkbon_id], $allowedIds);
    $checkStmt->bind_param($checkTypes, ...$checkValues);
    $checkStmt->execute();
    $existingRes = $checkStmt->get_result();
    $existingIds = [];
    while ($row = $existingRes->fetch_assoc()) {
        $existingIds[] = (int)$row['object_id'];
    }
    $checkStmt->close();

    $new_ids = array_values(array_diff($allowedIds, $existingIds));

    if ($new_ids) {
        $ph2 = implode(',', array_fill(0, count($new_ids), '(?, ?)'));
        $types2 = str_repeat('ii', count($new_ids));
        $vals2 = [];
        foreach ($new_ids as $oid) {
            $vals2[] = $werkbon_id;
            $vals2[] = $oid;
        }

        $insertSql = "INSERT INTO werkbon_objecten (werkbon_id, object_id) VALUES $ph2";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param($types2, ...$vals2);

        if ($insertStmt->execute()) {
            setFlash(count($new_ids) . " nieuw(e) object(en) gekoppeld.", "success");
        } else {
            setFlash("Fout bij koppelen: " . $insertStmt->error, "error");
        }
        $insertStmt->close();
    } else {
        setFlash("Alle geselecteerde objecten waren al gekoppeld (of niet toegestaan).", "error");
    }

    header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id);
    exit;
}

/* --------------------------------------------------
   OUTPUT
-------------------------------------------------- */
$pageTitle = "Objecten koppelen";

ob_start();
?>
<div class="page-header">
    <h2>Objecten koppelen aan werkbon <?= e($werkbon['werkbonnummer'] ?? $werkbon_id) ?></h2>
</div>

<div class="card">
    <p><strong>Klant:</strong> <?= e($werkbon['bedrijfsnaam'] ?? 'Onbekend') ?></p>

    <?php if ($hasWerkadresCol && $werkadres_id > 0): ?>
        <p style="margin-top:-8px; color:#64748b;">
            Filter: klant-objecten + werkadres-objecten (werkadres_id <?= (int)$werkadres_id ?>)
        </p>
    <?php else: ?>
        <p style="margin-top:-8px; color:#64748b;">
            Filter: alleen klant-objecten
        </p>
    <?php endif; ?>

    <form method="get" style="margin-bottom:15px;">
        <input type="hidden" name="werkbon_id" value="<?= (int)$werkbon_id ?>">
        <input type="text" name="zoek" value="<?= e($zoek) ?>"
               placeholder="Zoek op code, omschrijving of locatie..."
               style="width:70%; padding:8px;">
        <button type="submit" class="btn">🔍 Zoeken</button>
        <a href="/monteur/werkbon_object_toevoegen.php?werkbon_id=<?= (int)$werkbon_id ?>" class="btn btn-secondary">Reset</a>
    </form>

    <form method="post">
        <table class="data-table small-table">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                    <th>Code</th>
                    <th>Omschrijving</th>
                    <th>Locatie</th>
                    <th>Verdieping</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$objecten || $objecten->num_rows === 0): ?>
                <tr><td colspan="5" class="text-center">Geen (beschikbare) objecten gevonden</td></tr>
            <?php else: ?>
                <?php while ($o = $objecten->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" name="object_ids[]" value="<?= (int)$o['object_id'] ?>"></td>
                        <td><?= e($o['code'] ?? '') ?></td>
                        <td><?= e($o['omschrijving'] ?? '') ?></td>
                        <td><?= e($o['locatie'] ?? '') ?></td>
                        <td><?= e($o['verdieping'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="form-actions" style="margin-top:15px;">
            <button type="submit" name="toevoegen" class="btn">➕ Geselecteerde toevoegen</button>
            <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>" class="btn btn-secondary">⬅ Terug</a>
        </div>
    </form>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checked = this.checked;
    document.querySelectorAll('input[name="object_ids[]"]').forEach(cb => cb.checked = checked);
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
