<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT artikel_id, artikelnummer, omschrijving
    FROM artikelen
    WHERE (categorie IS NULL OR categorie <> 'Administratie')
      AND (artikelnummer LIKE CONCAT('%', ?, '%') OR omschrijving LIKE CONCAT('%', ?, '%'))
    ORDER BY artikelnummer ASC
    LIMIT 25
");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'id'   => (int)$row['artikel_id'],
        'text' => $row['artikelnummer'] . ' — ' . $row['omschrijving']
    ];
}

echo json_encode($data);
