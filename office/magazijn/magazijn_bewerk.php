<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang.", "error");
    header("Location: magazijnen.php");
    exit;
}

$magazijn_id = (int)($_GET['id'] ?? 0);
$magazijn = $conn->query("SELECT * FROM magazijnen WHERE magazijn_id = {$magazijn_id}")->fetch_assoc();

if (!$magazijn) {
    setFlash("Magazijn niet gevonden.", "error");
    header("Location: magazijnen.php");
    exit;
}

$pageTitle = "✏️ Magazijn bewerken";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam']);
    $type = $_POST['type'] ?? 'gebouw';
    $locatie = trim($_POST['locatie']);

    if ($naam === '') {
        setFlash("Naam is verplicht.", "error");
    } else {
        $stmt = $conn->prepare("UPDATE magazijnen SET naam=?, type=?, locatie=? WHERE magazijn_id=?");
        $stmt->bind_param("sssi", $naam, $type, $locatie, $magazijn_id);
        $stmt->execute();

        setFlash("Magazijn bijgewerkt ✅", "success");
        header("Location: magazijnen.php");
        exit;
    }
}

ob_start();
?>
<div class="page-header">
    <h2>✏️ Magazijn bewerken</h2>
    <a href="magazijnen.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" class="form-card">
        <label>Naam *</label>
        <input type="text" name="naam" value="<?= htmlspecialchars($magazijn['naam']) ?>" required>

        <label>Type *</label>
        <select name="type" required>
            <option value="gebouw" <?= $magazijn['type'] === 'gebouw' ? 'selected' : '' ?>>Gebouw</option>
            <option value="voertuig" <?= $magazijn['type'] === 'voertuig' ? 'selected' : '' ?>>Voertuig</option>
        </select>

        <label>Locatie</label>
        <input type="text" name="locatie" value="<?= htmlspecialchars($magazijn['locatie']) ?>">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="magazijnen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
