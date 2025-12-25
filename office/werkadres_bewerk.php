<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// 🔐 Login check
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$werkadres_id = intval($_GET['id'] ?? 0);
if ($werkadres_id <= 0) {
    setFlash("Ongeldig werkadres-ID.", "error");
    header("Location: klanten.php");
    exit;
}

// 🧩 Werkadres ophalen
$stmt = $conn->prepare("SELECT * FROM werkadressen WHERE werkadres_id = ?");
$stmt->bind_param("i", $werkadres_id);
$stmt->execute();
$wa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wa) {
    setFlash("Werkadres niet gevonden.", "error");
    header("Location: klanten.php");
    exit;
}

$klant_id = $wa['klant_id'];

// 🧩 Klant ophalen
$stmt = $conn->prepare("SELECT debiteurnummer, bedrijfsnaam FROM klanten WHERE klant_id = ?");
$stmt->bind_param("i", $klant_id);
$stmt->execute();
$klant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ==============================
// POST verwerken
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $werkadresnummer  = trim($_POST['werkadresnummer']);
    $bedrijfsnaam     = trim($_POST['bedrijfsnaam']);
    $adres            = trim($_POST['adres']);
    $postcode         = trim($_POST['postcode']);
    $plaats           = trim($_POST['plaats']);
    $contactpersoon   = trim($_POST['contactpersoon']);
    $telefoon         = trim($_POST['telefoon']);
    $email            = trim($_POST['email']);
    $opmerkingen      = trim($_POST['opmerkingen']);

    // ❗ Uniek per KLANT controleren
    $chk = $conn->prepare("
        SELECT werkadres_id 
        FROM werkadressen 
        WHERE klant_id = ? AND werkadresnummer = ? AND werkadres_id <> ?
    ");
    $chk->bind_param("isi", $klant_id, $werkadresnummer, $werkadres_id);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($exists) {
        setFlash("❌ Werkadresnummer bestaat al voor deze klant.", "error");
    } else {
        // UPDATE uitvoeren
        $stmt = $conn->prepare("
            UPDATE werkadressen SET
                werkadresnummer = ?,
                bedrijfsnaam = ?,
                adres = ?,
                postcode = ?,
                plaats = ?,
                contactpersoon = ?,
                telefoon = ?,
                email = ?,
                opmerkingen = ?
            WHERE werkadres_id = ?
        ");

        $stmt->bind_param(
            "sssssssssi",
            $werkadresnummer,
            $bedrijfsnaam,
            $adres,
            $postcode,
            $plaats,
            $contactpersoon,
            $telefoon,
            $email,
            $opmerkingen,
            $werkadres_id
        );

        if ($stmt->execute()) {
            setFlash("✅ Werkadres succesvol bijgewerkt.", "success");
            header("Location: klant_detail.php?id=" . $klant_id);
            exit;
        } else {
            setFlash("❌ Fout bij opslaan: " . $stmt->error, "error");
        }

        $stmt->close();
    }

    // Bij foutmelding: waarden terug in form
    $wa = array_merge($wa, $_POST);
}

$pageTitle = "Werkadres bewerken";
ob_start();
?>

<div class="page-header">
    <h2>🏢 Werkadres bewerken</h2>
    <div class="header-actions">
        <a href="klant_detail.php?id=<?= $klant_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<?php showFlash(); ?>

<div class="card" style="max-width:900px;margin:auto;">
    <h3>Klant</h3>
    <p><strong><?= htmlspecialchars($klant['debiteurnummer']) ?> - <?= htmlspecialchars($klant['bedrijfsnaam']) ?></strong></p>

    <form method="post" class="two-column-form" style="margin-top:15px;">

        <!-- LINKER kolom -->
        <div>
            <label>Werkadresnummer*</label>
            <input type="text" name="werkadresnummer" required 
                   value="<?= htmlspecialchars($wa['werkadresnummer']) ?>">

            <label>Bedrijfsnaam*</label>
            <input type="text" name="bedrijfsnaam" required 
                   value="<?= htmlspecialchars($wa['bedrijfsnaam']) ?>">

            <label>Adres*</label>
            <input type="text" name="adres" required 
                   value="<?= htmlspecialchars($wa['adres']) ?>">

            <label>Postcode*</label>
            <input type="text" name="postcode" required 
                   value="<?= htmlspecialchars($wa['postcode']) ?>">

            <label>Plaats*</label>
            <input type="text" name="plaats" required 
                   value="<?= htmlspecialchars($wa['plaats']) ?>">
        </div>

        <!-- RECHTER kolom -->
        <div>
            <label>Contactpersoon</label>
            <input type="text" name="contactpersoon" 
                   value="<?= htmlspecialchars($wa['contactpersoon']) ?>">

            <label>Telefoon</label>
            <input type="text" name="telefoon" 
                   value="<?= htmlspecialchars($wa['telefoon']) ?>">

            <label>E-mail</label>
            <input type="email" name="email" 
                   value="<?= htmlspecialchars($wa['email']) ?>">

            <label>Opmerkingen</label>
            <textarea name="opmerkingen" rows="5"><?= htmlspecialchars($wa['opmerkingen']) ?></textarea>
        </div>

        <div style="grid-column:1/-1; margin-top:20px; display:flex; gap:10px;">
            <button type="submit" class="btn">💾 Opslaan</button>
            <a href="klant_detail.php?id=<?= $klant_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>

    </form>
</div>

<style>
.two-column-form {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}
label { font-weight:600; margin-top:10px; display:block; }
input, textarea {
    width:100%;
    padding:7px;
    border:1px solid #ccc;
    border-radius:6px;
    font-size:14px;
}
textarea { resize:vertical; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
