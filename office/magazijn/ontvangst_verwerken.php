<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bestelling_id'])) {
    $bestelID = intval($_POST['bestelling_id']);
    $ontvangen = $_POST['ontvangen'] ?? [];

    foreach ($ontvangen as $regel_id => $aantal) {
        $aantal = intval($aantal);
        if ($aantal <= 0) continue;

        // Regel ophalen uit leveranciersmap-tabel
        $regel = $conn->query("SELECT * FROM bestelling_artikelen WHERE regel_id = $regel_id")->fetch_assoc();
        if (!$regel) continue;

        $nog = $regel['aantal_besteld'] - $regel['aantal_ontvangen'];
        if ($aantal > $nog) {
            setFlash("Aantal groter dan resterend voor regel $regel_id", "error");
            header("Location: voorraad.php?bestelling_id=$bestelID#tab-ontvangsten");
            exit;
        }

        // 1️⃣ Voorraad bijwerken
        $conn->query("
            INSERT INTO voorraad (artikel_id, aantal)
            VALUES ({$regel['artikel_id']}, $aantal)
            ON DUPLICATE KEY UPDATE aantal = aantal + $aantal, laatste_update = NOW()
        ");

        // 2️⃣ Transactie loggen
        $stmt = $conn->prepare("
            INSERT INTO voorraad_transacties (artikel_id, type, aantal, opmerking) 
            VALUES (?, 'ontvangst', ?, ?)
        ");
        $opm = "Ontvangen bestelling #$bestelID";
        $stmt->bind_param("iis", $regel['artikel_id'], $aantal, $opm);
        $stmt->execute();

        // 3️⃣ Bestellingregel bijwerken
        $conn->query("
            UPDATE bestelling_artikelen
            SET aantal_ontvangen = aantal_ontvangen + $aantal,
                status = CASE 
                    WHEN aantal_ontvangen + $aantal >= aantal_besteld THEN 'voltooid'
                    ELSE 'backorder'
                END
            WHERE regel_id = $regel_id
        ");
    }

    // 4️⃣ Bestellingstatus controleren
    $check = $conn->query("
        SELECT COUNT(*) AS openregels
        FROM bestelling_artikelen
        WHERE bestelling_id = $bestelID AND status != 'voltooid'
    ")->fetch_assoc();

    $newStatus = $check['openregels'] == 0 ? 'afgehandeld' : 'gedeeltelijk';
    $conn->query("UPDATE bestellingen SET status = '$newStatus' WHERE bestelling_id = $bestelID");

    setFlash("Ontvangst succesvol verwerkt ✅", "success");
    header("Location: voorraad.php?bestelling_id=$bestelID#tab-ontvangsten");
    exit;
}

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
