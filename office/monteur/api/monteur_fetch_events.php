<?php
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if (empty($_SESSION['user']['id']) || strtolower($_SESSION['user']['rol']) !== 'monteur') {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$monteur_id = (int)$_SESSION['user']['id'];

$sql = "
    SELECT 
        w.werkbon_id,
        w.werkbonnummer,
        w.uitvoerdatum,
        w.starttijd,
        w.eindtijd,
        w.monteur_status,
        w.omschrijving,
        k.bedrijfsnaam,
        CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats) AS werkadres
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    WHERE w.monteur_id = ?
    ORDER BY w.uitvoerdatum ASC, w.starttijd ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $monteur_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $date = $row['uitvoerdatum'];

    if (!isset($out[$date])) $out[$date] = [];

    $out[$date][] = [
        'id' => $row['werkbon_id'],
        'nummer' => $row['werkbonnummer'],
        'starttijd' => $row['starttijd'],
        'eindtijd' => $row['eindtijd'],
        'status' => $row['monteur_status'],
        'klant' => $row['bedrijfsnaam'],
        'adres' => $row['werkadres'],
        'omschrijving' => $row['omschrijving']
    ];
}

echo json_encode(['ok' => true, 'days' => $out]);
exit;
