<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

if ($_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(['error' => 'Geen toegang']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$monteur_id = (int)$_SESSION['user']['id'];

// ---- WEEK ----
$week = $_GET['week'] ?? date("W");
$jaar = $_GET['jaar'] ?? date("Y");

$week_start = date("Y-m-d", strtotime($jaar . "W" . $week));
$week_end   = date("Y-m-d", strtotime($jaar . "W" . $week . " +6 days"));


// ------------------------------
// 1. GEWERKTE UREN (urenregistratie)
// ------------------------------
$sql_worked = $conn->prepare("
    SELECT COALESCE(SUM(duur_minuten), 0) AS total_min
    FROM urenregistratie
    WHERE user_id = ?
    AND datum BETWEEN ? AND ?
");
$sql_worked->bind_param("iss", $monteur_id, $week_start, $week_end);
$sql_worked->execute();
$worked_minutes = (int)$sql_worked->get_result()->fetch_assoc()['total_min'];
$worked_hours = round($worked_minutes / 60, 2);


// ------------------------------
// 2. GEPLANDE UREN (werkbonnen)
// ------------------------------
$sql_planned = $conn->prepare("
    SELECT 
        uitvoerdatum AS datum,
        starttijd,
        eindtijd
    FROM werkbonnen
    WHERE monteur_id = ?
    AND uitvoerdatum BETWEEN ? AND ?
    AND gearchiveerd = 0
");
$sql_planned->bind_param("iss", $monteur_id, $week_start, $week_end);
$sql_planned->execute();
$res = $sql_planned->get_result();

$planned_minutes = 0;
$day_stats = [];

// Default dagen vullen met 0
for ($i=0; $i<7; $i++) {
    $d = date("Y-m-d", strtotime("$week_start +$i days"));
    $day_stats[$d] = [
        'planned' => 0,
        'worked'  => 0
    ];
}

while ($row = $res->fetch_assoc()) {
    $start = strtotime($row['starttijd']);
    $end   = strtotime($row['eindtijd']);
    $min   = max(0, ($end - $start) / 60);

    $planned_minutes += $min;
    $day_stats[$row['datum']]['planned'] += $min;
}


// ------------------------------
// 3. GEWERKTE UREN PER DAG
// ------------------------------
$sql_dag = $conn->prepare("
    SELECT datum, SUM(duur_minuten) AS total_min
    FROM urenregistratie
    WHERE user_id = ?
    AND datum BETWEEN ? AND ?
    GROUP BY datum
");
$sql_dag->bind_param("iss", $monteur_id, $week_start, $week_end);
$sql_dag->execute();
$dag_res = $sql_dag->get_result();

while ($r = $dag_res->fetch_assoc()) {
    $day_stats[$r['datum']]['worked'] += (int)$r['total_min'];
}


// ------------------------------
// EINDE
// ------------------------------
echo json_encode([
    'week_start'   => $week_start,
    'week_end'     => $week_end,
    'workedWeek'   => $worked_hours,
    'plannedWeek'  => round($planned_minutes / 60, 2),
    'totalWeek'    => round(($planned_minutes + $worked_minutes) / 60, 2),
    'days'         => $day_stats
]);
