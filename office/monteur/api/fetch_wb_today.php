<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["ok" => false]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

$q = $conn->prepare("
    SELECT werkbon_id, werkbonnummer, omschrijving, starttijd, eindtijd
    FROM werkbonnen
    WHERE monteur_id=? 
    AND uitvoerdatum=CURDATE()
    AND gearchiveerd=0
    ORDER BY starttijd
");
$q->bind_param("i", $monteur_id);
$q->execute();

$res = $q->get_result();
$list = [];

while ($row = $res->fetch_assoc()) { $list[] = $row; }

echo json_encode(["ok" => true, "data" => $list]);
exit;
