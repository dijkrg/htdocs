<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// 📨 JSON body ophalen
$data = json_decode(file_get_contents('php://input'), true);

$werkbon_id = (int)($data['id'] ?? 0);
$monteur_id = (int)($data['monteur_id'] ?? 0);
$start      = $data['start'] ?? null;
$end        = $data['end'] ?? null;

// ✅ Basiscontrole
if ($werkbon_id <= 0 || $monteur_id <= 0 || !$start) {
    echo json_encode([
        'success' => false,
        'error'   => 'Ongeldige invoer (werkbon_id, monteur_id of starttijd ontbreekt)'
    ]);
    exit;
}

try {
    $startDt = new DateTime($start);

    // ⏰ Standaard eindtijd = starttijd + 30 minuten, tenzij er al een eindtijd is
    if ($end) {
        $endDt = new DateTime($end);
    } else {
        $endDt = (clone $startDt)->modify('+30 minutes');
    }

    $uitvoerdatum = $startDt->format('Y-m-d');
    $starttijd    = $startDt->format('H:i:s');
    $eindtijd     = $endDt->format('H:i:s');

    $stmt = $conn->prepare("
        UPDATE werkbonnen 
        SET monteur_id = ?, 
            uitvoerdatum = ?, 
            starttijd = ?, 
            eindtijd = ?, 
            status = 'Ingepland'
        WHERE werkbon_id = ?
    ");
    $stmt->bind_param("isssi", $monteur_id, $uitvoerdatum, $starttijd, $eindtijd, $werkbon_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Datumfout: ' . $e->getMessage()]);
}
