<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT artikel_id, artikelnummer, naam, verkoopprijs
    FROM artikelen
    WHERE naam LIKE CONCAT('%', ?, '%')
       OR artikelnummer LIKE CONCAT('%', ?, '%')
    ORDER BY naam ASC
    LIMIT 20
");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
