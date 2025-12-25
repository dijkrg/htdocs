<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot categorieën.", "error");
    header("Location: ../index.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam'] ?? '');

    if ($naam === '') {
        $errors[] = "Naam is verplicht.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO categorieen (naam) VALUES (?)");
        $stmt->bind_param("s", $naam);
        if ($stmt->execute()) {
            setFlash("Categorie succesvol toegevoegd!", "success");
            header("Location: categorieen.php");
            exit;
        } else {
            $errors[] = "Fout bij opslaan: " . $stmt->error;
        }
    }

    foreach ($errors as $err) setFlash($err, "error");
}

ob_start();
?>
<h2>Nieuwe categorie toevoegen</h2>
<div class="card">
    <form method="post" class="form-styled">
        <label for="naam">Naam*</label>
        <input type="text" name="naam" id="naam" required>

        <div class="form-actions">
            <button type="submit" class="btn">Opslaan</button>
            <a href="categorieen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Categorie toevoegen";
include __DIR__ . "/../template/template.php";
