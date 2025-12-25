<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$werkbon_id = (int)($data['id'] ?? 0);

if ($werkbon_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Geen geldig ID ontvangen']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE werkbonnen
    SET monteur_id = NULL,
        uitvoerdatum = NULL,
        starttijd = NULL,
        eindtijd = NULL,
        status = 'Klaargezet',
        monteur_status = '',
        monteur_status_note = NULL,
        monteur_status_timestamp = NULL,
        monteur_alert_seen = 0
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);

$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

echo json_encode([
    'success' => (bool)$ok,
    'error'   => $ok ? null : ($err ?: 'Onbekende SQL fout')
], JSON_UNESCAPED_UNICODE);
