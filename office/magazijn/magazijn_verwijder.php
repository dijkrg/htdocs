<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang.", "error");
    header("Location: magazijnen.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldig magazijn.", "error");
    header("Location: magazijnen.php");
    exit;
}

$magazijn_id = (int)$_GET['id'];
$magazijn = $conn->query("SELECT * FROM magazijnen WHERE magazijn_id = {$magazijn_id}")->fetch_assoc();

if (!$magazijn) {
    setFlash("Magazijn niet gevonden.", "error");
    header("Location: magazijnen.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->query("DELETE FROM magazijnen WHERE magazijn_id = {$magazijn_id}");
    setFlash("Magazijn '{$magazijn['naam']}' verwijderd ✅", "success");
    header("Location: magazijnen.php");
    exit;
}

$pageTitle = "🗑 Magazijn verwijderen";
ob_start();
?>
<div class="page-header">
    <h2>🗑 Magazijn verwijderen</h2>
    <a href="magazijnen.php" class="btn btn-secondary">⬅ Annuleren</a>
</div>

<div class="card warning">
    <p><strong>Weet je zeker dat je dit magazijn wilt verwijderen?</strong></p>
    <p>Naam: <?= htmlspecialchars($magazijn['naam']) ?><br>
       Type: <?= htmlspecialchars($magazijn['type']) ?><br>
       Locatie: <?= htmlspecialchars($magazijn['locatie'] ?? '-') ?></p>

    <form method="post">
        <button type="submit" class="btn btn-danger">🗑 Verwijderen</button>
        <a href="magazijnen.php" class="btn btn-secondary">Annuleren</a>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
