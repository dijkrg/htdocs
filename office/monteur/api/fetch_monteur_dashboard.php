<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["ok" => false]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

$data = [
    "today" => 0,
    "week" => 0,
    "month" => 0,
    "status" => "",
    "timestamp" => ""
];

$data['today'] = $conn->query("
    SELECT COUNT(*) AS c 
    FROM werkbonnen 
    WHERE monteur_id=$monteur_id AND uitvoerdatum=CURDATE() AND gearchiveerd=0
")->fetch_assoc()['c'];

$data['week'] = $conn->query("
    SELECT COUNT(*) AS c 
    FROM werkbonnen 
    WHERE monteur_id=$monteur_id AND YEARWEEK(uitvoerdatum,1)=YEARWEEK(CURDATE(),1) AND gearchiveerd=0
")->fetch_assoc()['c'];

$data['month'] = $conn->query("
    SELECT COUNT(*) AS c 
    FROM werkbonnen 
    WHERE monteur_id=$monteur_id AND MONTH(uitvoerdatum)=MONTH(CURDATE()) AND gearchiveerd=0
")->fetch_assoc()['c'];

$status = $conn->query("
    SELECT monteur_status, status_timestamp 
    FROM medewerkers WHERE id=$monteur_id
")->fetch_assoc();

$data['status'] = $status['monteur_status'];
$data['timestamp'] = $status['status_timestamp'];

echo json_encode(["ok" => true, "data" => $data]);
exit;
