<?php
// /monteur/ajax_mijn_planning.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json; charset=utf-8');

// no-cache (belangrijk bij PWA/service worker)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// JSON-endpoint: nooit warnings/html dumpen
ini_set('display_errors', '0');
error_reporting(E_ALL);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
if ($monteur_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** dd-mm-jjjj -> Y-m-d */
function parseNlDate(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') return null;

    $dt = DateTime::createFromFormat('d-m-Y', $s);
    $err = DateTime::getLastErrors();
    if (!$dt || ($err['warning_count'] ?? 0) > 0 || ($err['error_count'] ?? 0) > 0) return null;

    return $dt->format('Y-m-d');
}

$rangeStart = null;
$rangeEnd   = null;

// Mode: datum (NL)
$datumNl = (string)($_GET['datum'] ?? '');
if ($datumNl !== '') {
    $d = parseNlDate($datumNl);
    if ($d) {
        $rangeStart = $d;
        $rangeEnd   = $d;
    }
}

// fallback week/jaar (compat)
if (!$rangeStart || !$rangeEnd) {
    $week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
    $jaar = isset($_GET['jaar']) ? (int)$_GET['jaar'] : (int)date('Y');
    if ($week < 1 || $week > 53) $week = (int)date('W');
    if ($jaar < 2000 || $jaar > 2100) $jaar = (int)date('Y');

    try {
        $dt = new DateTime();
        $dt->setISODate($jaar, $week);  // maandag
        $rangeStart = $dt->format('Y-m-d');
        $dt->modify('+6 days');         // zondag
        $rangeEnd = $dt->format('Y-m-d');
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Bad date range'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$sql = "
    SELECT 
        w.werkbon_id,
        w.werkbonnummer,
        w.uitvoerdatum,
        w.starttijd,
        w.eindtijd,

        /* beide: algemene status + monteur status */
        w.status,
        w.werkzaamheden_status,

        t.naam AS type_naam,
        k.bedrijfsnaam AS klantnaam,

        COALESCE(wa.adres, k.adres)         AS adres,
        COALESCE(wa.postcode, k.postcode)  AS postcode,
        COALESCE(wa.plaats, k.plaats)      AS plaats,

        COALESCE(wa.telefoon, k.telefoonnummer) AS telefoon,
        COALESCE(wa.contactpersoon, k.contactpersoon) AS contactpersoon

    FROM werkbonnen w
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    LEFT JOIN klanten k            ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa      ON w.werkadres_id = wa.werkadres_id
    WHERE w.monteur_id = ?
      AND w.uitvoerdatum BETWEEN ? AND ?
    ORDER BY w.uitvoerdatum, w.starttijd, w.werkbonnummer
";

...

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'werkbon_id'          => (int)$row['werkbon_id'],
        'werkbonnummer'       => (string)$row['werkbonnummer'],
        'uitvoerdatum'        => (string)$row['uitvoerdatum'],
        'starttijd'           => (string)($row['starttijd'] ?? ''),
        'eindtijd'            => (string)($row['eindtijd'] ?? ''),

        'status'              => (string)($row['status'] ?? ''),
        'werkzaamheden_status'=> (string)($row['werkzaamheden_status'] ?? ''),

        'type_naam'           => (string)($row['type_naam'] ?? ''),
        'klantnaam'           => (string)($row['klantnaam'] ?? ''),
        'adres'               => (string)($row['adres'] ?? ''),
        'postcode'            => (string)($row['postcode'] ?? ''),
        'plaats'              => (string)($row['plaats'] ?? ''),
        'telefoon'            => (string)($row['telefoon'] ?? ''),
        'contactpersoon'      => (string)($row['contactpersoon'] ?? ''),
    ];
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'SQL prepare failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('iss', $monteur_id, $rangeStart, $rangeEnd);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'werkbon_id'      => (int)$row['werkbon_id'],
        'werkbonnummer'   => (string)$row['werkbonnummer'],
        'uitvoerdatum'    => (string)$row['uitvoerdatum'],
        'starttijd'       => (string)($row['starttijd'] ?? ''),
        'eindtijd'        => (string)($row['eindtijd'] ?? ''),

        'status'          => (string)($row['status'] ?? ''), // planner
        'monteur_status'  => (string)($row['werkzaamheden_status'] ?? ''), // monteur
        'monteur_status_at' => (string)($row['werkzaamheden_status_at'] ?? ''),

        'type_naam'       => (string)($row['type_naam'] ?? ''),
        'klantnaam'       => (string)($row['klantnaam'] ?? ''),

        'adres'           => (string)($row['adres'] ?? ''),
        'postcode'        => (string)($row['postcode'] ?? ''),
        'plaats'          => (string)($row['plaats'] ?? ''),

        'telefoon'        => (string)($row['telefoon'] ?? ''),
        'contactpersoon'  => (string)($row['contactpersoon'] ?? ''),
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
