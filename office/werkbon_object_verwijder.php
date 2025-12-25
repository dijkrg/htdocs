<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ✅ Controleer of werkbon_id en object_id zijn meegegeven
$werkbon_id = isset($_GET['werkbon_id']) ? (int)$_GET['werkbon_id'] : 0;
$object_id  = isset($_GET['object_id'])  ? (int)$_GET['object_id']  : 0;

if ($werkbon_id <= 0 || $object_id <= 0) {
    setFlash("Ongeldige aanvraag. Werkbon of object ontbreekt.", "error");
    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

// ✅ Verwijder de koppeling
$stmt = $conn->prepare("DELETE FROM werkbon_objecten WHERE werkbon_id = ? AND object_id = ?");
$stmt->bind_param("ii", $werkbon_id, $object_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        setFlash("Object succesvol ontkoppeld van werkbon.", "success");
    } else {
        setFlash("Geen koppeling gevonden om te verwijderen.", "error");
    }
} else {
    setFlash("Fout bij verwijderen: " . $stmt->error, "error");
}

$stmt->close();

// ✅ Terug naar de detailpagina
header("Location: werkbon_detail.php?id=" . $werkbon_id);
exit;
?>
