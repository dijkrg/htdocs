<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT werkadres_id, klant_id FROM werkadressen WHERE werkadres_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$wa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wa) {
    setFlash("Werkadres niet gevonden.", "error");
    header("Location: klanten.php");
    exit;
}

$stmt = $conn->prepare("DELETE FROM werkadressen WHERE werkadres_id=?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    setFlash("🗑 Werkadres verwijderd.", "success");
} else {
    setFlash("Fout bij verwijderen: " . $stmt->error, "error");
}
$stmt->close();

header("Location: klant_detail.php?id=" . $wa['klant_id']);
exit;
