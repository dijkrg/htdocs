<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
checkRole(['Admin','Manager']);

$medewerker_id = intval($_GET['id'] ?? 0);

// Medewerker ophalen
$stmt = $conn->prepare("SELECT * FROM medewerkers WHERE medewerker_id=?");
$stmt->bind_param("i", $medewerker_id);
$stmt->execute();
$res = $stmt->get_result();
$medewerker = $res->fetch_assoc();
$stmt->close();

if (!$medewerker) {
    setFlash("Medewerker niet gevonden.", "error");
    header("Location: medewerkers.php");
    exit;
}

// Verwijderen bevestigen
if (isset($_POST['bevestig'])) {
    $stmt = $conn->prepare("DELETE FROM medewerkers WHERE medewerker_id=?");
    $stmt->bind_param("i", $medewerker_id);

    if ($stmt->execute()) {
        setFlash("Medewerker succesvol verwijderd.", "success");
    } else {
        setFlash("Fout bij verwijderen: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: medewerkers.php");
    exit;
}

// Content
ob_start();
?>
<h2>Medewerker verwijderen</h2>
<p>Weet je zeker dat je deze medewerker wilt verwijderen?</p>

<ul>
    <li><b>Personeelsnummer:</b> <?= htmlspecialchars($medewerker['personeelsnummer']) ?></li>
    <li><b>Naam:</b> <?= htmlspecialchars($medewerker['voornaam'] . " " . $medewerker['achternaam']) ?></li>
    <li><b>Email:</b> <?= htmlspecialchars($medewerker['email']) ?></li>
    <li><b>Rol:</b> <?= htmlspecialchars($medewerker['rol']) ?></li>
</ul>

<form method="post">
    <button type="submit" name="bevestig" class="btn btn-danger">Ja, verwijderen</button>
    <a href="medewerkers.php" class="btn btn-secondary">Nee, terug</a>
</form>
<?php
$content = ob_get_clean();
$pageTitle = "Medewerker verwijderen";
include __DIR__ . "/template/template.php";
