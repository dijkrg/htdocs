<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$monteur_id = isset($_GET['monteur_id']) ? (int)$_GET['monteur_id'] : null;
$datum      = isset($_GET['datum']) ? $_GET['datum'] : null;

if (!$datum) {
    setFlash("Geen datum opgegeven.", "error");
    header("Location: werkbonnen.php");
    exit;
}

// Nieuw werkbonnummer bepalen
$res = $conn->query("SELECT MAX(werkbonnummer) as maxnr FROM werkbonnen");
$row = $res->fetch_assoc();
$nieuwNummer = $row['maxnr'] ? $row['maxnr'] + 1 : 500000;

// Placeholder klant_id (bijv. 0 of NULL)
$klant_id = null;

$stmt = $conn->prepare("
    INSERT INTO werkbonnen (werkbonnummer, klant_id, monteur_id, uitvoerdatum, status, datum)
    VALUES (?, ?, ?, ?, 'Ingepland', NOW())
");
$stmt->bind_param("siis", $nieuwNummer, $klant_id, $monteur_id, $datum);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    header("Location: werkbon_bewerk.php?id=" . $newId);
    exit;
} else {
    setFlash("❌ Fout bij aanmaken: " . $stmt->error, "error");
    header("Location: werkbonnen.php");
    exit;
}
