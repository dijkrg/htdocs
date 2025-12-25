<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
checkRole(['Admin']);

$errors = [];
if (isset($_POST['opslaan'])) {
    $naam = trim($_POST['naam'] ?? '');
    if ($naam === '') $errors[] = "Rolnaam is verplicht.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO rollen (naam) VALUES (?)");
        $stmt->bind_param("s", $naam);
        if ($stmt->execute()) {
            setFlash("Rol succesvol toegevoegd!", "success");
            header("Location: rollen.php");
            exit;
        } else {
            $errors[] = "Fout: " . $stmt->error;
        }
    }
    foreach ($errors as $err) setFlash($err, "error");
}

ob_start();
?>
<h2>Nieuwe rol toevoegen</h2>
<div class="card">
    <form method="post" class="form-styled">
        <label for="naam">Rolnaam*</label>
        <input type="text" name="naam" id="naam" required>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">Opslaan</button>
            <a href="rollen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include '../template/template.php';
