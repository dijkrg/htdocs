<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json; charset=utf-8');

// no-cache (belangrijk bij PWA/service worker)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', '0');
error_reporting(E_ALL);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_POST['werkbon_id'] ?? 0);
$status     = trim((string)($_POST['status'] ?? ''));

$allowed = ['open', 'bezig', 'onderweg', 'op_locatie', 'gereed'];

if ($monteur_id <= 0 || $werkbon_id <= 0 || !in_array($status, $allowed, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige invoer.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Eerst checken of werkbon van monteur is (heldere foutmelding)
$stmt = $conn->prepare("
    SELECT werkbon_id, werkzaamheden_status
    FROM werkbonnen
    WHERE werkbon_id = ? AND monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb) {
    echo json_encode(['ok' => false, 'msg' => 'Werkbon niet gevonden of geen rechten.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Als status al hetzelfde is: alsnog ok teruggeven
if ((string)$wb['werkzaamheden_status'] === $status) {
    echo json_encode(['ok' => true, 'status' => $status, 'same' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Update status
$stmt = $conn->prepare("
    UPDATE werkbonnen
    SET werkzaamheden_status = ?,
        werkzaamheden_status_at = NOW()
    WHERE werkbon_id = ?
      AND monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("sii", $status, $werkbon_id, $monteur_id);

$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'msg' => 'DB fout: '.$err], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
