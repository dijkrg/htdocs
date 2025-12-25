<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

/* ---------------------------
   1) Check login & rol
---------------------------- */
if (empty($_SESSION['user']['id'])) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Niet ingelogd.'
    ]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];
$rol = strtolower($_SESSION['user']['rol'] ?? '');

if ($rol !== 'monteur') {
    echo json_encode([
        'ok' => false, 
        'msg' => 'Geen rechten om status te wijzigen.'
    ]);
    exit;
}

/* ---------------------------
   2) Ophalen POST waardes
---------------------------- */
$werkbon_id = intval($_POST['id'] ?? 0);
$status     = $_POST['status'] ?? '';
$note       = trim($_POST['note'] ?? '');

$allowed = ['onderweg','op_locatie','gereed'];

if (!in_array($status, $allowed, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldige status.']);
    exit;
}

if ($werkbon_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Ongeldig werkbon ID']);
    exit;
}

/* ---------------------------
   3) Controleren werkbon -> monteur
---------------------------- */
$stmt = $conn->prepare("SELECT monteur_id FROM werkbonnen WHERE werkbon_id = ?");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    echo json_encode(['ok' => false, 'msg' => 'Werkbon niet gevonden.']);
    exit;
}

if ((int)$res['monteur_id'] !== $monteur_id) {
    echo json_encode(['ok' => false, 'msg' => 'Deze werkbon is niet aan jou toegewezen.']);
    exit;
}

/* ---------------------------
   4) Updaten van status
---------------------------- */
$now = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    UPDATE werkbonnen 
    SET monteur_status = ?, 
        monteur_status_note = ?, 
        monteur_status_timestamp = ?, 
        last_update = NOW()
    WHERE werkbon_id = ?
");
$stmt->bind_param("sssi", $status, $note, $now, $werkbon_id);

if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'msg' => 'Database fout bij opslaan.']);
    exit;
}

/* ---------------------------
   5) SUCCES → reload toevoegen
---------------------------- */
echo json_encode([
    'ok' => true,
    'status' => $status,
    'timestamp' => $now,
    'reload' => true   // <-- FIX: Planner mag direct refreshen
]);
exit;
