<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$sql = "
    SELECT 
        w.werkbon_id AS id,
        w.werkbonnummer,
        t.naam AS type_werkzaamheden,
        k.bedrijfsnaam AS klant,
        COALESCE(wa.adres, w.werkadres, '-') AS werkadres,
        w.status
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.status = 'Klaargezet' AND w.gearchiveerd = 0
    ORDER BY w.werkbonnummer ASC
";

$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
echo json_encode($items);
