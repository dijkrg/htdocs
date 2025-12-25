<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/tcpdf/tcpdf.php';

// Alleen Admin/Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    exit('Geen toegang.');
}

$bestelling_id = intval($_GET['id'] ?? 0);
if ($bestelling_id <= 0) exit('Geen geldig bestel-ID.');

// 📥 Bedrijfsgegevens ophalen
$bedrijf = $conn->query("SELECT * FROM bedrijfsgegevens LIMIT 1")->fetch_assoc();
if (!$bedrijf) exit("Geen bedrijfsgegevens gevonden.");

// 📥 PDF-instellingen ophalen
$pdfset = $conn->query("SELECT * FROM pdf_instellingen LIMIT 1")->fetch_assoc();
if (!$pdfset) {
    $pdfset = [
        'marge_links' => 15,
        'marge_boven' => 20,
        'marge_rechts' => 15,
        'marge_onder' => 15,
        'kleur_accent' => '#c00000',
        'lettertype' => 'helvetica',
        'lettergrootte' => 10
    ];
}

// 📥 Bestelling ophalen
$stmt = $conn->prepare("
    SELECT 
        b.bestelling_id, b.bestelnummer, b.besteldatum, b.status, b.opmerking,
        l.naam AS leverancier_naam, l.adres, l.postcode, l.plaats, l.land
    FROM bestellingen b
    JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    WHERE b.bestelling_id = ?
");
$stmt->bind_param("i", $bestelling_id);
$stmt->execute();
$bestelling = $stmt->get_result()->fetch_assoc();
if (!$bestelling) exit("Bestelling niet gevonden.");

// 📦 Artikelen ophalen
$artikelen = $conn->query("
    SELECT a.artikelnummer, a.omschrijving, ba.aantal, ba.inkoopprijs
    FROM bestelling_artikelen ba
    JOIN artikelen a ON ba.artikel_id = a.artikel_id
    WHERE ba.bestelling_id = {$bestelling_id}
");

// 🧾 TCPDF-configuratie
$pdf = new TCPDF();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('ABCBrand Office');
$pdf->SetAuthor($bedrijf['bedrijfsnaam']);
$pdf->SetTitle('Bestelling ' . $bestelling['bestelnummer']);
$pdf->SetMargins($pdfset['marge_links'], $pdfset['marge_boven'], $pdfset['marge_rechts']);
$pdf->SetAutoPageBreak(TRUE, $pdfset['marge_onder']);
$pdf->SetFont($pdfset['lettertype'], '', $pdfset['lettergrootte']);
$pdf->AddPage();

// 🎨 Kleuraccent
$kleur = sscanf($pdfset['kleur_accent'], "#%02x%02x%02x");

// 🏢 Header met logo + bedrijfsgegevens
$logo_path = __DIR__ . '/..' . $bedrijf['logo_pad'];
if (!file_exists($logo_path)) {
    $logo_path = __DIR__ . '/../template/ABCBFAV.png';
}

// 🔹 Logo linksboven
$pdf->Image($logo_path, 15, 10, 90);

// 🔹 Bedrijfsgegevens rechtsboven
$pdf->SetXY(120, 10);
$pdf->SetFont('', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(80, 5,
    $bedrijf['bedrijfsnaam'] . "\n" .
    $bedrijf['adres'] . "\n" .
    $bedrijf['postcode'] . " " . $bedrijf['plaats'] . "\n" .
    $bedrijf['telefoon'] . "\n" .
    $bedrijf['email'] . "\n\n" .
    "KvK: " . $bedrijf['kvk'] . "\n" .
    "BTW: " . $bedrijf['btw_nummer'] . "\n" .
    "IBAN: " . $bedrijf['iban'],
0, 'R');
$pdf->Ln(45);

// 📄 Titel
$pdf->SetFont('', 'B', 16);
$pdf->SetTextColor($kleur[0], $kleur[1], $kleur[2]);
$pdf->Cell(0, 10, "BESTELBON " . $bestelling['bestelnummer'], 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(4);

// 📅 Datum + status
$pdf->SetFont('', '', 11);
$pdf->Cell(100, 6, "Datum bestelling: " . date('d-m-Y', strtotime($bestelling['besteldatum'])));
$pdf->Cell(0, 6, "Status: " . ucfirst($bestelling['status']), 0, 1);
$pdf->Ln(5);

// 🧾 Leveranciergegevens
$pdf->SetFont('', 'B', 12);
$pdf->SetFillColor($kleur[0], $kleur[1], $kleur[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, "Leverancier", 0, 1, 'L', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('', '', 10);
$adres = trim($bestelling['adres'] . "\n" .
              $bestelling['postcode'] . " " . $bestelling['plaats'] .
              (!empty($bestelling['land']) ? "\n" . $bestelling['land'] : ''));
$pdf->MultiCell(0, 5,
    $bestelling['leverancier_naam'] . "\n" . $adres,
0, 'L');
$pdf->Ln(10);

// 📦 Artikelen
$pdf->SetFont('', 'B', 11);
$pdf->SetFillColor($kleur[0], $kleur[1], $kleur[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(50, 8, 'Artikelnummer', 1, 0, 'L', 1);
$pdf->Cell(80, 8, 'Omschrijving', 1, 0, 'L', 1);
$pdf->Cell(25, 8, 'Aantal', 1, 0, 'R', 1);
$pdf->Cell(30, 8, 'Prijs (€)', 1, 1, 'R', 1);

$pdf->SetFont('', '', 10);
$pdf->SetTextColor(0, 0, 0);

$totaal = 0;
while ($r = $artikelen->fetch_assoc()) {
    $pdf->Cell(50, 7, $r['artikelnummer'], 1);
    $pdf->Cell(80, 7, $r['omschrijving'], 1);
    $pdf->Cell(25, 7, $r['aantal'], 1, 0, 'R');
    $pdf->Cell(30, 7, number_format($r['inkoopprijs'], 2, ',', '.'), 1, 1, 'R');
    $totaal += $r['aantal'] * $r['inkoopprijs'];
}

$pdf->SetFont('', 'B');
$pdf->Cell(155, 8, "Totaal", 1);
$pdf->Cell(30, 8, "€ " . number_format($totaal, 2, ',', '.'), 1, 1, 'R');

// 📋 Opmerking
if (!empty($bestelling['opmerking'])) {
    $pdf->Ln(8);
    $pdf->SetFont('', 'B');
    $pdf->Cell(0, 8, "Opmerking:", 0, 1);
    $pdf->SetFont('', '');
    $pdf->MultiCell(0, 6, $bestelling['opmerking']);
}

// 🧾 Footer
if (!empty($bedrijf['pdf_footer'])) {
    $pdf->Ln(10);
    $pdf->SetFont('', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 5, $bedrijf['pdf_footer'], 0, 'C');
}

// ✅ Output PDF
ob_end_clean();
$pdf->Output('Bestelling_' . $bestelling['bestelnummer'] . '.pdf', 'I');
