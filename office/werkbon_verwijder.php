<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
requireLogin();
requireRole(['Admin', 'Manager']);

$werkbon_id = (int)($_GET['id'] ?? 0);

if ($werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /werkbonnen.php");
    exit;
}

// 1) klant_id ophalen
$stmt = $conn->prepare("SELECT klant_id FROM werkbonnen WHERE werkbon_id = ? LIMIT 1");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: /werkbonnen.php");
    exit;
}

$klant_id = (int)$wb['klant_id'];

// 2) verwijderen
$del = $conn->prepare("DELETE FROM werkbonnen WHERE werkbon_id = ? LIMIT 1");
$del->bind_param("i", $werkbon_id);
$del->execute();
$del->close();

setFlash("Werkbon verwijderd.", "success");
header("Location: /klant_detail.php?id=" . $klant_id);
exit;
