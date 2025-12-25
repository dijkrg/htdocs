<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

/* ========================================================
   1. DASHBOARD STATISTICS (counts)
======================================================== */

$todayCount = $conn->query("
    SELECT COUNT(*) AS c
    FROM werkbonnen
    WHERE monteur_id = $monteur_id
      AND uitvoerdatum = CURDATE()
      AND gearchiveerd = 0
")->fetch_assoc()['c'];

$tomorrowCount = $conn->query("
    SELECT COUNT(*) AS c
    FROM werkbonnen
    WHERE monteur_id = $monteur_id
      AND uitvoerdatum = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
      AND gearchiveerd = 0
")->fetch_assoc()['c'];

$weekCount = $conn->query("
    SELECT COUNT(*) AS c
    FROM werkbonnen
    WHERE monteur_id = $monteur_id
      AND YEARWEEK(uitvoerdatum, 1) = YEARWEEK(CURDATE(), 1)
      AND gearchiveerd = 0
")->fetch_assoc()['c'];

/* ========================================================
   2. UREN (worked_today, worked_week, planned_week, total_week)
======================================================== */

/* Gewerkte uren deze week */
$q1 = $conn->query("
    SELECT COALESCE(SUM(duur_minuten),0) AS m
    FROM urenregistratie
    WHERE user_id = $monteur_id
      AND YEARWEEK(datum,1) = YEARWEEK(CURDATE(),1)
")->fetch_assoc();
$workedWeek = round($q1['m'] / 60, 2);

/* Gewerkte uren vandaag */
$q2 = $conn->query("
    SELECT COALESCE(SUM(duur_minuten),0) AS m
    FROM urenregistratie
    WHERE user_id = $monteur_id
      AND datum = CURDATE()
")->fetch_assoc();
$workedToday = round($q2['m'] / 60, 2);

/* Eventueel later: geplande uren */
$plannedHours = 0;

/* Totaal week */
$totalHours = $workedWeek + $plannedHours;

/* ========================================================
   3. TODAY LIST + TOMORROW LIST (full werkbon data)
======================================================== */

$todayList = [];
$tomorrowList = [];

$todayQ = $conn->query("
    SELECT werkbon_id, werkbonnummer, omschrijving, starttijd, eindtijd
    FROM werkbonnen
    WHERE monteur_id = $monteur_id
      AND uitvoerdatum = CURDATE()
      AND gearchiveerd = 0
    ORDER BY starttijd ASC
");

while ($r = $todayQ->fetch_assoc()) {
    $todayList[] = $r;
}

$tomorrowQ = $conn->query("
    SELECT werkbon_id, werkbonnummer, omschrijving, starttijd, eindtijd
    FROM werkbonnen
    WHERE monteur_id = $monteur_id
      AND uitvoerdatum = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
      AND gearchiveerd = 0
    ORDER BY starttijd ASC
");

while ($r = $tomorrowQ->fetch_assoc()) {
    $tomorrowList[] = $r;
}

/* ========================================================
   4. JSON OUTPUT voor monteur_dashboard_v2.js
======================================================== */

echo json_encode([
    "today"          => $todayCount,
    "tomorrow"       => $tomorrowCount,
    "week"           => $weekCount,
    "hours"          => $workedWeek,

    "planned_hours"  => $plannedHours,
    "total_hours"    => $totalHours,

    "todayList"      => $todayList,
    "tomorrowList"   => $tomorrowList,

    "workedToday"    => $workedToday,
    "workedWeek"     => $workedWeek
]);

exit;
