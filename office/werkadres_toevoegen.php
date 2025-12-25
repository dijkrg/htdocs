<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// Ingelogd?
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$klant_id = intval($_GET['klant_id'] ?? 0);
if ($klant_id <= 0) {
    setFlash("Ongeldige klant-ID.", "error");
    header("Location: klanten.php");
    exit;
}

// Klant ophalen
$stmt = $conn->prepare("SELECT debiteurnummer, bedrijfsnaam FROM klanten WHERE klant_id = ?");
$stmt->bind_param("i", $klant_id);
$stmt->execute();
$klant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$klant) {
    setFlash("Klant niet gevonden.", "error");
    header("Location: klanten.php");
    exit;
}

// Handmatige invoer — waarde wordt overgeschreven nadat POST plaatsvindt
$werkadresnummer = "";

// POST verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $werkadresnummer = trim($_POST['werkadresnummer']);
    $bedrijfsnaam    = trim($_POST['bedrijfsnaam']);
    $adres           = trim($_POST['adres']);
    $postcode        = trim($_POST['postcode']);
    $plaats          = trim($_POST['plaats']);
    $contactpersoon  = trim($_POST['contactpersoon']);
    $telefoon        = trim($_POST['telefoon']);
    $email           = trim($_POST['email']);
    $opmerkingen     = trim($_POST['opmerkingen']);

    if ($werkadresnummer === "") {
        setFlash("⚠️ Vul een werkadresnummer in.", "error");
    } else {

        // ‼️ Controle: werkadresnummer moet uniek zijn binnen deze klant
        $stmt = $conn->prepare("
            SELECT werkadres_id 
            FROM werkadressen 
            WHERE klant_id = ? AND werkadresnummer = ?
        ");
        $stmt->bind_param("is", $klant_id, $werkadresnummer);
        $stmt->execute();
        $check = $stmt->get_result();
        $stmt->close();

        if ($check->num_rows > 0) {
            setFlash("❌ Dit werkadresnummer bestaat al bij deze klant.", "error");
        } else {
            // Toevoegen
            $stmt = $conn->prepare("
                INSERT INTO werkadressen 
                (werkadresnummer, klant_id, bedrijfsnaam, adres, postcode, plaats, contactpersoon, telefoon, email, opmerkingen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sissssssss",
                $werkadresnummer, $klant_id, $bedrijfsnaam, $adres, $postcode,
                $plaats, $contactpersoon, $telefoon, $email, $opmerkingen
            );

            if ($stmt->execute()) {
                setFlash("✅ Werkadres toegevoegd.", "success");
                header("Location: klant_detail.php?id=" . $klant_id);
                exit;
            } else {
                setFlash("❌ Fout: " . $stmt->error, "error");
            }
            $stmt->close();
        }
    }
}

$pageTitle = "Nieuw werkadres toevoegen";
ob_start();
?>

<div class="page-header">
    <h2>➕ Nieuw werkadres</h2>
    <a href="klant_detail.php?id=<?= $klant_id ?>" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <h3>Werkadres gegevens</h3>

    <form method="post">

        <label>Werkadresnummer*</label>
        <input type="text" name="werkadresnummer" 
               value="<?= htmlspecialchars($werkadresnummer) ?>"
               placeholder="Bijv. <?= $klant['debiteurnummer'] ?>-01">

        <label>Bedrijfsnaam*</label>
        <input type="text" name="bedrijfsnaam" required>

        <label>Adres*</label>
        <input type="text" name="adres" required>

        <div class="two-cols">
            <div>
                <label>Postcode*</label>
                <input type="text" name="postcode" required>
            </div>
            <div>
                <label>Plaats*</label>
                <input type="text" name="plaats" required>
            </div>
        </div>

        <label>Contactpersoon</label>
        <input type="text" name="contactpersoon">

        <label>Telefoon</label>
        <input type="text" name="telefoon">

        <label>E-mail</label>
        <input type="email" name="email">

        <label>Opmerkingen</label>
        <textarea name="opmerkingen"></textarea>

        <div class="form-actions">
            <button type="submit" class="btn">💾 Opslaan</button>
            <a href="klant_detail.php?id=<?= $klant_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>

    </form>
</div>

<style>
.two-cols { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
input, textarea { width:100%; padding:7px; border:1px solid #ccc; border-radius:6px; }
label { margin-top:10px; font-weight:600; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
