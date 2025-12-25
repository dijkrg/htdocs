<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$werkbon_id = intval($_GET['id'] ?? 0);
if ($werkbon_id > 0) {
    $stmt = $conn->prepare("UPDATE werkbonnen SET gearchiveerd=0 WHERE werkbon_id=?");
    $stmt->bind_param("i", $werkbon_id);
    $stmt->execute();
    $stmt->close();

    setFlash("Werkbon succesvol hersteld!", "success");
}
header("Location: werkbonnen_archief.php");
exit;
