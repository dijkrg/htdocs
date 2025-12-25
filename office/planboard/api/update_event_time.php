<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id         = $data['id'] ?? null;
$starttijd  = $data['starttijd'] ?? null;
$eindtijd   = $data['eindtijd'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Geen werkbon ID']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE werkbonnen 
    SET starttijd = ?, eindtijd = ?
    WHERE werkbon_id = ?
");

$stmt->bind_param("ssi", $starttijd, $eindtijd, $id);
$ok = $stmt->execute();

echo json_encode(['success' => $ok]);
exit;
