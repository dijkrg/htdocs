<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["ok" => false]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

$response = [
    "ok" => true,
    "today" => [],
    "tomorrow" => [],
    "status" => "",
    "timestamp" => ""
];

// Vandaag
$response['today'] = $conn->query("
    SELECT werkbon_id, werkbonnummer, omschrijving, starttijd, eindtijd
    FROM werkbonnen
    WHERE monteur_id=$monteur_id
    AND uitvoerdatum=CURDATE()
    AND gearchiveerd=0
")->fetch_all(MYSQLI_ASSOC);

// Morgen
$response['tomorrow'] = $conn->query("
    SELECT werkbon_id, werkbonnummer, omschrijving, starttijd, eindtijd
    FROM werkbonnen
    WHERE monteur_id=$monteur_id
    AND uitvoerdatum=DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    AND gearchiveerd=0
")->fetch_all(MYSQLI_ASSOC);

// Status
$s = $conn->query("
    SELECT monteur_status, status_timestamp 
    FROM medewerkers WHERE id=$monteur_id
")->fetch_assoc();

$response['status'] = $s['monteur_status'];
$response['timestamp'] = $s['status_timestamp'];

echo json_encode($response);
exit;
