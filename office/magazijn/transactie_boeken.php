<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot transacties.", "error");
    header("Location: ../index.php");
    exit;
}

// ─────────────────────────────
// Validatie ID
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
    SELECT t.transactie_id, t.artikel_id, t.magazijn_id, t.type, t.aantal, t.status, a.omschrijving
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

if ($transactie['status'] === 'geboekt') {
    setFlash("Deze transactie is al geboekt.", "info");
    header("Location: transacties.php");
    exit;
}

$artikel_id  = (int)$transactie['artikel_id'];
$magazijn_id = (int)$transactie['magazijn_id'];
$type        = $transactie['type'];
$aantal      = (int)$transactie['aantal'];

// ─────────────────────────────
// Huidige voorraad ophalen (indien aanwezig)
// ─────────────────────────────
$res = $conn->prepare("
    SELECT aantal 
    FROM voorraad_magazijn 
    WHERE artikel_id = ? AND magazijn_id = ?
");
$res->bind_param("ii", $artikel_id, $magazijn_id);
$res->execute();
$res->bind_result($huidig);
$res->fetch();
$res->close();

if ($huidig === null) $huidig = 0;
$nieuwAantal = $huidig;

// ─────────────────────────────
// Voorraad aanpassen per type
// ─────────────────────────────
switch ($type) {
    case 'ontvangst':
        $nieuwAantal += $aantal;
        break;
    case 'verkoop':
    case 'uitgifte':
        $nieuwAantal -= $aantal;
        break;
    case 'correctie':
        $nieuwAantal = $aantal;
        break;
    case 'retour':
        $nieuwAantal += $aantal;
        break;
    case 'bestelling':
        // Alleen markeren, geen voorraadwijziging
        break;
    default:
        break;
}

// ─────────────────────────────
// Updaten of toevoegen met ON DUPLICATE KEY
// ─────────────────────────────
$upd = $conn->prepare("
    INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
        aantal = VALUES(aantal),
        laatste_update = NOW()
");
$upd->bind_param("iii", $artikel_id, $magazijn_id, $nieuwAantal);
$upd->execute();

// ─────────────────────────────
// Transactie-status bijwerken
// ─────────────────────────────
$mark = $conn->prepare("
    UPDATE voorraad_transacties 
    SET status = 'geboekt', geboekt_op = NOW() 
    WHERE transactie_id = ?
");
$mark->bind_param("i", $transactie_id);
$mark->execute();

// ─────────────────────────────
// Flash & redirect
// ─────────────────────────────
setFlash("Transactie voor artikel \"" . htmlspecialchars($transactie['omschrijving']) . "\" is succesvol geboekt ✅", "success");
header("Location: transacties.php");
exit;
