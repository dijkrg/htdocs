<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$uur_id    = intval($_GET['id'] ?? 0);
$werkbon_id = intval($_GET['werkbon'] ?? 0);

// Check goedkeuring
$stmt = $conn->prepare("SELECT goedgekeurd, werkbon_id FROM werkbon_uren WHERE werkbon_uur_id=?");
$stmt->bind_param("i", $uur_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res['goedgekeurd'] == 1) {
    setFlash("❌ Deze uren zijn goedgekeurd en mogen niet meer verwijderd worden.", "error");
    header("Location: werkbon_detail.php?id=".$res['werkbon_id']);
    exit;
}

if ($uur_id > 0) {
    $stmt = $conn->prepare("DELETE FROM werkbon_uren WHERE werkbon_uur_id=?");
    $stmt->bind_param("i", $uur_id);
    if ($stmt->execute()) {
        setFlash("✅ Uur verwijderd.", "success");
    } else {
        setFlash("❌ Verwijderen mislukt.", "error");
    }
}

header("Location: werkbon_detail.php?id=$werkbon_id");
exit;
