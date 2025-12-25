<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$monteur_id = (int)$_SESSION['user']['id'];
$filter = $_GET['filter'] ?? 'today';

$where = "wb.monteur_id = $monteur_id AND wb.gearchiveerd = 0";

if ($filter === "today") {
    $where .= " AND wb.uitvoerdatum = CURDATE()";
}
if ($filter === "week") {
    $where .= " AND YEARWEEK(wb.uitvoerdatum,1) = YEARWEEK(CURDATE(),1)";
}

$sql = "
    SELECT 
        wb.werkbon_id,
        wb.werkbonnummer,
        wb.omschrijving,
        DATE_FORMAT(wb.uitvoerdatum, '%d-%m-%Y') AS datum,
        k.bedrijfsnaam AS klantnaam
    FROM werkbonnen wb
    LEFT JOIN klanten k ON k.klant_id = wb.klant_id
    WHERE $where
    ORDER BY wb.uitvoerdatum ASC, wb.starttijd ASC
";

$res = $conn->query($sql);

$list = [];
while ($r = $res->fetch_assoc()) {
    $list[] = $r;
}

echo json_encode($list);
