<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Alleen POST-verzoeken toestaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Methode niet toegestaan.";
    exit;
}

$werkbon_id = intval($_POST['werkbon_id'] ?? 0);
$status     = $_POST['status'] ?? '';

if ($werkbon_id === 0 || $status === '') {
    http_response_code(400);
    echo "Ongeldige invoer.";
    exit;
}

// Status bijwerken
$stmt = $conn->prepare("UPDATE werkbonnen SET status = ? WHERE werkbon_id = ?");
$stmt->bind_param("si", $status, $werkbon_id);

if ($stmt->execute()) {
    echo "Status bijgewerkt naar: $status";
} else {
    http_response_code(500);
    echo "Fout bij bijwerken status: " . $stmt->error;
}
$stmt->close();
