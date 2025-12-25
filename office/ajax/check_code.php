<?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$code = trim($_GET['code'] ?? '');
$object_id = (int)($_GET['object_id'] ?? 0);

if ($code === '') {
    echo json_encode(['status' => 'empty']);
    exit;
}

$stmt = $conn->prepare("SELECT object_id FROM objecten WHERE code = ? AND object_id <> ?");
$stmt->bind_param("si", $code, $object_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode(['status' => 'exists']);
} else {
    echo json_encode(['status' => 'available']);
}
$stmt->close();
