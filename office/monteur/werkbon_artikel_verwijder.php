<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);              // rij ID in werkbon_artikelen
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldige parameters.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Check eigendom
$stmt = $conn->prepare("
    SELECT 1 FROM werkbonnen
    WHERE werkbon_id = ? AND monteur_id = ?
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    setFlash("Geen toegang.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Delete
$stmt = $conn->prepare("
    DELETE FROM werkbon_artikelen 
    WHERE id = ? AND werkbon_id = ?
");
$stmt->bind_param("ii", $id, $werkbon_id);
$stmt->execute();

setFlash("Artikel verwijderd.", "success");
header("Location: /monteur/werkbon_view.php?id=$werkbon_id");
exit;
