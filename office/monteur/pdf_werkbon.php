<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

// Voor PDF generatie: disable display_errors en gebruik output buffering
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/tcpdf/tcpdf.php';

$werkbon_id = (int)($_GET['id'] ?? 0);
$monteur_id = (int)($_SESSION['user']['id'] ?? 0);

if ($werkbon_id <= 0 || $monteur_id <= 0) {
    exit("Ongeldig ID.");
}

/* --------------------------------------------------
   Helpers
-------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
function dateNL(?string $date): string {
    if (!$date || $date === "0000-00-00") return "-";
    $ts = strtotime($date);
    return $ts ? date("d-m-Y", $ts) : (string)$date;
}
function hm(?string $time): string {
    if (!$time) return "-";
    return substr($time, 0, 5);
}
function safeFileName(string $s): string {
    $s = preg_replace('/[^\pL\pN\-_ ]+/u', '', $s) ?? $s;
    $s = trim(preg_replace('/\s+/', '_', $s) ?? $s);
    return $s !== '' ? $s : 'werkbon';
}

/* --------------------------------------------------
   Bedrijfsgegevens voor footer + logo
-------------------------------------------------- */
$bedrijfsnaam = 'ABCBrand Beveiliging B.V.';
$logoRel = '/template/logo_abc.png'; // Gebruik logo_abc.png i.p.v. ABCBFAV.png (die heeft alpha channel problemen)

$bg = $conn->query("SELECT bedrijfsnaam, logo_pad FROM bedrijfsgegevens LIMIT 1");
if ($bg && ($row = $bg->fetch_assoc())) {
    if (!empty($row['bedrijfsnaam'])) $bedrijfsnaam = (string)$row['bedrijfsnaam'];
    // Gebruik alleen logo_pad uit database als het NIET ABCBFAV.png is (alpha channel problemen)
    if (!empty($row['logo_pad']) && strpos($row['logo_pad'], 'ABCBFAV.png') === false) {
        $logoRel = (string)$row['logo_pad'];
    }
}
// logo_pad kan beginnen met /template/... -> omzetten naar filesystem pad
$logoPath = realpath(__DIR__ . '/..' . $logoRel) ?: '';

