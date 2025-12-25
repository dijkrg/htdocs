<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

$pageTitle = "Gegevens importeren";

ob_start();
?>

<h2>📥 Gegevens importeren</h2>
<p>Kies hieronder het type gegevens dat je wilt importeren en upload het juiste Excel-bestand.</p>

<div class="import-container">

    <!-- 🟦 Klanten import -->
    <form action="klanten_import.php" method="post" enctype="multipart/form-data" class="import-form">
        <label for="klanten_file">📄 Klantenbestand (.xlsx)</label>
        <input type="file" name="excel_file" id="klanten_file" accept=".xlsx" required>
        <button type="submit" class="btn btn-primary">Importeer Klanten</button>
    </form>

    <!-- 🟩 Artikelen import -->
    <form action="artikelen_import.php" method="post" enctype="multipart/form-data" class="import-form">
        <label for="artikelen_file">📄 Artikelenbestand (.xlsx)</label>
        <input type="file" name="excel_file" id="artikelen_file" accept=".xlsx" required>
        <button type="submit" class="btn btn-primary">Importeer Artikelen</button>
    </form>

    <!-- 🟧 Objecten import -->
    <form action="objecten_import.php" method="post" enctype="multipart/form-data" class="import-form">
        <label for="objecten_file">📄 Objectenbestand (.xlsx)</label>
        <input type="file" name="excel_file" id="objecten_file" accept=".xlsx" required>
        <button type="submit" class="btn btn-primary">Importeer Objecten</button>
    </form>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
