<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["ok" => false, "msg" => "Geen toegang."]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_POST['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Ongeldig ID."]);
    exit;
}

$stmt = $conn->prepare("
    SELECT status, monteur_id 
    FROM werkbonnen WHERE werkbon_id = ?
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();

if (!$wb || $wb['monteur_id'] != $monteur_id) {
    echo json_encode(["ok" => false, "msg" => "Geen rechten voor deze werkbon."]);
    exit;
}

$new = $wb['status'] === 'gereed' ? 'in_behandeling' : 'gereed';

$update = $conn->prepare("
    UPDATE werkbonnen SET status=? WHERE werkbon_id=?
");
$update->bind_param("si", $new, $werkbon_id);
$update->execute();

echo json_encode(["ok" => true, "status" => $new]);
exit;
