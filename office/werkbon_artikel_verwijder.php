<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);
$werkbon_id = intval($_GET['werkbon_id'] ?? 0);

if ($id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldige parameters.", "error");
    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

if (isset($_POST['bevestigen'])) {
    $stmt = $conn->prepare("DELETE FROM werkbon_artikelen WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash("Artikelregel verwijderd 🗑", "success");
    } else {
        setFlash("Fout bij verwijderen: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

ob_start();
?>
<div class="page-header">
    <h2>Artikelregel verwijderen</h2>
    <div class="header-actions">
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <p>Weet je zeker dat je deze artikelregel wilt verwijderen?</p>
    <form method="post" class="form-actions" style="justify-content: flex-start;">
        <button type="submit" name="bevestigen" class="btn btn-danger">Ja, verwijderen</button>
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">Annuleren</a>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Artikelregel verwijderen";
include __DIR__ . "/template/template.php";
