<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash("Geen artikel ID opgegeven.", "error");
    header("Location: artikelen.php");
    exit;
}

$stmt = $conn->prepare("DELETE FROM artikelen WHERE artikel_id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    setFlash("Artikel succesvol verwijderd!", "success");
} else {
    setFlash("Fout bij verwijderen: " . $stmt->error, "error");
}
$stmt->close();

header("Location: artikelen.php");
exit;
