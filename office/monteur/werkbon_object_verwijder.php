<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);
$object_id = (int)($_GET['object_id'] ?? 0);

if ($werkbon_id <= 0 || $object_id <= 0) {
    setFlash("Ongeldige actie.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Controle op eigenaar
$stmt = $conn->prepare("SELECT 1 FROM werkbonnen WHERE werkbon_id=? AND monteur_id=?");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    setFlash("Geen toegang.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Verwijderen
$stmt = $conn->prepare("
    DELETE FROM werkbon_objecten
    WHERE werkbon_id = ? AND object_id = ?
");
$stmt->bind_param("ii", $werkbon_id, $object_id);
$stmt->execute();

setFlash("Object verwijderd.", "success");
header("Location: /monteur/werkbon_view.php?id={$werkbon_id}");
exit;
