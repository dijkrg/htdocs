<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "Klanten importeren";
ob_start();
?>
<h2>📥 Klanten importeren</h2>
<form method="post" enctype="multipart/form-data">
    <label>Kies Excel-bestand (.xlsx):</label><br>
    <input type="file" name="xlsx" accept=".xlsx" required>
    <br><br>
    <button type="submit" class="btn">Importeren →</button>
</form>
<p>Excel indeling (rij 1 = headers):<br>
<code>debiteurnummer | bedrijfsnaam | contactpersoon | email | telefoon | adres | postcode | plaats</code></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';

// === Verwerk import ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx'])) {
    try {
        $spreadsheet = IOFactory::load($_FILES['xlsx']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $stmt = $conn->prepare("
            INSERT INTO klanten (debiteurnummer, bedrijfsnaam, contactpersoon, email, telefoon, adres, postcode, plaats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              bedrijfsnaam=VALUES(bedrijfsnaam),
              contactpersoon=VALUES(contactpersoon),
              email=VALUES(email),
              telefoon=VALUES(telefoon),
              adres=VALUES(adres),
              postcode=VALUES(postcode),
              plaats=VALUES(plaats)
        ");

        $inserted=0; $skipped=0;
        for ($row=2; $row<=$highestRow; $row++) {
            $r = $sheet->rangeToArray("A{$row}:H{$row}", null, true, false)[0];
            [$deb,$bed,$cont,$mail,$tel,$adr,$pc,$pl] = $r;

            if (trim((string)$deb)==='' || trim((string)$bed)==='') { $skipped++; continue; }

            $stmt->bind_param("ssssssss", $deb,$bed,$cont,$mail,$tel,$adr,$pc,$pl);
            $stmt->execute();
            $inserted++;
        }

        echo "<h3>✅ Import voltooid</h3>";
        echo "<p>Ingevoerd/bijgewerkt: $inserted<br>Overgeslagen: $skipped</p>";

    } catch(Throwable $e) {
        echo "<p>❌ Fout: ".htmlspecialchars($e->getMessage())."</p>";
    }
}
