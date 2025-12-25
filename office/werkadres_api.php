<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$klant_id = intval($_GET['klant_id'] ?? 0);
$data = [];

if ($klant_id > 0) {
    $stmt = $conn->prepare("
        SELECT werkadres_id, bedrijfsnaam, plaats 
        FROM werkadressen 
        WHERE klant_id = ? 
        ORDER BY bedrijfsnaam ASC
    ");
    $stmt->bind_param("i", $klant_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
}

echo json_encode($data);
