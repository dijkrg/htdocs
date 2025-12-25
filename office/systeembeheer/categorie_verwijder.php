<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot categorieën.", "error");
    header("Location: ../index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $conn->prepare("DELETE FROM categorieen WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash("Categorie verwijderd.", "success");
    } else {
        setFlash("Fout bij verwijderen: " . $stmt->error, "error");
    }
}
header("Location: categorieen.php");
exit;
