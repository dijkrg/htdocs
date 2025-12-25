<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$artikel_id  = isset($_GET['artikel_id']) ? (int)$_GET['artikel_id'] : 0;
$magazijn_id = isset($_GET['magazijn_id']) ? (int)$_GET['magazijn_id'] : 0;

if ($artikel_id <= 0 || $magazijn_id <= 0) {
    echo json_encode(['error' => 'Ongeldige parameters']);
    exit;
}

$stmt = $conn->prepare("
    SELECT aantal
    FROM voorraad_magazijn
    WHERE artikel_id = ? AND magazijn_id = ?
");
$stmt->bind_param("ii", $artikel_id, $magazijn_id);
$stmt->execute();
$stmt->bind_result($aantal);
$stmt->fetch();
$stmt->close();

$aantal = $aantal !== null ? (float)$aantal : 0;
echo json_encode(['aantal' => $aantal]);
