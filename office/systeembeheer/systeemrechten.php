<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin & Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot systeemrechten.", "error");
    header("Location: ../index.php");
    exit;
}

// Rollen ophalen
$res = $conn->query("SELECT naam FROM rollen ORDER BY naam ASC");
$rollen = [];
while ($row = $res->fetch_assoc()) {
    $rollen[] = $row['naam'];
}
$gekozenRol = $_GET['rol'] ?? $rollen[0] ?? null;

// Opslaan nieuwe rechten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gekozenRol = $_POST['rol'] ?? $gekozenRol;

    // Alles wissen
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

    setFlash("Rechten opgeslagen voor rol: $gekozenRol", "success");
    header("Location: systeemrechten.php?rol=" . urlencode($gekozenRol));
    exit;
}

// Alle rechten ophalen
$rechtenRes = $conn->query("SELECT * FROM rechten ORDER BY module, actie");
$alleRechten = $rechtenRes->fetch_all(MYSQLI_ASSOC);

// Bestaande rechten
$stmt = $conn->prepare("SELECT recht_id FROM rol_rechten WHERE rol=?");
$stmt->bind_param("s", $gekozenRol);
$stmt->execute();
$res = $stmt->get_result();
$rolRechten = array_column($res->fetch_all(MYSQLI_ASSOC), 'recht_id');
$stmt->close();

// Gewenste module volgorde
$moduleVolgorde = [
    'planboard', 'klanten', 'artikelen', 'werkbonnen', 'objecten',
    'contracten', 'instellingen', 'medewerkers', 'mail'
];

// Alle unieke acties ophalen
$alleActies = array_unique(array_column($alleRechten, 'actie'));
sort($alleActies);

$pageTitle = "Systeemrechten";
ob_start();
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <h2>🔐 Systeemrechten</h2>
</div>

<!-- ROLE SELECTOR -->
<div class="card" style="max-width:320px; margin-bottom:20px;">
    <form method="get">
        <label><b>Selecteer rol:</b></label>
        <select name="rol" class="form-input" onchange="this.form.submit()">
            <?php foreach ($rollen as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>" <?= $r === $gekozenRol ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- RECHTEN TABEL -->
<form method="post">
<input type="hidden" name="rol" value="<?= htmlspecialchars($gekozenRol) ?>">

<div class="card">
    <h3>Rechten voor rol: <b><?= htmlspecialchars($gekozenRol) ?></b></h3>

    <table class="data-table rights-table">
        <thead>
            <tr>
                <th style="width:180px;">Module</th>
                <?php foreach ($alleActies as $actie): ?>
                    <th style="text-align:center;"><?= ucfirst($actie) ?></th>
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
                        $actief = in_array($recht['recht_id'], $rolRechten);
                    ?>
                        <td style="text-align:center;">
                            <label class="toggle-icon-wrap">
                                <input type="checkbox"
                                       name="rechten[]"
                                       value="<?= $recht['recht_id'] ?>"
                                       <?= $actief ? 'checked' : '' ?>>
                                <span class="toggle-icon <?= $actief ? 'on' : 'off' ?>">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </label>
                        </td>
                    <?php } else { ?>
                        <td></td>
                    <?php } ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-actions" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Opslaan
        </button>
    </div>
</div>

</form>

<!-- TOGGLE SCRIPT -->
<script>
document.querySelectorAll(".toggle-icon-wrap input[type=checkbox]").forEach(function(cb){
    cb.addEventListener("change", function(){
        let icon = this.parentElement.querySelector(".toggle-icon");
        if (this.checked) {
            icon.classList.add("on");
            icon.classList.remove("off");
        } else {
            icon.classList.remove("on");
            icon.classList.add("off");
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . "/../template/template.php";
