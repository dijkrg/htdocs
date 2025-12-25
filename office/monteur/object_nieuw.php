<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldige werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

// Werkbon ophalen + ownership check + klant_id/werkadres_id
$stmt = $conn->prepare("
    SELECT werkbon_id, werkbonnummer, monteur_id, klant_id, werkadres_id
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

$klant_id = (int)$wb['klant_id'];
$werkadres_id = isset($wb['werkadres_id']) ? (int)$wb['werkadres_id'] : 0;

// Check welke kolommen bestaan in objecten (zodat dit niet crasht als jouw schema afwijkt)
$cols = [];
$cr = $conn->query("SHOW COLUMNS FROM objecten");
while ($cr && ($c = $cr->fetch_assoc())) {
    $cols[strtolower((string)$c['Field'])] = true;
}

$hasWerkadresCol   = isset($cols['werkadres_id']);
$hasStatusIdCol    = isset($cols['status_id']);
$hasOnderhoudCol   = isset($cols['datum_onderhoud']);
$hasFabricageCol   = isset($cols['fabricagejaar']);

$errors = [];
$values = [
    'code'          => '',
    'omschrijving'  => '',
    'datum_onderhoud' => '',
    'fabricagejaar' => '',
    'status_id'     => '',
];

// Status opties (als status_id bestaat)
$statusOpties = [];
if ($hasStatusIdCol) {
    $res = $conn->query("SELECT status_id, naam FROM object_status ORDER BY naam ASC");
    while ($res && ($r = $res->fetch_assoc())) $statusOpties[] = $r;
}

// POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['code'] = trim((string)($_POST['code'] ?? ''));
    $values['omschrijving'] = trim((string)($_POST['omschrijving'] ?? ''));

    if ($hasOnderhoudCol) {
        $values['datum_onderhoud'] = trim((string)($_POST['datum_onderhoud'] ?? ''));
        if ($values['datum_onderhoud'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['datum_onderhoud'])) {
            $errors[] = "Onderhoudsdatum moet YYYY-MM-DD zijn.";
        }
    }

    if ($hasFabricageCol) {
        $values['fabricagejaar'] = trim((string)($_POST['fabricagejaar'] ?? ''));
        if ($values['fabricagejaar'] !== '' && !preg_match('/^\d{4}$/', $values['fabricagejaar'])) {
            $errors[] = "Fabricagejaar moet een jaartal zijn (YYYY).";
        }
    }

    if ($hasStatusIdCol) {
        $values['status_id'] = (string)($_POST['status_id'] ?? '');
        if ($values['status_id'] !== '' && !ctype_digit($values['status_id'])) {
            $errors[] = "Ongeldige status.";
        }
    }

    if ($values['code'] === '') $errors[] = "Code is verplicht.";
    if ($values['omschrijving'] === '') $errors[] = "Omschrijving is verplicht.";

    if (!$errors) {
        // Insert query dynamisch opbouwen
        $fields = ['klant_id', 'code', 'omschrijving'];
        $placeholders = ['?', '?', '?'];
        $types = 'iss';
        $params = [$klant_id, $values['code'], $values['omschrijving']];

        if ($hasWerkadresCol && $werkadres_id > 0) {
            $fields[] = 'werkadres_id';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = $werkadres_id;
        }

        if ($hasOnderhoudCol && $values['datum_onderhoud'] !== '') {
            $fields[] = 'datum_onderhoud';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $values['datum_onderhoud'];
        }

        if ($hasFabricageCol && $values['fabricagejaar'] !== '') {
            $fields[] = 'fabricagejaar';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $values['fabricagejaar'];
        }

        if ($hasStatusIdCol && $values['status_id'] !== '') {
            $fields[] = 'status_id';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = (int)$values['status_id'];
        }

        $sql = "INSERT INTO objecten (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $ins = $conn->prepare($sql);
        $ins->bind_param($types, ...$params);
        $ins->execute();

        if ($ins->affected_rows <= 0) {
            $ins->close();
            setFlash("Object aanmaken mislukt.", "error");
        } else {
            $new_object_id = (int)$ins->insert_id;
            $ins->close();

            // Koppel direct aan werkbon (voorkom dubbelen)
            $k = $conn->prepare("
                INSERT INTO werkbon_objecten (werkbon_id, object_id)
                SELECT ?, ?
                FROM DUAL
                WHERE NOT EXISTS (
                    SELECT 1 FROM werkbon_objecten WHERE werkbon_id = ? AND object_id = ?
                )
            ");
            $k->bind_param("iiii", $werkbon_id, $new_object_id, $werkbon_id, $new_object_id);
            $k->execute();
            $k->close();

            setFlash("Nieuw object aangemaakt en gekoppeld.", "success");
            header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id . "#objecten");
            exit;
        }
    }
}

$pageTitle = "Nieuw object – Werkbon " . e($wb['werkbonnummer']);
ob_start();
?>

<div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
    <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>#objecten" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <h3>Nieuw object aanmaken (Werkbon <?= e($wb['werkbonnummer']) ?>)</h3>

    <?php if ($errors): ?>
        <div class="flash flash-error" style="background:#dc3545;">
            <?= e(implode(" ", $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid" style="max-width:720px;">
        <label>Code *</label>
        <input type="text" name="code" class="form-control" required value="<?= e($values['code']) ?>">

        <label>Omschrijving *</label>
        <input type="text" name="omschrijving" class="form-control" required value="<?= e($values['omschrijving']) ?>">

        <?php if ($hasOnderhoudCol): ?>
            <label>Onderhoudsdatum</label>
            <input type="date" name="datum_onderhoud" class="form-control" value="<?= e($values['datum_onderhoud']) ?>">
        <?php endif; ?>

        <?php if ($hasFabricageCol): ?>
            <label>Fabricagejaar</label>
            <input type="text" name="fabricagejaar" class="form-control" placeholder="bijv. 2020" value="<?= e($values['fabricagejaar']) ?>">
        <?php endif; ?>

        <?php if ($hasStatusIdCol): ?>
            <label>Status</label>
            <select name="status_id" class="form-control">
                <option value="">-- kies status --</option>
                <?php foreach ($statusOpties as $s): ?>
                    <option value="<?= (int)$s['status_id'] ?>" <?= ((string)$values['status_id'] === (string)$s['status_id']) ? 'selected' : '' ?>>
                        <?= e($s['naam']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <div style="margin-top:14px; display:flex; gap:10px;">
            <button type="submit" class="btn">✅ Opslaan & koppelen</button>
            <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>#objecten" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>

    <p style="margin-top:12px; opacity:.75; font-size:13px;">
        Dit object wordt automatisch aangemaakt met <strong>klant_id</strong> van de werkbon
        <?php if ($hasWerkadresCol && $werkadres_id > 0): ?> en <strong>werkadres_id</strong> van de werkbon<?php endif; ?>,
        en daarna direct gekoppeld aan deze werkbon.
    </p>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
