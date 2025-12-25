<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php'; // ← LET OP: ../ omdat dit in /ajax/ staat

header('Content-Type: application/json; charset=utf-8');

$klant_id = intval($_GET['klant_id'] ?? 0);
$data = [];

if ($klant_id > 0) {

    $stmt = $conn->prepare("
        SELECT werkadres_id, werkadresnummer, bedrijfsnaam, adres, postcode, plaats
        FROM werkadressen
        WHERE klant_id = ?
        ORDER BY adres ASC
    ");
    $stmt->bind_param("i", $klant_id);

    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($w = $res->fetch_assoc()) {

            // Mooie weergave:
            $volledig = trim("{$w['bedrijfsnaam']} - {$w['adres']}, {$w['postcode']} {$w['plaats']}");

            $data[] = [
                "id"       => $w['werkadres_id'],
                "nummer"   => $w['werkadresnummer'],
                "adres"    => $volledig
            ];
        }
    }
    $stmt->close();
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
