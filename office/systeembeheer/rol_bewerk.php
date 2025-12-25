<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
checkRole(['Admin']);

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash("Geen rol ID opgegeven.", "error");
    header("Location: rollen.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM rollen WHERE rol_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$rol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rol) {
    setFlash("Rol niet gevonden.", "error");
    header("Location: rollen.php");
    exit;
}

if (isset($_POST['opslaan'])) {
    $naam = trim($_POST['naam'] ?? '');
    if ($naam !== '') {
        $stmt = $conn->prepare("UPDATE rollen SET naam=? WHERE rol_id=?");
        $stmt->bind_param("si", $naam, $id);
        $stmt->execute();
        setFlash("Rol bijgewerkt!", "success");
        header("Location: rollen.php");
        exit;
    } else {
        setFlash("Rolnaam is verplicht.", "error");
    }
}

ob_start();
?>
<h2>Rol bewerken</h2>
<div class="card">
    <form method="post" class="form-styled">
        <label for="naam">Rolnaam*</label>
        <input type="text" name="naam" id="naam" value="<?= htmlspecialchars($rol['naam']) ?>" required>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">Opslaan</button>
            <a href="rollen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include '../template/template.php';
