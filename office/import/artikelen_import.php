<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📥 Artikelen importeren";
ob_start();
$resultaatHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bestand'])) {
    $filePath = $_FILES['bestand']['tmp_name'];

    if (!file_exists($filePath)) {
        $resultaatHtml = '<div class="flash flash-error">❌ Geen bestand ontvangen.</div>';
    } else {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            // Header bepalen
            $header = array_map(fn($v) => strtolower(trim($v)), $rows[1]);
            unset($rows[1]);

            $importCount = 0;
            $errors = [];

            foreach ($rows as $r) {
                $row = array_combine($header, $r);

                $artikelnummer = trim($row['artikelnummer'] ?? '');
                $omschrijving  = trim($row['omschrijving'] ?? '');
                $eenheid       = trim($row['eenheid'] ?? 'stuk');
                $inkoopprijs   = floatval(str_replace(',', '.', $row['inkoopprijs'] ?? 0));
                $verkoopprijs  = floatval(str_replace(',', '.', $row['verkoopprijs'] ?? 0));

                if ($artikelnummer === '' || $omschrijving === '') continue;

                // Controleer op duplicaten
                $check = $conn->prepare("SELECT artikel_id FROM artikelen WHERE artikelnummer = ?");
                $check->bind_param("s", $artikelnummer);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $errors[] = "Artikel $artikelnummer bestaat al — overgeslagen.";
                    $check->close();
                    continue;
                }
                $check->close();

                $stmt = $conn->prepare("
                    INSERT INTO artikelen (artikelnummer, omschrijving, eenheid, inkoopprijs, verkoopprijs)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssdd", $artikelnummer, $omschrijving, $eenheid, $inkoopprijs, $verkoopprijs);

                if ($stmt->execute()) $importCount++;
                else $errors[] = "Fout bij artikel $artikelnummer: " . $stmt->error;

                $stmt->close();
            }

            ob_start();
            echo '<div class="card">';
            echo "<h3>✅ Artikelen import voltooid</h3>";
            echo "<p><strong>$importCount</strong> artikel(en) toegevoegd.</p>";
            if (!empty($errors)) {
                echo "<h4>⚠️ Meldingen:</h4><ul>";
                foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
                echo "</ul>";
            }
            echo '<a href="../artikelen.php" class="btn btn-secondary" style="margin-top:10px;">⬅ Terug naar Artikelen</a>';
            echo '</div>';
            $resultaatHtml = ob_get_clean();

        } catch (Exception $e) {
            $resultaatHtml = '<div class="flash flash-error">❌ Fout bij importeren van artikelen: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<div class="page-header">
    <h2>📥 Artikelen importeren</h2>
    <p>Hieronder zie je het resultaat van de importactie.</p>
</div>

<?= $resultaatHtml ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
