<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__ . '/test.xlsx';

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    echo "Bestand succesvol geopend: {$inputFileName}<br>";
    echo "Grootte: {$highestRow} rijen × {$highestColumn} kolommen<br><br>";

    // Headers tonen
    $headers = $sheet->rangeToArray('A1:' . $highestColumn . '1')[0];
    echo "<strong>Headers gevonden:</strong><br>";
    echo "<pre>"; print_r($headers); echo "</pre>";

    // Alle rijen tonen (vanaf rij 2 = data)
    echo "<strong>Data rijen:</strong><br>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>";
    foreach ($headers as $h) {
        echo "<th>" . htmlspecialchars($h) . "</th>";
    }
    echo "</tr>";

    for ($row = 2; $row <= $highestRow; $row++) {
        $rowData = $sheet->rangeToArray("A{$row}:" . $highestColumn . $row, null, true, false)[0];
        echo "<tr>";
        foreach ($rowData as $cell) {
            echo "<td>" . htmlspecialchars((string)$cell) . "</td>";
        }
        echo "</tr>";
    }

    echo "</table>";

} catch (\Throwable $e) {
    echo "Fout bij openen Excel: " . $e->getMessage();
}
