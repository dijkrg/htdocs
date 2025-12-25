<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__ . '/klanten.xlsx';

echo "<h2>🌍 Import klanten</h2>";

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $stmt = $conn->prepare("
        INSERT INTO klanten (debiteurnummer, bedrijfsnaam, contactpersoon, telefoonnummer, email, adres, postcode, plaats, opmerkingen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            bedrijfsnaam=VALUES(bedrijfsnaam),
            contactpersoon=VALUES(contactpersoon),
            telefoonnummer=VALUES(telefoonnummer),
            email=VALUES(email),
            adres=VALUES(adres),
            postcode=VALUES(postcode),
            plaats=VALUES(plaats),
            opmerkingen=VALUES(opmerkingen)
    ");

    $inserted=0; $skipped=0;

    for ($row=2; $row<=$highestRow; $row++) {
        $r = $sheet->rangeToArray("A{$row}:I{$row}", null, true, false)[0];
        [$deb,$bed,$con,$tel,$em,$adr,$pc,$pl,$opm] = $r;

        if (trim($deb)==='' || trim($bed)==='') { $skipped++; continue; }

        $stmt->bind_param("sssssssss",$deb,$bed,$con,$tel,$em,$adr,$pc,$pl,$opm);
        $stmt->execute(); $inserted++;
    }

    echo "✅ Import voltooid: $inserted ingevoerd/bijgewerkt, $skipped overgeslagen.";
} catch(Throwable $e){ echo "❌ Fout: ".$e->getMessage(); }
