<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || strtolower($_SESSION['user']['rol']) !== 'monteur') {
    echo json_encode(["ok" => false, "msg" => "Niet ingelogd"]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];
$week = isset($_GET['week']) ? (int)$_GET['week'] : date("W");
$jaar = isset($_GET['jaar']) ? (int)$_GET['jaar'] : date("Y");

/* Weekberekening */
$week_start = date("Y-m-d", strtotime($jaar . "W" . str_pad($week, 2, "0", STR_PAD_LEFT)));
$week_end   = date("Y-m-d", strtotime($week_start . " +6 days"));

$sql = $conn->prepare("
    SELECT 
        w.werkbon_id,
        w.werkbonnummer,
        w.omschrijving,
        w.uitvoerdatum AS datum,
        DATE_FORMAT(w.uitvoerdatum, '%d-%m-%Y') AS datum_display,
        w.starttijd AS tijd_start,
        w.eindtijd AS tijd_eind,
        k.bedrijfsnaam AS klantnaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON k.klant_id = w.klant_id
    WHERE w.monteur_id = ?
      AND w.uitvoerdatum BETWEEN ? AND ?
      AND w.gearchiveerd = 0
    ORDER BY w.uitvoerdatum ASC, w.starttijd ASC
");

$sql->bind_param("iss", $monteur_id, $week_start, $week_end);
$sql->execute();
$res = $sql->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = $row;
}

/* ❗ Belangrijk: Slechts 1 JSON-output */
echo json_encode($out);
exit;
?>
