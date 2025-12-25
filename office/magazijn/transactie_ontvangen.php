<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// 🔒 Beveiliging
if (!isset($_SESSION['user'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../login.php");
    exit;
}

$bestelling_id = intval($_POST['bestelling_id'] ?? 0);
if ($bestelling_id <= 0) {
    setFlash("Ongeldige bestelling.", "error");
    header("Location: ../leveranciers/bestellingen.php");
    exit;
}

// 📍 Altijd boeken op hoofdmagazijn
$magazijn_id   = 1;
$medewerker_id = $_SESSION['user']['id'] ?? null;
$aantalOntvangenRegels = 0;

// 🧾 Ontvangstregels verwerken
foreach ($_POST['ontvangen'] as $regel_id => $aantalOntvangen) {
    $aantalOntvangen = intval($aantalOntvangen);
    if ($aantalOntvangen <= 0) continue;

    $regel = $conn->query("
        SELECT artikel_id, aantal, IFNULL(aantal_ontvangen, 0) AS al_ontvangen
        FROM bestelling_artikelen
        WHERE id = {$regel_id}
    ")->fetch_assoc();

    if (!$regel) continue;

    $artikel_id = (int)$regel['artikel_id'];
    $nieuwOntvangen = $regel['al_ontvangen'] + $aantalOntvangen;

    // ✅ Update aantal ontvangen in bestelling
    $conn->query("
        UPDATE bestelling_artikelen
        SET aantal_ontvangen = {$nieuwOntvangen}
        WHERE id = {$regel_id}
    ");

    // ✅ Voeg toe of werk voorraad bij in magazijn (altijd hoofdmagazijn)
    $stmt = $conn->prepare("
        INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            aantal = aantal + VALUES(aantal),
            laatste_update = NOW()
    ");
    $stmt->bind_param("iii", $artikel_id, $magazijn_id, $aantalOntvangen);
    $stmt->execute();
    $stmt->close();

    // ✅ Verhoog voorraad direct in ARTIKELEN
    $updateArt = $conn->prepare("
        UPDATE artikelen 
        SET voorraad = voorraad + ?
        WHERE artikel_id = ?
    ");
    $updateArt->bind_param("ii", $aantalOntvangen, $artikel_id);
    $updateArt->execute();
    $updateArt->close();

    // ✅ Transactie loggen
    $opmerking = "Ontvangst bestelling #{$bestelling_id}";
    $stmt = $conn->prepare("
        INSERT INTO voorraad_transacties 
            (artikel_id, magazijn_id, aantal, type, referentie, medewerker_id, opmerking, status, geboekt_op)
        VALUES (?, ?, ?, 'ontvangst', ?, ?, ?, 'geboekt', NOW())
    ");
    $stmt->bind_param("iiisis", $artikel_id, $magazijn_id, $aantalOntvangen, $bestelling_id, $medewerker_id, $opmerking);
    $stmt->execute();
    $stmt->close();

    $aantalOntvangenRegels++;
}

// 📦 Bestellingstatus bijwerken
$status = $conn->query("
    SELECT 
        SUM(CASE WHEN aantal_ontvangen = 0 THEN 1 ELSE 0 END) AS open,
        SUM(CASE WHEN aantal_ontvangen < aantal AND aantal_ontvangen > 0 THEN 1 ELSE 0 END) AS gedeeltelijk
    FROM bestelling_artikelen
    WHERE bestelling_id = {$bestelling_id}
")->fetch_assoc();

if ($status['open'] == 0 && $status['gedeeltelijk'] == 0) {
    $conn->query("UPDATE bestellingen SET status='afgehandeld' WHERE bestelling_id={$bestelling_id}");
} elseif ($status['gedeeltelijk'] > 0) {
    $conn->query("UPDATE bestellingen SET status='gedeeltelijk' WHERE bestelling_id={$bestelling_id}");
} else {
    $conn->query("UPDATE bestellingen SET status='open' WHERE bestelling_id={$bestelling_id}");
}

// ✅ Klaar
if ($aantalOntvangenRegels > 0) {
    setFlash("Ontvangst succesvol verwerkt ✅ (hoofdlager)", "success");
} else {
    setFlash("Geen artikelen ontvangen of geen wijziging uitgevoerd.", "warning");
}

header("Location: ../leveranciers/bestelling_detail.php?id={$bestelling_id}");
exit;
?>
