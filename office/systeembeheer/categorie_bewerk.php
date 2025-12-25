<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot categorieën.", "error");
    header("Location: ../index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash("Geen categorie ID opgegeven.", "error");
    header("Location: categorieen.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM categorieen WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$categorie = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$categorie) {
    setFlash("Categorie niet gevonden.", "error");
    header("Location: categorieen.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam'] ?? '');
    if ($naam !== '') {
        $stmt = $conn->prepare("UPDATE categorieen SET naam=? WHERE id=?");
        $stmt->bind_param("si", $naam, $id);
        if ($stmt->execute()) {
            setFlash("Categorie succesvol bijgewerkt!", "success");
            header("Location: categorieen.php");
            exit;
        } else {
            setFlash("Fout bij opslaan: " . $stmt->error, "error");
        }
    } else {
        setFlash("Naam mag niet leeg zijn.", "error");
    }
}

ob_start();
?>
<h2>Categorie bewerken</h2>
<div class="card">
    <form method="post" class="form-styled">
        <label for="naam">Naam*</label>
        <input type="text" name="naam" id="naam" value="<?= htmlspecialchars($categorie['naam']) ?>" required>

        <div class="form-actions">
            <button type="submit" class="btn">Opslaan</button>
            <a href="categorieen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Categorie bewerken";
include __DIR__ . "/../template/template.php";
