<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ✅ Toegangscontrole
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: login.php");
    exit;
}

// ✅ Object-ID controleren
$object_id = intval($_GET['id'] ?? 0);
if ($object_id <= 0) {
    setFlash("❌ Ongeldig object-ID.", "error");
    header("Location: objecten.php");
    exit;
}

// ✅ Verwijderen
$stmt = $conn->prepare("DELETE FROM objecten WHERE object_id = ?");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$rows = $stmt->affected_rows;
$stmt->close();

if ($rows > 0) {
    setFlash("🗑️ Object succesvol verwijderd.", "success");
} else {
    setFlash("⚠️ Object niet gevonden of al verwijderd.", "error");
}

header("Location: objecten.php");
exit;
