<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Pad naar een testbestand (maak zelf een klein .xlsx in dezelfde map)
$inputFileName = "C:/xampp/htdocs/office/test.xlsx";

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    echo "Bestand succesvol geopend: {$inputFileName}<br>";
    echo "Grootte: {$highestRow} rijen × {$highestColumn} kolommen<br><br>";

    // Headers (eerste rij) tonen
    $headers = $sheet->rangeToArray('A1:' . $highestColumn . '1')[0];
    echo "Headers gevonden:<br>";
    echo "<pre>";
    print_r($headers);
    echo "</pre>";

} catch (\Throwable $e) {
    echo "Fout bij openen Excel: " . $e->getMessage();
}
