<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__ . '/artikelen.xlsx';

echo "<h2>📦 Import artikelen</h2>";

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $stmt = $conn->prepare("
        INSERT INTO artikelen (artikelnummer, omschrijving, prijs, btw_tarief, categorie)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            omschrijving=VALUES(omschrijving),
            prijs=VALUES(prijs),
            btw_tarief=VALUES(btw_tarief),
            categorie=VALUES(categorie)
    ");

    $inserted=0; $skipped=0;

    for ($row=2; $row<=$highestRow; $row++) {
        $r = $sheet->rangeToArray("A{$row}:E{$row}", null, true, false)[0];
        [$art,$oms,$pr,$btw,$cat] = $r;

        if (trim($art)==='' || trim($oms)==='' || trim($pr)===''){ $skipped++; continue; }

        $stmt->bind_param("ssdss",$art,$oms,$pr,$btw,$cat);
        $stmt->execute(); $inserted++;
    }

    echo "✅ Import voltooid: $inserted ingevoerd/bijgewerkt, $skipped overgeslagen.";
} catch(Throwable $e){ echo "❌ Fout: ".$e->getMessage(); }
