<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/tcpdf/tcpdf.php';

// Alleen Admin/Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    exit('Geen toegang.');
}

// 🧾 Bedrijfsgegevens ophalen
$bedrijf = $conn->query("SELECT * FROM bedrijfsgegevens LIMIT 1")->fetch_assoc();
if (!$bedrijf) {
    $bedrijf = [
        'bedrijfsnaam' => 'ABC Brandbeveiliging',
        'adres' => 'Ambachtsweg 5R',
        'postcode' => '3953 BZ',
        'plaats' => 'Maarsbergen',
        'telefoon' => '085-3013175'
    ];
}

// 🧍 Gebruiker bepalen
$gebruiker = 'Systeembeheer';
if (isset($_SESSION['user']['voornaam']) || isset($_SESSION['user']['achternaam'])) {
    $gebruiker = trim(($_SESSION['user']['voornaam'] ?? '') . ' ' . ($_SESSION['user']['achternaam'] ?? ''));
} elseif (isset($_SESSION['user']['naam'])) {
    $gebruiker = $_SESSION['user']['naam'];
}

// 📦 Artikelen onder minimale voorraad ophalen
$sql = "
SELECT 
    a.artikel_id,
    a.artikelnummer,
    a.omschrijving,
    a.minimale_voorraad,
    COALESCE((
        SELECT SUM(vm.aantal)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ), 0) AS huidige_voorraad
FROM artikelen a
WHERE a.minimale_voorraad IS NOT NULL 
  AND a.minimale_voorraad > 0
  AND COALESCE((
        SELECT SUM(vm.aantal)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ), 0) <= a.minimale_voorraad
ORDER BY a.artikelnummer ASC
";
$result = $conn->query($sql);

// 📄 PDF setup
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetPrintHeader(false);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

// 🔹 Logo
$customLogo = !empty($bedrijf['logo']) ? __DIR__ . '/../uploads/logo/' . $bedrijf['logo'] : null;
$defaultLogo = __DIR__ . '/../template/ABCBFAV.png';
$logoPath = (file_exists($customLogo ?? '') ? $customLogo : (file_exists($defaultLogo) ? $defaultLogo : null));

if ($logoPath) {
    $pdf->Image($logoPath, 12, 12, 90);
}
$pdf->Ln(35);

// 🔹 Bedrijf + datum
$pdf->SetFont('dejavusans', '', 10);
$html = '
<table cellpadding="4">
<tr>
<td width="60%">' . htmlspecialchars($bedrijf['bedrijfsnaam']) . '<br>'
    . htmlspecialchars($bedrijf['adres']) . '<br>'
    . htmlspecialchars($bedrijf['postcode'] . ' ' . $bedrijf['plaats']) . '<br>'
    . htmlspecialchars($bedrijf['telefoon']) . '
</td>

<td width="40%" align="right">
    <strong>Datum:</strong> ' . date('d-m-Y') . '
</td>
</tr>
</table>
';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln(3);

// 🔹 Titel
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->Cell(0, 10, 'Te bestellen artikel(en)', 0, 1, 'L');
$pdf->Ln(3);

// 🔹 Tabelkop (aangepaste kolombreedtes)
$pdf->SetFont('dejavusans', 'B', 8);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(25, 8, 'Art.nr.', 1, 0, 'C', 1);
$pdf->Cell(65, 8, 'Omschrijving', 1, 0, 'L', 1);
$pdf->Cell(25, 8, 'Voorraad', 1, 0, 'C', 1);
$pdf->Cell(25, 8, 'Min. voorraad', 1, 0, 'C', 1);
$pdf->Cell(40, 8, 'Leverancier(s)', 1, 0, 'L', 1);
$pdf->Cell(10, 8, '✔', 1, 1, 'C', 1); // controlekolom

$pdf->SetFont('dejavusans', '', 9);

// 🔹 Inhoud
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Leveranciers ophalen
        $leveranciersRes = $conn->prepare("
            SELECT l.naam 
            FROM artikel_leveranciers al
            JOIN leveranciers l ON l.leverancier_id = al.leverancier_id
            WHERE al.artikel_id = ?
        ");
        $leveranciersRes->bind_param("i", $row['artikel_id']);
        $leveranciersRes->execute();
        $levResult = $leveranciersRes->get_result();

        $leveranciers = [];
        while ($lev = $levResult->fetch_assoc()) {
            $leveranciers[] = $lev['naam'];
        }
        $leveranciersText = !empty($leveranciers) ? implode(', ', $leveranciers) : '-';

        $voorraad = (int)$row['huidige_voorraad'];
        $min = (int)$row['minimale_voorraad'];

        // Rijen
        $pdf->Cell(25, 7, htmlspecialchars($row['artikelnummer']), 1, 0, 'C');
        $pdf->Cell(65, 7, htmlspecialchars(substr($row['omschrijving'], 0, 40)), 1, 0, 'L');
        $pdf->Cell(25, 7, $voorraad, 1, 0, 'C');
        $pdf->Cell(25, 7, $min, 1, 0, 'C');
        $pdf->Cell(40, 7, htmlspecialchars($leveranciersText), 1, 0, 'L');
        $pdf->Cell(10, 7, '', 1, 1, 'C');
    }
} else {
    $pdf->Cell(190, 10, 'Geen artikelen onder minimale voorraad 🎉', 1, 1, 'C');
}

// 🔹 Footer
$pdf->Ln(8);
$pdf->SetFont('dejavusans', 'I', 9);
$pdf->Cell(0, 6, 'Gegenereerd op ' . date('d-m-Y') . ' door ' . htmlspecialchars($gebruiker), 0, 1, 'L');

// Output
$pdf->Output('te_bestellen_artikelen.pdf', 'I');
