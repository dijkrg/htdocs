<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json; charset=utf-8');

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_POST['werkbon_id'] ?? 0);
$object_id  = (int)($_POST['object_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0 || $object_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige invoer.']);
    exit;
}

// Werkbon ophalen + ownership + klant_id
$stmt = $conn->prepare("
    SELECT werkbon_id, monteur_id, klant_id
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    echo json_encode(['ok' => false, 'msg' => 'Geen toegang tot werkbon.']);
    exit;
}

$klant_id = (int)$wb['klant_id'];

// Object check: hoort bij klant
$stmt = $conn->prepare("SELECT object_id, klant_id FROM objecten WHERE object_id = ? LIMIT 1");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$obj = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$obj || (int)$obj['klant_id'] !== $klant_id) {
    echo json_encode(['ok' => false, 'msg' => 'Object hoort niet bij deze klant.']);
    exit;
}

// Status 'onderhouden' zoeken (optioneel)
$status_id = null;
$res = $conn->query("SELECT status_id FROM object_status WHERE LOWER(naam) = 'onderhouden' LIMIT 1");
if ($res && ($r = $res->fetch_assoc())) $status_id = (int)$r['status_id'];

$tz = new DateTimeZone('Europe/Amsterdam');
$now = new DateTimeImmutable('now', $tz);
$today = $now->format('Y-m-d');
$time  = $now->format('H:i:s');

// Update object
if ($status_id) {
    $u = $conn->prepare("UPDATE objecten SET datum_onderhoud = ?, status_id = ? WHERE object_id = ? LIMIT 1");
    $u->bind_param("sii", $today, $status_id, $object_id);
} else {
    $u = $conn->prepare("UPDATE objecten SET datum_onderhoud = ? WHERE object_id = ? LIMIT 1");
    $u->bind_param("si", $today, $object_id);
}
$u->execute();
$u->close();

// Log inspectie (bijzonderheid)
$ins = $conn->prepare("
    INSERT INTO object_inspecties (object_id, werkbon_id, user_id, resultaat, opmerking, datum, tijd)
    VALUES (?, ?, ?, 'onderhouden', '', ?, ?)
");
$ins->bind_param("iiiss", $object_id, $werkbon_id, $monteur_id, $today, $time);
$ins->execute();
$ins->close();

echo json_encode([
    'ok' => true,
    'today' => $today,
    'status_set' => (bool)$status_id
]);
