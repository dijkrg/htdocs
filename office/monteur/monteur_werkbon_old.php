<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json');

$monteur_id = (int)$_SESSION['user']['id'];

$stmt = $conn->prepare("
    SELECT 
        werkbon_id, 
        werkbonnummer,
        omschrijving,
        uitvoerdatum,
        starttijd,
        eindtijd,
        status
    FROM werkbonnen
    WHERE monteur_id = ?
      AND gearchiveerd = 0
    ORDER BY uitvoerdatum ASC, starttijd ASC
");
$stmt->bind_param("i", $monteur_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode($rows);
