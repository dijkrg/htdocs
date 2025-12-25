<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$id           = (int)($data['id'] ?? 0);
$uitvoerdatum = $data['uitvoerdatum'] ?? null;
$starttijd    = $data['starttijd'] ?? null;
$eindtijd     = $data['eindtijd'] ?? null;
$monteur_id   = isset($data['monteur_id']) ? (int)$data['monteur_id'] : null;

if ($id <= 0 || !$uitvoerdatum) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige gegevens (id/uitvoerdatum)']);
    exit;
}

/* 
========================================
  ALS ER GEEN START/ENDTIJD IS
  → DAN KOMT DIT VAN "drag & drop"
  → Tijden NIET aanpassen!
========================================
*/
if ($starttijd === null && $eindtijd === null) {

    $stmt = $conn->prepare("
        UPDATE werkbonnen
        SET uitvoerdatum = ?, 
            monteur_id   = IFNULL(?, monteur_id)
        WHERE werkbon_id = ?
    ");
    $stmt->bind_param("sii", $uitvoerdatum, $monteur_id, $id);

    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

/* 
========================================
  ALS ER WÉL Tijden worden doorgegeven
  (popup “Tijden opslaan”)
========================================
*/

$stmt = $conn->prepare("
    UPDATE werkbonnen
    SET uitvoerdatum = ?, 
        starttijd    = ?, 
        eindtijd     = ?, 
        monteur_id   = IFNULL(?, monteur_id)
    WHERE werkbon_id = ?
");
$stmt->bind_param("sssii", $uitvoerdatum, $starttijd, $eindtijd, $monteur_id, $id);

$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
