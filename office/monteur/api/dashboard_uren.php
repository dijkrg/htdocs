<?php
require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json');

$monteur_id = (int)$_SESSION['user']['id'];

// Weekbereik
$week_start = date("Y-m-d", strtotime("monday this week"));
$week_end   = date("Y-m-d", strtotime("sunday this week"));

// Maandbereik
$month_start = date("Y-m-01");
$month_end   = date("Y-m-t");

// ---- Uren deze week ----
$stmt = $conn->prepare("
    SELECT SUM(duur_minuten) AS minuten 
    FROM urenregistratie 
    WHERE user_id = ? AND datum BETWEEN ? AND ?
");
$stmt->bind_param("iss", $monteur_id, $week_start, $week_end);
$stmt->execute();
$week = $stmt->get_result()->fetch_assoc();
$week_hours = round(($week['minuten'] ?? 0) / 60, 2);


// ---- Uren deze maand ----
$stmt = $conn->prepare("
    SELECT SUM(duur_minuten) AS minuten 
    FROM urenregistratie 
    WHERE user_id = ? AND datum BETWEEN ? AND ?
");
$stmt->bind_param("iss", $monteur_id, $month_start, $month_end);
$stmt->execute();
$month = $stmt->get_result()->fetch_assoc();
$month_hours = round(($month['minuten'] ?? 0) / 60, 2);


// ---- Laatste registratie ----
$stmt = $conn->prepare("
    SELECT datum, starttijd, eindtijd
    FROM urenregistratie
    WHERE user_id = ?
    ORDER BY datum DESC, starttijd DESC
    LIMIT 1
");
$stmt->bind_param("i", $monteur_id);
$stmt->execute();
$last = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "week_hours" => $week_hours,
    "month_hours" => $month_hours,
    "last_entry" => $last ?: null
]);
