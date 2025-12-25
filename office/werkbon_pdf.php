<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/tcpdf/tcpdf.php';

// ✅ 1. Werkbon-ID ophalen en valideren
$werkbon_id = intval($_GET['id'] ?? 0);
if ($werkbon_id <= 0) {
    die("Ongeldige werkbon ID");
}

// ✅ 2. Werkbon + klant + werkadres ophalen
$stmt = $conn->prepare("
    SELECT w.*,
           k.bedrijfsnaam       AS klant_naam,
           k.adres              AS klant_adres,
           k.postcode           AS klant_postcode,
           k.plaats             AS klant_plaats,
           wa.bedrijfsnaam      AS wa_naam,
           wa.adres             AS wa_adres,
           wa.postcode          AS wa_postcode,
           wa.plaats            AS wa_plaats,
           CONCAT(m.voornaam, ' ', m.achternaam) AS monteur_naam,
           tw.naam              AS type_werkzaamheden_naam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN medewerkers m ON w.monteur_id = m.medewerker_id
    LEFT JOIN type_werkzaamheden tw ON w.type_werkzaamheden_id = tw.id
    WHERE w.werkbon_id = ?
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();


if (!$werkbon) {
    die("Werkbon niet gevonden");
}

// ✅ Datum bepalen
$datum = $werkbon['datum'] ?? $werkbon['uitvoerdatum'] ?? null;
$datumTekst = ($datum && $datum !== '0000-00-00') ? date('d-m-Y', strtotime($datum)) : '-';

// ✅ 3. Artikelen ophalen (zonder prijzen)
$artikelen_stmt = $conn->prepare("
    SELECT a.omschrijving, wa.aantal
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON wa.artikel_id = a.artikel_id
    WHERE wa.werkbon_id = ?
");
$artikelen_stmt->bind_param("i", $werkbon_id);
$artikelen_stmt->execute();
$artikelen = $artikelen_stmt->get_result();
$artikelen_stmt->close();

// ✅ 4. Urenregistratie ophalen
$uren_stmt = $conn->prepare("
    SELECT wu.*, 
           CONCAT(m.voornaam, ' ', m.achternaam) AS monteur_naam,
           us.omschrijving AS uursoort_naam
    FROM werkbon_uren wu
    LEFT JOIN medewerkers m ON wu.monteur_id = m.medewerker_id
    LEFT JOIN uursoorten us ON wu.uursoort_id = us.uursoort_id
    WHERE wu.werkbon_id = ?
    ORDER BY wu.datum ASC, wu.begintijd ASC
");
$uren_stmt->bind_param("i", $werkbon_id);
$uren_stmt->execute();
$urenregistraties = $uren_stmt->get_result();
$uren_stmt->close();

// ✅ 5. Objecten ophalen
$objecten_stmt = $conn->prepare("
    SELECT o.*, s.naam AS status_naam
    FROM werkbon_objecten wo
    LEFT JOIN objecten o ON wo.object_id = o.object_id
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE wo.werkbon_id = ?
");
$objecten_stmt->bind_param("i", $werkbon_id);
$objecten_stmt->execute();
$objecten = $objecten_stmt->get_result();
$objecten_stmt->close();


// ================== 6. PDF START ===================
class WerkbonPDF extends TCPDF {
    public $werkbonnummer = '';  // ✅ Nieuw: property voor werkbonnummer

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(
            0,
            10,
            'ABC Brandbeveiliging - Werkbon ' . $this->werkbonnummer .
            ' - Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(),
            0,
            0,
            'L'
        );
    }
}

$pdf = new WerkbonPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->werkbonnummer = $werkbon['werkbonnummer'] ?? '-';  // ✅ Werkbonnummer instellen
$pdf->setPrintHeader(false);
$pdf->SetCreator('ABC Brandbeveiliging');
$pdf->SetAuthor('ABC Brandbeveiliging');
$pdf->SetTitle('Werkbon ' . ($werkbon['werkbonnummer'] ?? '-'));
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();


// ================== 7. HEADER ===================
$logoPath = __DIR__ . '/template/logo_abc.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 10, 85);
}

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(80, 12);
$pdf->MultiCell(100, 5,
    "ABC Brandbeveiliging\n".
    "Ambachtsweg 5R\n".
    "3953 BZ Maarsbergen\n".
    "info@abcbrandbeveiliging.nl\n".
    "085-3013175\n\n".
    "KvK: 61053155\n".
    "BTW nr. NL001903207B52\n".
    "IBAN: NL10SNSB8839005668",
    0, 'R', false
);

// ================== 8. WERKBONGEGEVENS ==================
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Werkbongegevens', 0, 1, 'L');

$rowHeight = 6;

// Gegevens ophalen
$werkbonnummer = $werkbon['werkbonnummer'] ?? '-';
$typeWerk = $werkbon['type_werkzaamheden_naam'] ?? '-';
$uitvoering = (!empty($werkbon['uitvoerdatum']) && $werkbon['uitvoerdatum'] != '0000-00-00')
    ? date('d-m-Y', strtotime($werkbon['uitvoerdatum']))
    : '-';
$status = $werkbon['status'] ?? '-';

// Monteur naam ophalen
$monteurNaam = '-';
if (!empty($werkbon['monteur_id'])) {
    $mstmt = $conn->prepare("SELECT CONCAT(voornaam, ' ', achternaam) AS naam FROM medewerkers WHERE medewerker_id = ?");
    $mstmt->bind_param("i", $werkbon['monteur_id']);
    $mstmt->execute();
    $mres = $mstmt->get_result()->fetch_assoc();
    $monteurNaam = $mres['naam'] ?? '-';
    $mstmt->close();
}

$werkGereed = !empty($werkbon['werk_gereed']) ? 'Ja' : 'Nee';
$contractnummer = $werkbon['contractnummer'] ?? '-';

// 📐 Kolombreedtes (4 kolommen op 180 mm)
$colWidth = 180 / 4;

// ✅ Rij 1: labels
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($colWidth, $rowHeight, 'Werkbonnummer', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, 'Type werkzaamheden', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, 'Uitvoering datum', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, 'Werkbon status', 0, 1, 'L');

// ✅ Rij 1: waarden
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($colWidth, $rowHeight, $werkbonnummer, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, $typeWerk, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, $uitvoering, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, $status, 0, 1, 'L');

// ✅ Rij 2: labels
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($colWidth, $rowHeight, 'Monteur', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, 'Werk gereed', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, 'Contractnummer', 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, '', 0, 1, 'L'); // lege kolom voor later

// ✅ Rij 2: waarden
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($colWidth, $rowHeight, $monteurNaam, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, $werkGereed, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, $contractnummer, 0, 0, 'L');
$pdf->Cell($colWidth, $rowHeight, '', 0, 1, 'L');

$pdf->Ln(6);




// ================== 9. KLANTGEGEVENS + WERKADRES (naast elkaar) ===================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Klantgegevens & Werkadres', 0, 1, 'L');
$pdf->Ln(2);

$colWidth = 90;
$gap = 6;
$rowHeight = 5;

$startX = $pdf->GetX();
$startY = $pdf->GetY();

$leftX  = $startX;
$rightX = $startX + $colWidth + $gap;

// ✅ KLANTGEGEVENS TITEL
$pdf->SetXY($leftX, $startY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($colWidth, $rowHeight, 'Klantgegevens', 0, 1, 'L');

// ✅ KLANTGEGEVENS INHOUD
$pdf->SetFont('helvetica', '', 10);
$klantTekst  = ($werkbon['klant_naam'] ?? '-') . "\n";
$klantTekst .= ($werkbon['klant_adres'] ?? '-') . "\n";
$klantTekst .= trim(($werkbon['klant_postcode'] ?? '') . ' ' . ($werkbon['klant_plaats'] ?? ''));
$pdf->SetX($leftX);
$pdf->MultiCell($colWidth, $rowHeight, $klantTekst, 0, 'L');
$leftYend = $pdf->GetY();

// ✅ WERKADRES TITEL
$pdf->SetXY($rightX, $startY);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($colWidth, $rowHeight, 'Werkadres', 0, 1, 'L');

// ✅ WERKADRES INHOUD
$pdf->SetFont('helvetica', '', 10);
$werkadresTekst = "-";
if (!empty($werkbon['wa_naam'])) {
    $werkadresTekst  = ($werkbon['wa_naam'] ?? '-') . "\n";
    $werkadresTekst .= ($werkbon['wa_adres'] ?? '-') . "\n";
    $werkadresTekst .= trim(($werkbon['wa_postcode'] ?? '') . ' ' . ($werkbon['wa_plaats'] ?? ''));
}
$pdf->SetX($rightX);
$pdf->MultiCell($colWidth, $rowHeight, $werkadresTekst, 0, 'L');
$rightYend = $pdf->GetY();

// ✅ Cursor netjes onder het hoogste blok zetten
$pdf->SetY(max($leftYend, $rightYend) + 4);





// ================== 11. GEBRUIKTE ARTIKELEN ===================
if ($artikelen && $artikelen->num_rows > 0) {
    $rows = [];
    while ($row = $artikelen->fetch_assoc()) {
        $rows[] = $row;
    }

    if (!empty($rows)) {
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Gebruikte artikelen', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // 📐 Kolombreedtes
        $colAantal = 25;
        $colOmschrijving = 155; // 180 totaal breedte

        // Kolomtitels
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($colAantal, 6, 'Aantal', 0, 0, 'L', true);
        $pdf->Cell($colOmschrijving, 6, 'Omschrijving', 0, 1, 'L', true);

        foreach ($rows as $r) {
            $aantal = $r['aantal'] ?? '';
            $omschrijving = $r['omschrijving'] ?? '';

            $pdf->MultiCell($colAantal, 6, $aantal, 0, 'L', 0, 0);
            $pdf->MultiCell($colOmschrijving, 6, $omschrijving, 0, 'L', 0, 1);
        }
    }
}




// ================== 12. URENREGISTRATIE ===================
if ($urenregistraties && $urenregistraties->num_rows > 0) {
    $rows = [];
    while ($uur = $urenregistraties->fetch_assoc()) {
        $rows[] = $uur;
    }

    if (!empty($rows)) {
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Urenregistratie', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // 📐 Kolombreedtes
        $colDatum = 25;
        $colMonteur = 35;
        $colUursoort = 55;
        $colBegintijd = 20;
        $colEindtijd = 20;
        $colTotaal = 25;

        // Kolomtitels
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($colDatum, 6, 'Datum', 0, 0, 'L', true);
        $pdf->Cell($colMonteur, 6, 'Monteur', 0, 0, 'L', true);
        $pdf->Cell($colUursoort, 6, 'Uursoort', 0, 0, 'L', true);
        $pdf->Cell($colBegintijd, 6, 'Begintijd', 0, 0, 'L', true);
        $pdf->Cell($colEindtijd, 6, 'Eindtijd', 0, 0, 'L', true);
        $pdf->Cell($colTotaal, 6, 'Totaal uren', 0, 1, 'L', true);

        foreach ($rows as $uur) {
            $datum     = !empty($uur['datum']) ? date('d-m-Y', strtotime($uur['datum'])) : '';
            $monteur   = $uur['monteur_naam'] ?? '';
            $uursoort  = $uur['uursoort_naam'] ?? '';
            $begintijd = !empty($uur['begintijd']) ? substr($uur['begintijd'], 0, 5) : '';
            $eindtijd  = !empty($uur['eindtijd']) ? substr($uur['eindtijd'], 0, 5) : '';
            $totaal    = isset($uur['totaal_uren']) ? number_format($uur['totaal_uren'], 2, ',', '.') : '';

            $pdf->MultiCell($colDatum, 6, $datum, 0, 'L', 0, 0);
            $pdf->MultiCell($colMonteur, 6, $monteur, 0, 'L', 0, 0);
            $pdf->MultiCell($colUursoort, 6, $uursoort, 0, 'L', 0, 0);
            $pdf->MultiCell($colBegintijd, 6, $begintijd, 0, 'L', 0, 0);
            $pdf->MultiCell($colEindtijd, 6, $eindtijd, 0, 'L', 0, 0);
            $pdf->MultiCell($colTotaal, 6, $totaal, 0, 'L', 0, 1);

            // Opmerkingen onder de regel
            if (!empty($uur['opmerkingen'])) {
                $pdf->SetFont('helvetica', 'I', 9);
                $pdf->Cell(0, 5, 'Opmerking: ' . $uur['opmerkingen'], 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
            }
        }
    }
}



// ================== 13. OBJECTEN overzicht ===================
if ($objecten->num_rows > 0) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'Objectenoverzicht', 0, 1);
    $pdf->Ln(2);

    $tileWidth  = 90;
    $tileHeight = 30;
    $imgWidth   = 18;
    $imgHeight  = 20;
    $padding    = 3;

    $pageHeight = $pdf->getPageHeight();
    $bottomMargin = 20;
    $usableHeight = $pageHeight - $bottomMargin;

    $xStart = $pdf->GetX();
    $yStart = $pdf->GetY();
    $i = 0;

    while ($obj = $objecten->fetch_assoc()) {
        $col = $i % 2;
        $row = floor($i / 2);

        $x = $xStart + $col * ($tileWidth + 5);
        $y = $yStart + $row * ($tileHeight + 5);

        if ($y + $tileHeight > $usableHeight) {
            $pdf->AddPage();
            $yStart = $pdf->GetY();
            $row = 0;
            $y = $yStart;
            $x = $xStart;
            $i = 0;
        }

        $pdf->Rect($x, $y, $tileWidth, $tileHeight);

        // 📸 Afbeelding links
        $imgX = $x + $padding;
        $imgY = $y + ($tileHeight - $imgHeight) / 2;
        if (!empty($obj['afbeelding']) && file_exists(__DIR__ . '/' . $obj['afbeelding'])) {
            $pdf->Image(__DIR__ . '/' . $obj['afbeelding'], $imgX, $imgY, $imgWidth, $imgHeight);
        } else {
            $pdf->Rect($imgX, $imgY, $imgWidth, $imgHeight);
        }

        // Tekst
        $textX = $imgX + $imgWidth + $padding;
        $textY = $y + 4;
        $pdf->SetXY($textX, $textY);
        $pdf->SetFont('helvetica', '', 10);

$lines = [
    trim(($obj['code'] ?? '-') . ' - ' . ($obj['omschrijving'] ?? '-')),
    'Laatste onderhoud: ' . ($obj['datum_onderhoud'] ? date('d-m-Y', strtotime($obj['datum_onderhoud'])) : '-'),
    'Fabricagejaar: ' . ($obj['fabricagejaar'] ?? '-'),
    'Status: ' . ($obj['status_naam'] ?? '-'),
];

        foreach ($lines as $line) {
            $pdf->Cell($tileWidth - $imgWidth - $padding*3, 4, $line, 0, 1);
            $pdf->SetX($textX);
        }

        $i++;
    }
}

// ✅ 14. PDF opslaan én (optioneel) tonen

$bestandNaam = 'werkbon_' . $werkbon['werkbonnummer'] . '.pdf';

// Pad naar de archiefmap
$archiefMap = __DIR__ . '/pdf_archief/werkbonnen/';
if (!file_exists($archiefMap)) {
    mkdir($archiefMap, 0775, true);
}

// Volledig pad naar bestand
$bestandPad = $archiefMap . $bestandNaam;

// ✅ 1. Opslaan in archief
$pdf->Output($bestandPad, 'F');  // 'F' = File

// ✅ 2. Eventueel direct tonen in browser
$pdf->Output($bestandNaam, 'I'); // 'I' = Inline (weergave/download)
