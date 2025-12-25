<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flash.php';

header('Content-Type: application/json');

$id     = intval($_POST['id'] ?? 0);
$gereed = intval($_POST['gereed'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige werkbon ID']);
    exit;
}

// ✅ Bepaal status op basis van vinkje
$newStatus = $gereed ? 'Compleet' : 'Ingepland';

$stmt = $conn->prepare("
    UPDATE werkbonnen 
    SET werk_gereed = ?, status = ? 
    WHERE werkbon_id = ?
");
$stmt->bind_param("isi", $gereed, $newStatus, $id);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'status' => $newStatus]);
} else {
    echo json_encode(['ok' => false, 'msg' => $stmt->error]);
}
$stmt->close();
