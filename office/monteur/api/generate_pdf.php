<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

// TCPDF laden
require_once __DIR__ . '/../../vendor/autoload.php';

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    die("Ongeldig werkbon ID.");
}

// Werkbon ophalen + controle
$stmt = $conn->prepare("
    SELECT w.*, k.bedrijfsnaam, k.contactpersoon, k.straat, k.postcode, k.plaats
    FROM werkbonnen w
    LEFT JOIN klanten k ON k.klant_id = w.klant_id
    WHERE w.werkbon_id = ? AND w.monteur_id = ?
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();

if (!$werkbon) {
    die("Werkbon niet gevonden of geen toegang.");
}

// Objecten
$objecten = $conn->query("
    SELECT o.code, o.omschrijving
    FROM werkbon_objecten wo
    LEFT JOIN objecten o ON o.object_id = wo.object_id
    WHERE wo.werkbon_id = $werkbon_id
");

// Artikelen
$artikelen = $conn->query("
    SELECT wa.aantal, a.omschrijving
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON a.artikel_id = wa.artikel_id
    WHERE wa.werkbon_id = $werkbon_id
");

// Uren
$uren = $conn->query("
    SELECT u.*, us.code AS uurcode, us.omschrijving AS uur_omschrijving
    FROM werkbon_uren u
    LEFT JOIN uursoorten us ON us.uursoort_id = u.uursoort_id
    WHERE werkbon_id = $werkbon_id
    ORDER BY datum, begintijd
");

// Handtekening
$handtekening_path = "";
if (!empty($werkbon['handtekening_klant'])) {
    $handtekening_path = __DIR__ . "/../../uploads/handtekeningen/" . $werkbon['handtekening_klant'];
}



// ---------------------------------------------
//  PDF GENEREREN
// ---------------------------------------------
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator("ABCB Office");
$pdf->SetAuthor("ABCB");
$pdf->SetTitle("Werkbon #".$werkbon['werkbonnummer']);
$pdf->SetMargins(15, 30, 15);

// Header uitschakelen
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();


// ---------------------------------------------
//  ABCB KOPTEKST
// ---------------------------------------------
$logo = __DIR__ . "/../../template/ABCBFAV.png";

$html = '
<table cellpadding="2">
<tr>
    <td width="60"><img src="'.$logo.'" width="50"></td>
    <td>
        <h2>Werkbon '.$werkbon['werkbonnummer'].'</h2>
        <strong>Datum uitvoering:</strong> '.date("d-m-Y", strtotime($werkbon['uitvoerdatum'])).'<br>
    </td>
</tr>
</table>
<hr>
';

$pdf->writeHTML($html, true, false, false, false, '');


// ---------------------------------------------
//  KLANT INFO
// ---------------------------------------------
$html = '
<h3>Klantgegevens</h3>
<table cellpadding="4" border="1">
<tr><td><strong>Bedrijfsnaam:</strong></td><td>'.htmlspecialchars($werkbon['bedrijfsnaam']).'</td></tr>
<tr><td><strong>Contactpersoon:</strong></td><td>'.htmlspecialchars($werkbon['contactpersoon']).'</td></tr>
<tr><td><strong>Adres:</strong></td><td>'.
        htmlspecialchars($werkbon['straat']).', '.
        htmlspecialchars($werkbon['postcode']).' '.
        htmlspecialchars($werkbon['plaats']).'</td></tr>
</table>
<br>
';

$pdf->writeHTML($html, true, false, false, false, '');


// ---------------------------------------------
//  OBJECTEN
// ---------------------------------------------
$html = '<h3>Objecten</h3>';
$html .= '<table cellpadding="4" border="1"><tr><th width="40%">Code</th><th>Omschrijving</th></tr>';

if ($objecten->num_rows == 0) {
    $html .= '<tr><td colspan="2">Geen objecten gekoppeld</td></tr>';
} else {
    while ($o = $objecten->fetch_assoc()) {
        $html .= '<tr><td>'.$o['code'].'</td><td>'.htmlspecialchars($o['omschrijving']).'</td></tr>';
    }
}

$html .= "</table><br>";
$pdf->writeHTML($html, true, false, false, false, '');


// ---------------------------------------------
//  ARTIKELEN
// ---------------------------------------------
$html = '<h3>Artikelen</h3>';
$html .= '<table cellpadding="4" border="1"><tr><th width="20%">Aantal</th><th>Artikel</th></tr>';

if ($artikelen->num_rows == 0) {
    $html .= '<tr><td colspan="2">Geen artikelen gebruikt</td></tr>';
} else {
    while ($a = $artikelen->fetch_assoc()) {
        $html .= '<tr><td>'.$a['aantal'].'</td><td>'.htmlspecialchars($a['omschrijving']).'</td></tr>';
    }
}

$html .= "</table><br>";
$pdf->writeHTML($html, true, false, false, false, '');


// ---------------------------------------------
//  UREN
// ---------------------------------------------
$html = '<h3>Uren</h3>';
$html .= '<table cellpadding="4" border="1">
<tr>
<th width="20%">Datum</th>
<th width="20%">Tijd</th>
<th width="25%">Uursoort</th>
<th>Opmerking</th>
</tr>';

if ($uren->num_rows == 0) {
    $html .= '<tr><td colspan="4">Geen uren geregistreerd</td></tr>';
} else {
    while ($u = $uren->fetch_assoc()) {
        $html .= '<tr>
            <td>'.date("d-m-Y", strtotime($u['datum'])).'</td>
            <td>'.substr($u['begintijd'],0,5).' - '.substr($u['eindtijd'],0,5).'</td>
            <td>'.$u['uurcode'].' - '.htmlspecialchars($u['uur_omschrijving']).'</td>
            <td>'.nl2br(htmlspecialchars($u['opmerkingen'])).'</td>
        </tr>';
    }
}

$html .= "</table><br>";
$pdf->writeHTML($html, true, false, false, false, '');


// ---------------------------------------------
//  HANDTEKENING
// ---------------------------------------------
$html = "<h3>Handtekening klant</h3>";

if (!empty($handtekening_path) && file_exists($handtekening_path)) {
    $html .= "<p><img src=\"$handtekening_path\" width=\"200\"></p>";
} else {
    $html .= "<p>Geen handtekening aanwezig</p>";
}

$pdf->writeHTML($html, true, false, false, false, '');


// Output
$pdf->Output("werkbon_".$werkbon_id.".pdf", "I");
exit;
