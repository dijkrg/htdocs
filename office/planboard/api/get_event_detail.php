<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$werkbon_id = (int)($_GET['id'] ?? 0);
if ($werkbon_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        w.werkbon_id,
        w.werkbonnummer,
        w.uitvoerdatum,
        w.starttijd,
        w.eindtijd,
        w.omschrijving,
        w.status,

        -- ✅ werkzaamheden status (voor popup/planboard)
        w.werkzaamheden_status,
        w.werkzaamheden_status_at,

        -- ✅ monteur status (voor popup/planboard)
        w.monteur_status,
        w.monteur_status_timestamp,

        k.bedrijfsnaam AS klant,

        COALESCE(
            CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats),
            w.werkadres,
            'Onbekend adres'
        ) AS werkadres
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    WHERE w.werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($werkbon) {
    echo json_encode(['success' => true, 'werkbon' => $werkbon], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Werkbon niet gevonden'], JSON_UNESCAPED_UNICODE);
exit;
