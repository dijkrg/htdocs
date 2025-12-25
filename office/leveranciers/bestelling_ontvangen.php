<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager (optioneel)
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: ../login.php");
    exit;
}

$bestelling_id = (int)$_POST['bestelling_id'];

// Bestelling ophalen
$bestelling = $conn->query("SELECT * FROM bestellingen WHERE bestelling_id = $bestelling_id")->fetch_assoc();
if (!$bestelling) {
    setFlash("Bestelling niet gevonden.", "error");
    header("Location: bestellingen.php");
    exit;
}

// Artikelen ophalen
$regels = $conn->query("
    SELECT artikel_id, aantal
    FROM bestelling_artikelen
    WHERE bestelling_id = $bestelling_id
");

// 📌 Voor nu boeken we alles naar 1 standaard magazijn (kan later uitgebreid worden)
$standaard_magazijn_id = 1; // TODO: configurabel maken

$conn->begin_transaction();

try {
    while ($r = $regels->fetch_assoc()) {
        $artikel_id = (int)$r['artikel_id'];
        $aantal = (int)$r['aantal'];

        // Voorraad updaten of toevoegen
        $check = $conn->query("SELECT id, aantal FROM voorraad_magazijn WHERE magazijn_id = $standaard_magazijn_id AND artikel_id = $artikel_id");
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $nieuwAantal = $row['aantal'] + $aantal;
            $conn->query("UPDATE voorraad_magazijn SET aantal = $nieuwAantal WHERE id = {$row['id']}");
        } else {
            $conn->query("INSERT INTO voorraad_magazijn (magazijn_id, artikel_id, aantal) VALUES ($standaard_magazijn_id, $artikel_id, $aantal)");
        }

        // Transactie loggen
        $conn->query("
            INSERT INTO voorraad_transacties (magazijn_id, artikel_id, aantal, type, datum, referentie)
            VALUES ($standaard_magazijn_id, $artikel_id, $aantal, 'Ontvangst bestelling', NOW(), 'Bestelling #$bestelling_id')
        ");
    }

    // Status bestelling bijwerken
    $conn->query("UPDATE bestellingen SET status = 'Ontvangen' WHERE bestelling_id = $bestelling_id");

    $conn->commit();
    setFlash("Bestelling succesvol ontvangen en voorraad bijgewerkt.", "success");

} catch (Exception $e) {
    $conn->rollback();
    setFlash("Fout bij boeken bestelling: " . $e->getMessage(), "error");
}

header("Location: bestelling_detail.php?id=$bestelling_id");
exit;

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';

