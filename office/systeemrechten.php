<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
checkRole(['Admin']); // Alleen Admin kan dit beheren

// Rollen ophalen uit de rollen-tabel
$res = $conn->query("SELECT naam FROM rollen ORDER BY naam ASC");
$rollen = [];
while ($row = $res->fetch_assoc()) {
    $rollen[] = $row['naam'];
}
$gekozenRol = $_GET['rol'] ?? $rollen[0] ?? null;

// Opslaan van rechten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gekozenRol = $_POST['rol'] ?? $gekozenRol;

    // Oude rechten wissen
    $stmt = $conn->prepare("DELETE FROM rol_rechten WHERE rol=?");
    $stmt->bind_param("s", $gekozenRol);
    $stmt->execute();

    // Nieuwe rechten opslaan
    if (!empty($_POST['rechten'])) {
        $stmt = $conn->prepare("INSERT INTO rol_rechten (rol, recht_id) VALUES (?, ?)");
        foreach ($_POST['rechten'] as $recht_id) {
            $stmt->bind_param("si", $gekozenRol, $recht_id);
            $stmt->execute();
        }
    }

    setFlash("✅ Rechten opgeslagen voor rol: $gekozenRol", "success");
    header("Location: systeemrechten.php?rol=" . urlencode($gekozenRol));
    exit;
}

// Alle rechten ophalen
$rechtenRes = $conn->query("SELECT * FROM rechten ORDER BY module, actie");
$alleRechten = $rechtenRes->fetch_all(MYSQLI_ASSOC);

// Bestaande rechten van de rol
$stmt = $conn->prepare("SELECT recht_id FROM rol_rechten WHERE rol=?");
$stmt->bind_param("s", $gekozenRol);
$stmt->execute();
$res = $stmt->get_result();
$rolRechten = array_column($res->fetch_all(MYSQLI_ASSOC), 'recht_id');
$stmt->close();

// Modules in gewenste volgorde
$moduleVolgorde = [
    'planboard',
    'klanten',
    'artikelen',
    'werkbonnen',
    'objecten',
    'contracten',
    'instellingen',
    'medewerkers',
    'mail'
];

// Alle unieke acties ophalen voor kolommen
$alleActies = array_unique(array_column($alleRechten, 'actie'));
sort($alleActies);

ob_start();
?>
<div class="page-header">
    <h2>Systeemrechten</h2>
</div>

<form method="get" action="">
    <label for="rol">Kies rol:</label>
    <select name="rol" id="rol" onchange="this.form.submit()">
        <?php foreach ($rollen as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>" <?= $r === $gekozenRol ? 'selected' : '' ?>>
                <?= htmlspecialchars($r) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<hr>

<form method="post">
    <input type="hidden" name="rol" value="<?= htmlspecialchars($gekozenRol) ?>">

    <h3>Rechten voor rol: <?= htmlspecialchars($gekozenRol) ?></h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>Module</th>
                <?php foreach ($alleActies as $actie): ?>
                    <th><?= htmlspecialchars(ucfirst($actie)) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($moduleVolgorde as $module): ?>
                <?php
                $moduleRechten = array_filter($alleRechten, fn($r) => $r['module'] === $module);
                if (empty($moduleRechten)) continue;
                ?>
                <tr>
                    <td><b><?= ucfirst($module) ?></b></td>
                    <?php foreach ($alleActies as $actie): ?>
                        <?php
                        $recht = array_filter($moduleRechten, fn($r) => $r['actie'] === $actie);
                        if ($recht) {
                            $recht = reset($recht);
                            ?>
                            <td style="text-align:center;">
                                <input type="checkbox" name="rechten[]" value="<?= $recht['recht_id'] ?>"
                                    <?= in_array($recht['recht_id'], $rolRechten) ? 'checked' : '' ?>>
                            </td>
                        <?php } else { ?>
                            <td></td>
                        <?php } ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-actions">
        <button type="submit" class="btn">Opslaan</button>
    </div>
</form>
<?php
$content = ob_get_clean();
$pageTitle = "Systeemrechten";
include __DIR__ . "/template/template.php";