/* --------------------------------------------------
   1) Werkbon + klant + werkadres + monteur naam
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT w.*,
           k.debiteurnummer,
           k.bedrijfsnaam AS klantnaam,
           k.adres AS klant_adres,
           k.postcode AS klant_postcode,
           k.plaats AS klant_plaats,

           wa.bedrijfsnaam AS wa_naam,
           wa.adres AS wa_adres,
           wa.postcode AS wa_postcode,
           wa.plaats AS wa_plaats,

           m.voornaam AS monteur_voornaam,
           m.achternaam AS monteur_achternaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON k.klant_id = w.klant_id
    LEFT JOIN werkadressen wa ON wa.werkadres_id = w.werkadres_id
    LEFT JOIN medewerkers m ON m.medewerker_id = w.monteur_id
    WHERE w.werkbon_id = ?
      AND w.monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb) {
    exit("Geen toegang.");
}

/* --------------------------------------------------
   2) Artikelen (ZONDER prijzen/totaal)
-------------------------------------------------- */
$q_art = $conn->prepare("
    SELECT a.artikelnummer, a.omschrijving, wa.aantal
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON a.artikel_id = wa.artikel_id
    WHERE wa.werkbon_id = ?
    ORDER BY a.omschrijving ASC
");
$q_art->bind_param("i", $werkbon_id);
$q_art->execute();
$artikelen = $q_art->get_result();
$q_art->close();

/* --------------------------------------------------
   3) Uren uit werkbon_uren + uursoorten (ZONDER totaal-kolom in output)
-------------------------------------------------- */
$q_uren = $conn->prepare("
    SELECT
        u.werkbon_uur_id,
        u.datum,
        u.begintijd,
        u.eindtijd,
        u.totaal_uren,
        u.opmerkingen,
        u.goedgekeurd,
        us.code AS uur_code,
        us.omschrijving AS uur_omschrijving
    FROM werkbon_uren u
    LEFT JOIN uursoorten us ON us.uursoort_id = u.uursoort_id
    WHERE u.werkbon_id = ?
      AND u.monteur_id = ?
    ORDER BY u.datum ASC, u.begintijd ASC
");
$q_uren->bind_param("ii", $werkbon_id, $monteur_id);
$q_uren->execute();
$uren = $q_uren->get_result();
$q_uren->close();

/* --------------------------------------------------
   4) Objecten op locatie (zonder foto)
-------------------------------------------------- */
$sql_obj = "
    SELECT
        o.code,
        o.omschrijving,
        o.fabricagejaar,
        o.rijkstypekeur,
        o.beproeving_nen671_3,
        o.datum_onderhoud
    FROM objecten o
    WHERE o.klant_id = ?
      AND o.verwijderd = 0
";
$params = [$wb['klant_id']];
$types  = "i";

if (!empty($wb['werkadres_id'])) {
    $sql_obj .= " AND o.werkadres_id = ? ";
    $params[] = $wb['werkadres_id'];
    $types   .= "i";
} else {
    $sql_obj .= " AND (o.werkadres_id IS NULL OR o.werkadres_id = 0) ";
}
$sql_obj .= " ORDER BY o.code+0 ASC, o.code ASC";

$q = $conn->prepare($sql_obj);
$q->bind_param($types, ...$params);
$q->execute();
$objecten = $q->get_result();
$q->close();

/* --------------------------------------------------
   TCPDF: eigen footer, GEEN header (verwijdert die loze horizontale lijn)
-------------------------------------------------- */
class MyPDF extends TCPDF {
    public string $footerBedrijf = '';
    public function Header(): void {
        // bewust leeg -> geen header lijn bovenaan
    }
    public function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 8);
        $left = $this->footerBedrijf;
        $right = 'Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages();
        $this->Cell(0, 6, $left, 0, 0, 'L');
        $this->Cell(0, 6, $right, 0, 0, 'R');
    }
}

$pdf = new MyPDF('P','mm','A4', true, 'UTF-8', false);
$pdf->footerBedrijf = $bedrijfsnaam;

// Belangrijk: TCPDF header uit (anders blijft er vaak een lijn komen)
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$pdf->SetCreator('ABCB');
$pdf->SetAuthor($bedrijfsnaam);
$pdf->SetTitle('Werkbon ' . (string)($wb['werkbonnummer'] ?? $werkbon_id));

$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$left   = 12;
$right  = 12;
$pageW  = 210;
$usableW = $pageW - $left - $right;

/* --------------------------------------------------
   Stijl helpers
-------------------------------------------------- */
$lineColor = [60,60,60];
$pdf->SetDrawColor($lineColor[0], $lineColor[1], $lineColor[2]);

$sep = function() use ($pdf, $left, $usableW) {
    $y = $pdf->GetY();
    $pdf->Line($left, $y, $left + $usableW, $y);
    $pdf->Ln(4);
};

$hLabel = function(string $t) use ($pdf) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, $t, 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
};

