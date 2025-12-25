<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json; charset=utf-8');

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);

$werkbon_id = (int)($_POST['werkbon_id'] ?? 0);
$object_id  = (int)($_POST['object_id'] ?? 0);

$omschrijving   = trim((string)($_POST['omschrijving'] ?? ''));
$merk           = trim((string)($_POST['merk'] ?? ''));
$type           = trim((string)($_POST['type'] ?? ''));
$rijkstypekeur  = trim((string)($_POST['rijkstypekeur'] ?? ''));
$locatie        = trim((string)($_POST['locatie'] ?? ''));
$verdieping     = trim((string)($_POST['verdieping'] ?? ''));

// ✅ nieuwe velden
$datum_installatie = trim((string)($_POST['datum_installatie'] ?? '')); // yyyy-mm-dd of leeg
$datum_onderhoud   = trim((string)($_POST['datum_onderhoud'] ?? ''));   // yyyy-mm-dd of leeg
$fabricagejaar_raw = trim((string)($_POST['fabricagejaar'] ?? ''));     // yyyy of leeg
$beproeving_raw    = trim((string)($_POST['beproeving_nen671_3'] ?? ''));// yyyy of leeg

// (blijven ondersteund; niet verplicht)
$resultaat    = trim((string)($_POST['resultaat'] ?? ''));
$opmerkingen  = trim((string)($_POST['opmerkingen'] ?? ''));

$uitgebreid   = (int)($_POST['uitgebreid_onderhoud'] ?? 0) === 1 ? 1 : 0;
$gereviseerd  = (int)($_POST['gereviseerd'] ?? 0) === 1 ? 1 : 0;

if ($monteur_id <= 0 || $werkbon_id <= 0 || $object_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige invoer.']);
    exit;
}
if ($omschrijving === '') {
    echo json_encode(['ok' => false, 'msg' => 'Omschrijving is verplicht.']);
    exit;
}

/* Jaarvelden normaliseren: leeg -> NULL (via 0 + NULLIF in SQL) */
$fabricagejaar = ($fabricagejaar_raw === '' ? 0 : (int)$fabricagejaar_raw);
$beproeving    = ($beproeving_raw === '' ? 0 : (int)$beproeving_raw);

/* Datums: leeg -> NULL (via NULLIF in SQL) */
if ($datum_installatie === '') $datum_installatie = '';
if ($datum_onderhoud === '')   $datum_onderhoud   = '';

/* --------------------------------------------------
   Werkbon check + ownership + klant/werkadres regels
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT werkbon_id, monteur_id, klant_id, werkadres_id
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    echo json_encode(['ok' => false, 'msg' => 'Geen toegang tot werkbon.']);
    exit;
}

$klant_id     = (int)($wb['klant_id'] ?? 0);
$wbWerkadres  = (int)($wb['werkadres_id'] ?? 0);

/* --------------------------------------------------
   Object check: volgens dezelfde regels als werkbon_view
   - werkbon met werkadres => object.werkadres_id moet gelijk zijn
   - geen werkadres => object van klant én object.werkadres_id NULL/0
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT object_id, klant_id, COALESCE(werkadres_id, 0) AS werkadres_id
    FROM objecten
    WHERE object_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$obj = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$obj) {
    echo json_encode(['ok' => false, 'msg' => 'Object niet gevonden.']);
    exit;
}

$objKlant     = (int)($obj['klant_id'] ?? 0);
$objWerkadres = (int)($obj['werkadres_id'] ?? 0);

$allowed = false;
if ($wbWerkadres > 0) {
    $allowed = ($objWerkadres === $wbWerkadres);
} else {
    $allowed = ($objKlant === $klant_id) && ($objWerkadres === 0);
}

if (!$allowed) {
    echo json_encode(['ok' => false, 'msg' => 'Geen toegang tot dit object (werkadres/klant mismatch).']);
    exit;
}

/* --------------------------------------------------
   ✅ Update object (GEEN auto-link meer)
-------------------------------------------------- */
$stmt = $conn->prepare("
    UPDATE objecten
    SET
        omschrijving = ?,
        merk = ?,
        type = ?,
        datum_installatie  = NULLIF(?, ''),
        datum_onderhoud    = NULLIF(?, ''),
        fabricagejaar      = NULLIF(?, 0),
        beproeving_nen671_3 = NULLIF(?, 0),
        rijkstypekeur = ?,
        locatie = ?,
        verdieping = ?,
        resultaat = ?,
        opmerkingen = ?,
        uitgebreid_onderhoud = ?,
        gereviseerd = ?
    WHERE object_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "sssssiisssssiiii",
    $omschrijving,
    $merk,
    $type,
    $datum_installatie,
    $datum_onderhoud,
    $fabricagejaar,
    $beproeving,
    $rijkstypekeur,
    $locatie,
    $verdieping,
    $resultaat,
    $opmerkingen,
    $uitgebreid,
    $gereviseerd,
    $object_id
);

$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'msg' => 'DB fout: '.$err]);
    exit;
}

echo json_encode(['ok' => true]);
