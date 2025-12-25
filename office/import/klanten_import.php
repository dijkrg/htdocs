<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash('Geen toegang.', 'error');
    header('Location: ../index.php');
    exit;
}

$pageTitle = "📥 Klanten importeren";
ob_start();
$resultaatHtml = '';

function norm_key($s) {
    $s = strtolower((string)$s);
    $s = str_replace(['.', ',', ';', ':', '/', '\\', '-', '–', '—'], ' ', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}
function v($row, $key) {
    return trim((string)($row[$key] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bestand'])) {
    $filePath = $_FILES['bestand']['tmp_name'];

    if (!file_exists($filePath)) {
        setFlash('❌ Geen bestand ontvangen.', 'error');
        header('Location: index.php');
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            throw new Exception('Het bestand bevat geen gegevens.');
        }

        // Zoek header
        $headerRow = null;
        foreach ($rows as $i => $r) {
            if (!empty(array_filter($r))) { $headerRow = $i; break; }
        }
        if (!$headerRow) throw new Exception('Geen header rij gevonden.');

        $rawHeader = $rows[$headerRow];
        unset($rows[$headerRow]);

        // Header-mapping
        $aliases = [
            'debiteur nr'     => 'debiteurnummer',
            'debiteur nummer' => 'debiteurnummer',
            'debiteurnr'      => 'debiteurnummer',
            'debiteur'        => 'debiteurnummer',
            'e mail'          => 'email',
            'e-mail'          => 'email',
            'mail'            => 'email',
            'telefoonnummer'  => 'telefoon',
            'tel'             => 'telefoon',
            'telnr'           => 'telefoon',
            'bedrijfs naam'   => 'bedrijfsnaam',
        ];

        $header = [];
        foreach ($rawHeader as $colLetter => $label) {
            $n = norm_key($label);
            if (isset($aliases[$n])) $n = $aliases[$n];
            $header[$colLetter] = $n;
        }

        // Databasevelden
        $existingCols = [];
        $resCols = $conn->query("SHOW COLUMNS FROM klanten");
        while ($c = $resCols->fetch_assoc()) {
            $existingCols[] = $c['Field'];
        }

        // Mapping databasekolommen
        $fieldMap = [
            'bedrijfsnaam'   => in_array('bedrijfsnaam', $existingCols, true) ? 'bedrijfsnaam' : null,
            'debiteurnummer' => in_array('debiteurnummer', $existingCols, true) ? 'debiteurnummer' : null,
            'contactpersoon' => in_array('contactpersoon', $existingCols, true) ? 'contactpersoon' : null,
            'telefoon'       => in_array('telefoon', $existingCols, true) ? 'telefoon' :
                               (in_array('telefoonnummer', $existingCols, true) ? 'telefoonnummer' : null),
            'email'          => in_array('email', $existingCols, true) ? 'email' : null,
            'adres'          => in_array('adres', $existingCols, true) ? 'adres' : null,
            'postcode'       => in_array('postcode', $existingCols, true) ? 'postcode' : null,
            'plaats'         => in_array('plaats', $existingCols, true) ? 'plaats' : null,
        ];

        $insertable = array_values(array_filter($fieldMap));
        $placeholders = implode(',', array_fill(0, count($insertable), '?'));
        $stmt = $conn->prepare("INSERT INTO klanten (" . implode(',', $insertable) . ") VALUES ($placeholders)");
        if (!$stmt) throw new Exception("Kon INSERT niet voorbereiden: " . $conn->error);

        // Duplicaat-check
        $debnrCol = $fieldMap['debiteurnummer'] ?? null;
        $dupCheck = $debnrCol ? $conn->prepare("SELECT klant_id FROM klanten WHERE $debnrCol = ?") : null;

        $importCount = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($rows as $i => $r) {
            if (empty(array_filter($r))) continue;
            $row = [];
            foreach ($r as $colLetter => $val) {
                $key = $header[$colLetter] ?? null;
                if ($key) $row[$key] = trim((string)$val);
            }

            $bedrijfsnaam = v($row, 'bedrijfsnaam');
            if ($bedrijfsnaam === '') continue;

            $debiteurnummer = v($row, 'debiteurnummer');
            if ($debnrCol && $debiteurnummer !== '' && $dupCheck) {
                $dupCheck->bind_param('s', $debiteurnummer);
                $dupCheck->execute();
                $dupRes = $dupCheck->get_result();
                if ($dupRes && $dupRes->num_rows > 0) {
                    $duplicates++;
                    continue;
                }
            }

            $values = [];
            foreach ($insertable as $dbField) {
                $logical = array_search($dbField, $fieldMap, true);
                $values[] = v($row, $logical);
            }

            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) $importCount++;
            else $errors[] = "Rij $i (" . htmlspecialchars($bedrijfsnaam) . "): " . $stmt->error;
        }

        // Resultaat
        ob_start();
        echo "<div class='card'>";
        echo "<div style='font-size:15px; line-height:1.6'>";
        echo "✅ <strong>Import voltooid</strong><br>";
        echo "<small>$importCount klant(en) toegevoegd op " . date('d-m-Y H:i') . ".</small><br>";
        if ($duplicates > 0) echo "🔁 $duplicates overgeslagen (bestonden al).<br>";
        echo "</div>";

        if (!empty($errors)) {
            echo "<hr><b>⚠️ Fouten:</b><ul style='margin-top:5px'>";
            foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
            echo "</ul>";
        }

        echo "<a href='../klanten.php' class='btn btn-secondary' style='margin-top:10px;'>⬅ Terug naar Klanten</a>";
        echo "</div>";
        $resultaatHtml = ob_get_clean();

    } catch (Throwable $e) {
        $resultaatHtml = "<div class='flash flash-error'>❌ Fout bij importeren: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="page-header">
    <h2>📥 Klanten importeren</h2>
    <p>Upload hieronder een Excel-bestand met klantgegevens.<br>
       Vereiste kolommen (hoofdletters onbelangrijk): <code>Debiteur nr.</code>, <code>Bedrijfsnaam</code>, <code>Adres</code>,
       <code>Postcode</code>, <code>Plaats</code>, <code>Contactpersoon</code>, <code>Telefoon</code>, <code>E-mail</code>.</p>
</div>

<form method="post" enctype="multipart/form-data" class="import-form">
    <input type="file" name="bestand" accept=".xlsx" required>
    <button type="submit" class="btn btn-primary">📤 Importeer Klanten</button>
</form>

<?= $resultaatHtml ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
