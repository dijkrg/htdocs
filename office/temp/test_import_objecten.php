<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = __DIR__ . '/objecten.xlsx';

echo "<h2>🏢 Import objecten</h2>";

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $stmt = $conn->prepare("
        INSERT INTO objecten (code, klant_id, status_id, omschrijving)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            klant_id=VALUES(klant_id),
            status_id=VALUES(status_id),
            omschrijving=VALUES(omschrijving)
    ");
    $findKlant = $conn->prepare("SELECT klant_id FROM klanten WHERE debiteurnummer=?");
    $findStatus= $conn->prepare("SELECT status_id FROM object_status WHERE naam=?");

    $inserted=0; $skipped=0;

    for ($row=2; $row<=$highestRow; $row++) {
        $r = $sheet->rangeToArray("A{$row}:D{$row}", null, true, false)[0];
        [$code,$deb,$statusName,$oms] = $r;

        if (trim($code)==='' || trim($deb)===''){ $skipped++; continue; }

        // Zoek klant_id
        $findKlant->bind_param("s",$deb);
        $findKlant->execute();
        $res=$findKlant->get_result()->fetch_assoc();
        if(!$res){ $skipped++; continue; }
        $klant_id=$res['klant_id'];

        // Zoek status_id
        $status_id=null;
        if($statusName!==''){
            $findStatus->bind_param("s",$statusName);
            $findStatus->execute();
            $sr=$findStatus->get_result()->fetch_assoc();
            if($sr) $status_id=$sr['status_id'];
        }

        $stmt->bind_param("siis",$code,$klant_id,$status_id,$oms);
        $stmt->execute(); $inserted++;
    }

    echo "✅ Import voltooid: $inserted ingevoerd/bijgewerkt, $skipped overgeslagen.";
} catch(Throwable $e){ echo "❌ Fout: ".$e->getMessage(); }
