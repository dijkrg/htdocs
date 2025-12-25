<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

// Alleen monteur
$rol = $_SESSION['user']['rol'] ?? '';
if ($rol !== 'Monteur') {
    echo json_encode(['ok' => false, 'msg' => 'Geen rechten']);
    exit;
}

$werkbon_id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($werkbon_id < 1 || !in_array($status, ['onderweg','op_locatie','gereed'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige invoer']);
    exit;
}

// Zeker zijn dat werkbon aan deze monteur toe is gewezen
$monteur_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT monteur_id FROM werkbonnen WHERE werkbon_id = ?");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res || (int)$res['monteur_id'] !== (int)$monteur_id) {
    echo json_encode(['ok' => false, 'msg' => 'Geen toegang tot deze werkbon']);
    exit;
}

// Updaten
$stmt = $conn->prepare("UPDATE werkbonnen SET monteur_status = ? WHERE werkbon_id = ?");
$stmt->bind_param("si", $status, $werkbon_id);
$stmt->execute();

echo json_encode(['ok' => true]);
exit;
