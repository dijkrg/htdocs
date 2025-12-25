<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot verwijderen van bestellingen.", "error");
    header("Location: ../index.php");
    exit;
}

// Controleer of er een ID is opgegeven
$bestelling_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bestelling_id <= 0) {
    setFlash("Geen bestelling geselecteerd om te verwijderen.", "error");
    header("Location: bestellingen.php");
    exit;
}

// Controleer of bestelling bestaat
$stmt = $conn->prepare("SELECT bestelnummer FROM bestellingen WHERE bestelling_id = ?");
$stmt->bind_param("i", $bestelling_id);
$stmt->execute();
$result = $stmt->get_result();
$bestelling = $result->fetch_assoc();

if (!$bestelling) {
    setFlash("Bestelling niet gevonden.", "error");
    header("Location: bestellingen.php");
    exit;
}

// Verwijderen
$stmtDel = $conn->prepare("DELETE FROM bestellingen WHERE bestelling_id = ?");
$stmtDel->bind_param("i", $bestelling_id);
$stmtDel->execute();

// Dankzij ON DELETE CASCADE worden automatisch gekoppelde regels uit bestelling_artikelen ook verwijderd

setFlash("Bestelling {$bestelling['bestelnummer']} is verwijderd ✅", "success");
header("Location: bestellingen.php");
exit;
