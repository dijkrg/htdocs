<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);
$object_id  = (int)($_GET['object_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0 || $object_id <= 0) {
    setFlash("Ongeldige invoer.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

// Ownership check werkbon
$stmt = $conn->prepare("SELECT werkbon_id, monteur_id FROM werkbonnen WHERE werkbon_id = ? LIMIT 1");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

// Koppelen (voorkom dubbele)
$ins = $conn->prepare("
    INSERT INTO werkbon_objecten (werkbon_id, object_id)
    SELECT ?, ?
    FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1 FROM werkbon_objecten WHERE werkbon_id = ? AND object_id = ?
    )
");
$ins->bind_param("iiii", $werkbon_id, $object_id, $werkbon_id, $object_id);
$ins->execute();
$ins->close();

setFlash("Object gekoppeld aan werkbon.", "success");
header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id . "#objecten");
exit;
