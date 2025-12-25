<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
checkRole(['Admin']);

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $conn->prepare("DELETE FROM rollen WHERE rol_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    setFlash("Rol verwijderd.", "success");
}
header("Location: rollen.php");
exit;
