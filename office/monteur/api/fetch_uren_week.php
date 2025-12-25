<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json');

$monteur_id = (int)$_SESSION['user']['id'];
$week = $_GET['week'] ?? date("W");
$jaar = $_GET['jaar'] ?? date("Y");

$week_start = date("Y-m-d", strtotime($jaar . "W" . $week));
$week_end = date("Y-m-d", strtotime($jaar . "W" . $week . " +6 days"));

$stmt = $conn->prepare("
    SELECT u.*, s.code, s.omschrijving
    FROM urenregistratie u
    LEFT JOIN uursoorten_uren s ON s.uursoort_id = u.uursoort_id
    WHERE u.user_id = ?
      AND u.datum BETWEEN ? AND ?
    ORDER BY u.datum ASC, u.starttijd ASC
");
$stmt->bind_param("iss", $monteur_id, $week_start, $week_end);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
$total = 0;

while ($r = $res->fetch_assoc()) {
    $total += (int)$r['duur_minuten'];
    $data[] = $r;
}

echo json_encode([
    "uren" => $data,
    "total" => round($total / 60, 2),
    "week_start" => $week_start,
    "week_end" => $week_end,
    "week" => $week,
    "jaar" => $jaar
]);
