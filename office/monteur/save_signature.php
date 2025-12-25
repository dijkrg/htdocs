<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json; charset=utf-8');

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

$werkbon_id = (int)($data['werkbon_id'] ?? 0);
$image = (string)($data['image'] ?? '');

if ($monteur_id <= 0 || $werkbon_id <= 0 || $image === '') {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige invoer.']);
    exit;
}

// ownership check
$stmt = $conn->prepare("SELECT werkbon_id, monteur_id FROM werkbonnen WHERE werkbon_id = ? LIMIT 1");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    echo json_encode(['ok' => false, 'msg' => 'Geen toegang.']);
    exit;
}

// data URL → png bytes
if (!preg_match('#^data:image/png;base64,#', $image)) {
    echo json_encode(['ok' => false, 'msg' => 'Handtekening moet PNG zijn.']);
    exit;
}

$base64 = substr($image, strlen('data:image/png;base64,'));
$binary = base64_decode($base64, true);
if ($binary === false || strlen($binary) < 50) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige afbeelding.']);
    exit;
}

// map + bestandsnaam
$dirFs = realpath(__DIR__ . '/..') . '/uploads/handtekeningen';
if (!is_dir($dirFs)) {
    @mkdir($dirFs, 0775, true);
}
if (!is_dir($dirFs) || !is_writable($dirFs)) {
    echo json_encode(['ok' => false, 'msg' => 'Uploadmap niet schrijfbaar: /uploads/handtekeningen']);
    exit;
}

$fname = 'wb_' . $werkbon_id . '_' . date('Ymd_His') . '.png';
$pathFs = $dirFs . '/' . $fname;

// opslaan
if (file_put_contents($pathFs, $binary) === false) {
    echo json_encode(['ok' => false, 'msg' => 'Opslaan mislukt.']);
    exit;
}

// pad in DB (relatief vanaf webroot)
$dbPath = 'uploads/handtekeningen/' . $fname;

$u = $conn->prepare("UPDATE werkbonnen SET handtekening_klant = ? WHERE werkbon_id = ? LIMIT 1");
$u->bind_param("si", $dbPath, $werkbon_id);
$u->execute();
$u->close();

echo json_encode(['ok' => true, 'path' => '/' . $dbPath]);
