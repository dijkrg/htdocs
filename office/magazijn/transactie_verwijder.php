<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot voorraadtransacties.", "error");
    header("Location: ../index.php");
    exit;
}

// ─────────────────────────────
// Validatie transactie ID
// ─────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldige transactie.", "error");
    header("Location: transacties.php");
    exit;
}
$transactie_id = (int)$_GET['id'];

// ─────────────────────────────
// Transactie ophalen
// ─────────────────────────────
$stmt = $conn->prepare("
    SELECT t.transactie_id, t.artikel_id, t.type, t.aantal, a.omschrijving
    FROM voorraad_transacties t
    JOIN artikelen a ON a.artikel_id = t.artikel_id
    WHERE t.transactie_id = ?
");
$stmt->bind_param("i", $transactie_id);
$stmt->execute();
$transactie = $stmt->get_result()->fetch_assoc();

if (!$transactie) {
    setFlash("Transactie niet gevonden.", "error");
    header("Location: transacties.php");
    exit;
}

$artikel_id = (int)$transactie['artikel_id'];
$type       = $transactie['type'];
$aantal     = (int)$transactie['aantal'];

// ─────────────────────────────
// Voorraad herstellen
// ─────────────────────────────
$res = $conn->prepare("SELECT aantal FROM voorraad WHERE artikel_id = ?");
$res->bind_param("i", $artikel_id);
$res->execute();
$res->bind_result($huidig);
$res->fetch();
$res->close();

$nieuwAantal = $huidig;

switch ($type) {
    case 'ontvangst':
        $nieuwAantal -= $aantal;
        break;
    case 'uitgifte':
        $nieuwAantal += $aantal;
        break;
    case 'correctie':
        // Correctie herstellen kan niet exact — geen vorige waarde bekend.
        // Hier kiezen we voor geen wijziging, enkel verwijderen.
        break;
    case 'overboeking':
        $nieuwAantal += $aantal;
        break;
}

// Update voorraad (indien relevant)
if ($type !== 'correctie') {
    $upd = $conn->prepare("UPDATE voorraad SET aantal = ?, laatste_update = NOW() WHERE artikel_id = ?");
    $upd->bind_param("ii", $nieuwAantal, $artikel_id);
    $upd->execute();
}

// ─────────────────────────────
// Transactie verwijderen
// ─────────────────────────────
$del = $conn->prepare("DELETE FROM voorraad_transacties WHERE transactie_id = ?");
$del->bind_param("i", $transactie_id);
$del->execute();

// ─────────────────────────────
// Afsluiting
// ─────────────────────────────
setFlash("Transactie voor artikel \"" . htmlspecialchars($transactie['omschrijving']) . "\" is verwijderd en voorraad is hersteld ✅", "success");
header("Location: transacties.php");
exit;
