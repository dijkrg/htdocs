<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/tcpdf/tcpdf.php';

// Alleen Admin/Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    exit('Geen toegang.');
}

// Bestelling-ID ophalen
$bestelling_id = intval($_GET['id'] ?? 0);
if ($bestelling_id <= 0) exit('Ongeldige bestelling.');

// Bedrijfsgegevens ophalen
$bedrijf = $conn->query("SELECT * FROM bedrijfsgegevens LIMIT 1")->fetch_assoc();
if (!$bedrijf) {
    $bedrijf = [
        'bedrijfsnaam' => 'ABC Brandbeveiliging',
        'adres' => 'Ambachtsweg 5R',
        'postcode' => '3953 BZ',
        'plaats' => 'Maarsbergen',
        'telefoon' => '085-3013175',
        'email' => 'info@abcbrandbeveiliging.nl',
        'kvk' => '61053155',
        'btw' => 'NL001903207B52',
        'iban' => 'NL10SNSB8839005668'
    ];
}

// Bestelling + leverancier ophalen
$sql = "
    SELECT 
        b.bestelnummer, b.bestel_datum, b.status,
        l.naam AS leverancier_naam,
        l.adres, l.postcode, l.plaats, l.email, l.telefoon,
        m.naam AS magazijn_naam
    FROM bestellingen b
    LEFT JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    LEFT JOIN magazijnen m ON m.magazijn_id = (
        SELECT vt.magazijn_id FROM voorraad_transacties vt 
        WHERE vt.opmerking LIKE CONCAT('%#', b.bestelling_id, '%')
        ORDER BY vt.datum DESC LIMIT 1
    )
    WHERE b.bestelling_id = $bestelling_id
";
$bestelling = $conn->query($sql)->fetch_assoc();
if (!$bestelling) exit('Bestelling niet gevonden.');

// Artikelen ophalen
$artikelen = $conn->query("
    SELECT 
        a.artikelnummer, a.omschrijving, 
        ba.aantal AS besteld, ba.aantal_ontvangen AS ontvangen
    FROM bestelling_artikelen ba
    JOIN artikelen a ON a.artikel_id = ba.artikel_id
    WHERE ba.bestelling_id = $bestelling_id
    ORDER BY a.artikelnummer ASC
");

// Maak nieuwe PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Logo
$logoPath = __DIR__ . '/../template/ABCBFAV.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 12, 10, 28); // vergroot logo
}
$pdf->SetFont('helvetica', '', 10);
$pdf->Ln(25);

// Titel
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Ontvangstbon', 0, 1, 'L');
$pdf->Ln(2);

// Leverancier & bedrijf
$pdf->SetFont('helvetica', '', 10);
$html = '
<table cellpadding="4">
<tr>
<td width="50%">
    <strong>Leverancier:</strong><br>'
    . htmlspecialchars($bestelling['leverancier_naam']) . '<br>'
    . htmlspecialchars($bestelling['adres'] ?? '') . '<br>'
    . htmlspecialchars(($bestelling['postcode'] ?? '') . ' ' . ($bestelling['plaats'] ?? '')) . '<br>'
    . htmlspecialchars($bestelling['telefoon'] ?? '') . '<br>'
    . htmlspecialchars($bestelling['email'] ?? '') . '
</td>
<td width="50%">
    <strong>' . htmlspecialchars($bedrijf['bedrijfsnaam']) . '</strong><br>'
    . htmlspecialchars($bedrijf['adres']) . '<br>'
    . htmlspecialchars($bedrijf['postcode'] . ' ' . $bedrijf['plaats']) . '<br>'
    . htmlspecialchars($bedrijf['telefoon']) . '<br>'
    . htmlspecialchars($bedrijf['email']) . '
</td>
</tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');

// Info
$pdf->Ln(2);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Bestelnummer: ' . $bestelling['bestelnummer'], 0, 1);
$pdf->Cell(0, 6, 'Datum bestelling: ' . date('d-m-Y', strtotime($bestelling['bestel_datum'])), 0, 1);
$pdf->Cell(0, 6, 'Magazijn: ' . ($bestelling['magazijn_naam'] ?? 'Onbekend'), 0, 1);
$pdf->Cell(0, 6, 'Status: ' . ucfirst($bestelling['status']), 0, 1);
$pdf->Ln(4);

// Artikelen
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 7, 'Artikelnummer', 1, 0, 'L');
$pdf->Cell(80, 7, 'Omschrijving', 1, 0, 'L');
$pdf->Cell(30, 7, 'Besteld', 1, 0, 'C');
$pdf->Cell(30, 7, 'Ontvangen', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$totalOntvangen = 0;
while ($r = $artikelen->fetch_assoc()) {
    $pdf->Cell(40, 7, htmlspecialchars($r['artikelnummer']), 1, 0, 'L');
    $pdf->Cell(80, 7, htmlspecialchars($r['omschrijving']), 1, 0, 'L');
    $pdf->Cell(30, 7, (int)$r['besteld'], 1, 0, 'C');
    $pdf->Cell(30, 7, (int)$r['ontvangen'], 1, 1, 'C');
    $totalOntvangen += (int)$r['ontvangen'];
}

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, "Totaal ontvangen: $totalOntvangen stuks", 0, 1, 'R');

// Footer
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(0, 6, 'Gegenereerd op ' . date('d-m-Y H:i') . ' door ' . ($_SESSION['user']['naam'] ?? 'Onbekend'), 0, 1, 'L');

// Output
$pdf->Output('ontvangstbon_' . $bestelling['bestelnummer'] . '.pdf', 'I');
