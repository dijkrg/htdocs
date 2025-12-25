<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header('Content-Type: application/json');

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['id'] ?? 0);

if ($werkbon_id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

/* -------------------------
   Werkbon hoofdgegevens
------------------------- */
$stmt = $conn->prepare("
    SELECT werkbon_id, werkbonnummer, omschrijving, uitvoerdatum, starttijd, eindtijd, werk_gereed, status
    FROM werkbonnen
    WHERE werkbon_id=? AND monteur_id=? AND gearchiveerd=0
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();

if (!$wb) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

/* -------------------------
   Objecten
------------------------- */
$obj = $conn->prepare("
    SELECT o.object_id, o.code, o.omschrijving
    FROM werkbon_objecten wo
    JOIN objecten o ON wo.object_id = o.object_id
    WHERE wo.werkbon_id=?
    ORDER BY o.code
");
$obj->bind_param("i", $werkbon_id);
$obj->execute();
$wb['objecten'] = $obj->get_result()->fetch_all(MYSQLI_ASSOC);

/* -------------------------
   Artikelen
------------------------- */
$art = $conn->prepare("
    SELECT wa.id, wa.aantal, a.omschrijving
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON a.artikel_id = wa.artikel_id
    WHERE wa.werkbon_id=?
");
$art->bind_param("i", $werkbon_id);
$art->execute();
$wb['artikelen'] = $art->get_result()->fetch_all(MYSQLI_ASSOC);

/* -------------------------
   Uren
------------------------- */
$ur = $conn->prepare("
    SELECT werkbon_uur_id, datum, begintijd, eindtijd
    FROM werkbon_uren
    WHERE werkbon_id=?
    ORDER BY datum ASC, begintijd ASC
");
$ur->bind_param("i", $werkbon_id);
$ur->execute();
$wb['uren'] = $ur->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($wb);
