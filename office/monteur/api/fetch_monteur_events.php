<?php
require_once __DIR__ . '/../../includes/init.php';
header("Content-Type: application/json");

if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Monteur') {
    echo json_encode(["ok" => false, "msg" => "Niet ingelogd"]);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

$sql = $conn->prepare("
    SELECT 
        w.werkbon_id,
        w.werkbonnummer,
        w.omschrijving,
        w.uitvoerdatum AS datum,
        DATE_FORMAT(w.uitvoerdatum, '%d-%m-%Y') AS datum_display,
        w.starttijd,
        w.eindtijd,
        k.bedrijfsnaam AS klantnaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    WHERE w.monteur_id = ?
      AND w.gearchiveerd = 0
    ORDER BY w.uitvoerdatum ASC, w.starttijd ASC
");

$sql->bind_param("i", $monteur_id);
$sql->execute();
$res = $sql->get_result();

$events = [];
while ($row = $res->fetch_assoc()) {
    $events[] = [
        "id"            => $row["werkbon_id"],
        "title"         => $row["werkbonnummer"] . " - " . $row["omschrijving"],
        "date"          => $row["datum"],
        "datum_display" => $row["datum_display"],
        "starttijd"     => $row["starttijd"],
        "eindtijd"      => $row["eindtijd"],
        "klantnaam"     => $row["klantnaam"]
    ];
}

echo json_encode([
    "ok" => true,
    "events" => $events
]);

exit;
