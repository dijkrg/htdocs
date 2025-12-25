<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$resultaten = [];

if (strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("
        SELECT artikel_id, artikelnummer, omschrijving, 
               FORMAT(verkoopprijs, 2, 'de_DE') AS verkoopprijs
        FROM artikelen
        WHERE artikelnummer LIKE ? OR omschrijving LIKE ?
        ORDER BY artikelnummer ASC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $resultaten[] = $row;
    }
    $stmt->close();
}

echo json_encode($resultaten);
