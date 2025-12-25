<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$code = trim($_GET['code'] ?? '');
$exclude_id = intval($_GET['exclude_id'] ?? 0);

$response = ['exists' => false];

if ($code !== '') {
    if ($exclude_id > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM objecten WHERE code=? AND object_id<>?");
        $stmt->bind_param("si", $code, $exclude_id);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM objecten WHERE code=?");
        $stmt->bind_param("s", $code);
    }

    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    if ($cnt > 0) {
        $response['exists'] = true;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
