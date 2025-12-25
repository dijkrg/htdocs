<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

if ($_SESSION['user']['rol'] !== 'Manager') {
    setFlash("⛔ Geen rechten om uren af te keuren.", "error");
    header("Location: index.php");
    exit;
}

$uur_id = intval($_GET['id'] ?? 0);

if ($uur_id <= 0) {
    setFlash("Ongeldige uur-ID.", "error");
    header("Location: uren_overzicht.php");
    exit;
}

$manager_id = (int)$_SESSION['user']['id'];

$stmt = $conn->prepare("
    UPDATE urenregistratie
    SET goedgekeurd = -1,
        goedgekeurd_door = ?,
        goedgekeurd_op = NOW()
    WHERE uur_id = ?
");
$stmt->bind_param("ii", $manager_id, $uur_id);

if ($stmt->execute()) {
    setFlash("Uurregistratie is afgekeurd.", "success");
} else {
    setFlash("Fout bij afkeuren: " . $stmt->error, "error");
}

header("Location: uren_overzicht.php");
exit;