$kvRow = function(string $k, string $v) use ($pdf, $usableW) {
    $kW = 40;
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($kW, 6, $k, 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell($usableW - $kW, 6, $v, 0, 'L', false, 1);
};

/* --------------------------------------------------
   Header: logo (groot) - geen lijn erboven/erdoor
-------------------------------------------------- */
if ($logoPath && is_file($logoPath)) {
    try {
        // groter logo (pas breedte aan naar wens)
        $pdf->Image($logoPath, $left, 10, 85, 0, '', '', '', false, 300);
        $pdf->Ln(28); // ruimte onder logo
    } catch (Exception $e) {
        // Als logo niet geladen kan worden (bijv. PNG met alpha channel zonder GD/Imagick)
        // Laat gewoon ruimte en ga verder
        $pdf->Ln(8);
    }
} else {
    $pdf->Ln(8);
}

/* --------------------------------------------------
   Werkbongegevens
-------------------------------------------------- */
$sep();
$hLabel('Werkbongegevens');

$werkbonnummer = (string)($wb['werkbonnummer'] ?? $werkbon_id);
$status = (string)($wb['status'] ?? '-');
$uitvoerdatum = dateNL((string)($wb['uitvoerdatum'] ?? ''));
$tijd = hm((string)($wb['starttijd'] ?? '')) . ' – ' . hm((string)($wb['eindtijd'] ?? ''));

$werkperiode = (string)($wb['werkperiode'] ?? '');
if ($werkperiode === '') {
    if (!empty($wb['werkbon_start']) || !empty($wb['werkbon_eind'])) {
        $ws = !empty($wb['werkbon_start']) ? date('d-m-Y H:i', strtotime((string)$wb['werkbon_start'])) : '-';
        $we = !empty($wb['werkbon_eind'])  ? date('d-m-Y H:i', strtotime((string)$wb['werkbon_eind']))  : '-';
        $werkperiode = $ws . ' t/m ' . $we;
    } else {
        $werkperiode = '-';
    }
}

$kvRow('Werkbon:', $werkbonnummer);
$kvRow('Status:', $status);
$kvRow('Uitvoerdatum:', $uitvoerdatum);
$kvRow('Tijd:', $tijd);
$kvRow('Werkperiode:', $werkperiode);

$pdf->Ln(2);
$sep();

/* --------------------------------------------------
   Omschrijving
-------------------------------------------------- */
$hLabel('Omschrijving');
$pdf->SetFont('helvetica','',10);
$omschrijving = trim((string)($wb['omschrijving'] ?? ''));
if ($omschrijving === '') $omschrijving = '-';
$pdf->MultiCell(0, 6, $omschrijving, 0, 'L');
$pdf->Ln(2);
$sep();

/* --------------------------------------------------
   Klantgegevens + Werkadres naast elkaar (extra-veilig tegen overlap)
-------------------------------------------------- */
$gap  = 10;
$colW = ($usableW - $gap) / 2;

// Zorg dat we echt vanaf 1 y-positie starten
$startY = $pdf->GetY();

$klantBlok =
    trim((string)($wb['debiteurnummer'] ?? '') . ' - ' . (string)($wb['klantnaam'] ?? '')) . "\n" .
    (string)($wb['klant_adres'] ?? '-') . "\n" .
    trim((string)($wb['klant_postcode'] ?? '') . ' ' . (string)($wb['klant_plaats'] ?? ''));

$werkadresBlok = '-';
if (!empty($wb['wa_naam'])) {
    $werkadresBlok =
        (string)$wb['wa_naam'] . "\n" .
        (string)($wb['wa_adres'] ?? '-') . "\n" .
        trim((string)($wb['wa_postcode'] ?? '') . ' ' . (string)($wb['wa_plaats'] ?? ''));
}

// meet teksthoogtes (zelfde font als output!)
$pdf->SetFont('helvetica','',10);
$hKlant = $pdf->getStringHeight($colW, $klantBlok);
$hWerk  = $pdf->getStringHeight($colW, $werkadresBlok);

// + titelhoogte (7) + kleine marge (6)
$blockH = max($hKlant, $hWerk) + 7 + 6;

// LINKS
$pdf->SetXY($left, $startY);
$pdf->SetFont('helvetica','B',12);
$pdf->Cell($colW, 7, 'Klantgegevens', 0, 1, 'L');
$pdf->SetFont('helvetica','',10);
$pdf->MultiCell($colW, 5.5, $klantBlok, 0, 'L', false, 1);

// RECHTS (exact dezelfde startY, eigen kolom)
$pdf->SetXY($left + $colW + $gap, $startY);
$pdf->SetFont('helvetica','B',12);
$pdf->Cell($colW, 7, 'Werkadres', 0, 1, 'L');
$pdf->SetFont('helvetica','',10);
$pdf->MultiCell($colW, 5.5, $werkadresBlok, 0, 'L', false, 1);

// zet Y altijd onder beide blokken (nooit terugvallen)
$pdf->SetY($startY + $blockH);
$sep();

/* --------------------------------------------------
   Gebruikte artikelen (zonder kaders, ZONDER prijzen/totaal)
-------------------------------------------------- */
$hLabel('Gebruikte artikelen');

if (!$artikelen || $artikelen->num_rows === 0) {
    $pdf->Cell(0, 6, 'Geen artikelen gebruikt.', 0, 1);
    $pdf->Ln(2);
} else {
    // kolommen
    $wNr   = 40;
    $wOms  = 130;
    $wAant = $usableW - ($wNr + $wOms);

    // header
    $pdf->SetFont('helvetica','B',10);
    $pdf->Cell($wNr, 7, 'Artikelnummer', 0, 0);
    $pdf->Cell($wOms,7, 'Omschrijving',  0, 0);
    $pdf->Cell($wAant,7,'Aantal',        0, 1, 'R');

    // lijn onder header
    $pdf->Line($left, $pdf->GetY(), $left + $usableW, $pdf->GetY());

    $pdf->SetFont('helvetica','',10);
    while ($a = $artikelen->fetch_assoc()) {
        $aantal = (float)($a['aantal'] ?? 0);

        $pdf->Cell($wNr, 7, (string)($a['artikelnummer'] ?? ''), 0, 0);
        $pdf->Cell($wOms,7, (string)($a['omschrijving'] ?? ''),  0, 0);
        $pdf->Cell($wAant,7, number_format($aantal, 2, ',', '.'), 0, 1, 'R');

        // scheidingslijn per rij
        $pdf->Line($left, $pdf->GetY(), $left + $usableW, $pdf->GetY());
    }
    $pdf->Ln(2);
}

$sep();

/* --------------------------------------------------
   Gewerkte uren (zonder kaders, ZONDER totaal-kolom + ZONDER totaalregel)
-------------------------------------------------- */
$hLabel('Gewerkte uren');

if (!$uren || $uren->num_rows === 0) {
    $pdf->Cell(0, 6, 'Geen uren geregistreerd.', 0, 1);
    $pdf->Ln(2);
} else {
    $wDat = 25;
    $wSo  = 80;
    $wSt  = 25;
    $wEi  = 25;
    $wOp  = $usableW - ($wDat + $wSo + $wSt + $wEi);

    // header
    $pdf->SetFont('helvetica','B',10);
    $pdf->Cell($wDat, 7, 'Datum', 0, 0);
    $pdf->Cell($wSo,  7, 'Uursoort', 0, 0);
    $pdf->Cell($wSt,  7, 'Start', 0, 0);
    $pdf->Cell($wEi,  7, 'Einde', 0, 0);
    $pdf->Cell($wOp,  7, 'Opmerkingen', 0, 1);

    $pdf->Line($left, $pdf->GetY(), $left + $usableW, $pdf->GetY());

    $pdf->SetFont('helvetica','',10);

    while ($u = $uren->fetch_assoc()) {
        $datum = dateNL((string)($u['datum'] ?? ''));
        $start = hm((string)($u['begintijd'] ?? ''));
        $einde = hm((string)($u['eindtijd'] ?? ''));

        $soort = trim((string)($u['uur_code'] ?? '') . ' - ' . (string)($u['uur_omschrijving'] ?? ''));
        if ($soort === '' || $soort === '-') $soort = '-';

        $opm = trim((string)($u['opmerkingen'] ?? ''));

        $pdf->Cell($wDat, 7, $datum, 0, 0);
        $pdf->Cell($wSo,  7, $soort, 0, 0);
        $pdf->Cell($wSt,  7, $start, 0, 0);
        $pdf->Cell($wEi,  7, $einde, 0, 0);
        $pdf->Cell($wOp,  7, $opm, 0, 1);

        $pdf->Line($left, $pdf->GetY(), $left + $usableW, $pdf->GetY());
    }
}

$pdf->Ln(3);
$sep();

/* --------------------------------------------------
   Handtekeningen (netjes, zonder overlap)
-------------------------------------------------- */
$hLabel('Handtekeningen');

$signY = $pdf->GetY();
$colW2 = ($usableW - $gap) / 2;

// links: klant
$pdf->SetXY($left, $signY);
$pdf->SetFont('helvetica','B',10);
$pdf->Cell($colW2, 6, 'Klant', 0, 1);
$pdf->SetFont('helvetica','',10);

$klantSignRel = (string)($wb['handtekening_klant'] ?? '');
$klantSignPath = '';
if ($klantSignRel !== '') {
    $try = realpath(__DIR__ . '/../' . ltrim($klantSignRel, '/'));
    if ($try !== false && is_file($try)) $klantSignPath = $try;
}

if ($klantSignPath) {
    $x = $left;
    $y = $pdf->GetY() + 2;
    $pdf->Line($x, $y + 22, $x + $colW2, $y + 22);
    try {
        $pdf->Image($klantSignPath, $x, $y, 60, 0);
        $pdf->SetY($y + 26);
    } catch (Exception $e) {
        // Als handtekening niet geladen kan worden
        $pdf->Cell($colW2, 16, '(handtekening kon niet worden geladen)', 0, 1);
        $pdf->SetY($y + 26);
    }
} else {
    $pdf->Cell($colW2, 16, '(geen handtekening)', 0, 1);
    $x = $left;
    $y = $pdf->GetY();
    $pdf->Line($x, $y + 10, $x + $colW2, $y + 10);
    $pdf->Ln(14);
}

// rechts: monteur
$monteurNaam = trim((string)($wb['monteur_voornaam'] ?? '') . ' ' . (string)($wb['monteur_achternaam'] ?? ''));
if ($monteurNaam === '') $monteurNaam = 'Monteur';

$pdf->SetXY($left + $colW2 + $gap, $signY);
$pdf->SetFont('helvetica','B',10);
$pdf->Cell($colW2, 6, 'Monteur', 0, 1);
$pdf->SetFont('helvetica','',10);
$pdf->Cell($colW2, 6, $monteurNaam, 0, 1);

$x = $left + $colW2 + $gap;
$y = $pdf->GetY() + 10;
$pdf->Line($x, $y, $x + $colW2, $y);
$pdf->SetY(max($pdf->GetY(), $signY + 40));

/* --------------------------------------------------
   LANDSCAPE: Objecten op locatie (zonder foto, met kolomscheidingen)
-------------------------------------------------- */
$pdf->AddPage('L');

$leftL = 12;
$rightL = 12;
$pageWL = 297;
$usableWL = $pageWL - $leftL - $rightL;

$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0, 10, "Objecten op locatie", 0, 1, 'L');
$pdf->SetFont('helvetica','',10);

