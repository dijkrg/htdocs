<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id     = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_uur_id = (int)($_GET['id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_uur_id <= 0) {
    setFlash("Ongeldige registratie.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT werkbon_uur_id, werkbon_id, monteur_id, goedgekeurd
    FROM werkbon_uren
    WHERE werkbon_uur_id = ? AND monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $werkbon_uur_id, $monteur_id);
$stmt->execute();
$uur = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$uur) {
    setFlash("Uurregel niet gevonden.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

$werkbon_id = (int)($uur['werkbon_id'] ?? 0);
if ($werkbon_id <= 0) {
    setFlash("Werkbon niet gevonden bij deze uurregel.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

if ((string)($uur['goedgekeurd'] ?? '') === 'goedgekeurd') {
    setFlash("Goedgekeurde uren kunnen niet worden verwijderd.", "error");
    header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#uren");
    exit;
}

$del = $conn->prepare("
    DELETE FROM werkbon_uren
    WHERE werkbon_uur_id = ? AND monteur_id = ?
    LIMIT 1
");
$del->bind_param("ii", $werkbon_uur_id, $monteur_id);

if ($del->execute()) {
    setFlash("Uurregel verwijderd.", "success");
} else {
    setFlash("Fout bij verwijderen: " . $del->error, "error");
}
$del->close();

header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#uren");
exit;
