<?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$q = $conn->query("
    SELECT werkbon_id, monteur_status, monteur_status_timestamp
    FROM werkbonnen
    WHERE gearchiveerd = 0
");

$data = [];
while ($r = $q->fetch_assoc()) {
    $data[$r['werkbon_id']] = [
        'status' => $r['monteur_status'],
        'timestamp' => $r['monteur_status_timestamp']
    ];
}

echo json_encode($data);
exit;