$pdf->SetDrawColor($lineColor[0], $lineColor[1], $lineColor[2]);

if (!$objecten || $objecten->num_rows === 0) {
    $pdf->Cell(0, 6, "Geen objecten aanwezig.", 0, 1);
} else {
    $wCode = 25;
    $wOms  = 105;
    $wFab  = 30;
    $wRk   = 35;
    $wBep  = 35;
    $wOnd  = $usableWL - ($wCode + $wOms + $wFab + $wRk + $wBep);

    // header
    $y0 = $pdf->GetY();
    $pdf->SetFont('helvetica','B',10);
    $pdf->Cell($wCode, 8, 'Code', 0, 0);
    $pdf->Cell($wOms,  8, 'Omschrijving', 0, 0);
    $pdf->Cell($wFab,  8, 'Fabricagejaar', 0, 0);
    $pdf->Cell($wRk,   8, 'Rijkstypekeur', 0, 0);
    $pdf->Cell($wBep,  8, 'Jaar beproeving', 0, 0);
    $pdf->Cell($wOnd,  8, 'Laatste onderhoud', 0, 1);

    $pdf->Line($leftL, $pdf->GetY(), $leftL + $usableWL, $pdf->GetY());

    // verticale kolomscheidingen
    $colXs = [
        $leftL + $wCode,
        $leftL + $wCode + $wOms,
        $leftL + $wCode + $wOms + $wFab,
        $leftL + $wCode + $wOms + $wFab + $wRk,
        $leftL + $wCode + $wOms + $wFab + $wRk + $wBep,
    ];

    // header-verticals
    $headerTop = $y0;
    $headerBot = $y0 + 8;
    foreach ($colXs as $xLine) {
        $pdf->Line($xLine, $headerTop, $xLine, $headerBot);
    }

    $pdf->SetFont('helvetica','',10);
    while ($o = $objecten->fetch_assoc()) {
        $yRow = $pdf->GetY();
        $hRow = 7;

        // page-break check
        if ($yRow + 10 > ($pdf->getPageHeight() - 16)) {
            $pdf->AddPage('L');
            $y0 = $pdf->GetY();

            $pdf->SetFont('helvetica','B',10);
            $pdf->Cell($wCode, 8, 'Code', 0, 0);
            $pdf->Cell($wOms,  8, 'Omschrijving', 0, 0);
            $pdf->Cell($wFab,  8, 'Fabricagejaar', 0, 0);
            $pdf->Cell($wRk,   8, 'Rijkstypekeur', 0, 0);
            $pdf->Cell($wBep,  8, 'Jaar beproeving', 0, 0);
            $pdf->Cell($wOnd,  8, 'Laatste onderhoud', 0, 1);

            $pdf->Line($leftL, $pdf->GetY(), $leftL + $usableWL, $pdf->GetY());

            $headerTop = $y0;
            $headerBot = $y0 + 8;
            foreach ($colXs as $xLine) {
                $pdf->Line($xLine, $headerTop, $xLine, $headerBot);
            }

            $pdf->SetFont('helvetica','',10);
        }

        $code = (string)($o['code'] ?? '');
        $oms  = (string)($o['omschrijving'] ?? '');
        $fab  = (string)($o['fabricagejaar'] ?? '-');
        $rk   = (string)($o['rijkstypekeur'] ?? '-');
        $bep  = (string)($o['beproeving_nen671_3'] ?? '-');
        $ond  = !empty($o['datum_onderhoud']) ? dateNL((string)$o['datum_onderhoud']) : '-';

        $pdf->Cell($wCode, $hRow, $code, 0, 0);
        $pdf->Cell($wOms,  $hRow, $oms,  0, 0);
        $pdf->Cell($wFab,  $hRow, $fab,  0, 0);
        $pdf->Cell($wRk,   $hRow, $rk,   0, 0);
        $pdf->Cell($wBep,  $hRow, $bep,  0, 0);
        $pdf->Cell($wOnd,  $hRow, $ond,  0, 1);

        $pdf->Line($leftL, $pdf->GetY(), $leftL + $usableWL, $pdf->GetY());

        // verticale scheidingen per rij
        $yTop = $yRow;
        $yBot = $yRow + $hRow;
        foreach ($colXs as $xLine) {
            $pdf->Line($xLine, $yTop, $xLine, $yBot);
        }
    }
}

$filename = 'werkbon_' . safeFileName((string)$werkbonnummer) . '.pdf';
$pdf->Output($filename, "I");
