<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "Artikelen importeren";
ob_start();
?>
<h2>📥 Artikelen importeren</h2>
<form method="post" enctype="multipart/form-data">
    <label>Kies Excel-bestand (.xlsx):</label><br>
    <input type="file" name="xlsx" accept=".xlsx" required>
    <br><br>
    <button type="submit" class="btn">Importeren →</button>
</form>
<p>Excel indeling (rij 1 = headers):<br>
<code>artikelnummer | omschrijving | inkoopprijs | verkoopprijs | btw_tarief | categorie</code></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';

// === Verwerk import na submit ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx'])) {
    // ... jouw huidige importlogica hier ...
}
