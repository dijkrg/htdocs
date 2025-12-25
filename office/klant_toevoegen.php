<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// ✅ Alleen Admin, Manager of Planning
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager','Planning'])) {
    setFlash("Geen toegang tot klanten toevoegen.", "error");
    header("Location: klanten.php");
    exit;
}

// 🧩 Functie: bepaal volgende debiteurnummer
function getNextDebiteurnummer(mysqli $conn): string {
    $startNummer = 15151;
    $res = $conn->query("SELECT MAX(CAST(debiteurnummer AS UNSIGNED)) AS maxnum FROM klanten WHERE debiteurnummer REGEXP '^[0-9]+$'");
    $row = $res->fetch_assoc();
    $huidigMax = (int)($row['maxnum'] ?? 0);
    return (string)max($startNummer, $huidigMax + 1);
}

$volgendDebiteur = getNextDebiteurnummer($conn);
$errors = [];

// 🧾 Formulierverwerking
if (isset($_POST['opslaan'])) {
    $debiteurnummer = trim($_POST['debiteurnummer'] ?? $volgendDebiteur);
    $bedrijfsnaam   = trim($_POST['bedrijfsnaam'] ?? '');
    $contactpersoon = trim($_POST['contactpersoon'] ?? '');
    $telefoonnummer = trim($_POST['telefoonnummer'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $adres          = trim($_POST['adres'] ?? '');
    $postcode       = trim($_POST['postcode'] ?? '');
    $plaats         = trim($_POST['plaats'] ?? '');
    $opmerkingen    = trim($_POST['opmerkingen'] ?? '');

    if ($bedrijfsnaam === '') {
        $errors[] = "Bedrijfsnaam is verplicht.";
    }

    // ✅ Controle op uniek debiteurnummer
    $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM klanten WHERE debiteurnummer = ?");
    $chk->bind_param("s", $debiteurnummer);
    $chk->execute();
    $cnt = $chk->get_result()->fetch_assoc()['cnt'] ?? 0;
    $chk->close();
    if ($cnt > 0) {
        // Als het nummer tóch bezet is → herberekenen
        $debiteurnummer = getNextDebiteurnummer($conn);
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO klanten 
            (debiteurnummer, bedrijfsnaam, contactpersoon, telefoonnummer, email, adres, postcode, plaats, opmerkingen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssss",
            $debiteurnummer,
            $bedrijfsnaam,
            $contactpersoon,
            $telefoonnummer,
            $email,
            $adres,
            $postcode,
            $plaats,
            $opmerkingen
        );

        if ($stmt->execute()) {
            setFlash("✅ Klant succesvol toegevoegd (Debiteur: $debiteurnummer)", "success");
            header("Location: klanten.php");
            exit;
        } else {
            $errors[] = "Fout bij opslaan: " . $stmt->error;
        }
        $stmt->close();
    }

    foreach ($errors as $err) setFlash($err, "error");
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ✅ Pagina layout
$pageTitle = "➕ Nieuwe klant toevoegen";
ob_start();
?>

<div class="page-header">
    <h2>➕ Nieuwe klant toevoegen</h2>
    <div class="header-actions">
        <a href="klanten.php" class="btn btn-secondary">⬅ Terug naar klanten</a>
    </div>
</div>

<div class="card" style="max-width:900px;">
    <form method="post" class="form-table">
        <div class="form-row">
            <label>Debiteurnummer</label>
            <input type="text" name="debiteurnummer" value="<?= e($volgendDebiteur) ?>" readonly style="background:#f0f0f0; font-weight:bold;">
            <small style="color:#666;">Automatisch toegewezen</small>
        </div>
        <div class="form-row">
            <label>Bedrijfsnaam*</label>
            <input type="text" name="bedrijfsnaam" value="<?= e($_POST['bedrijfsnaam'] ?? '') ?>" required>
        </div>
        <div class="form-row"><label>Contactpersoon</label><input type="text" name="contactpersoon" value="<?= e($_POST['contactpersoon'] ?? '') ?>"></div>
        <div class="form-row"><label>Telefoonnummer</label><input type="text" name="telefoonnummer" value="<?= e($_POST['telefoonnummer'] ?? '') ?>"></div>
        <div class="form-row"><label>E-mail</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="form-row"><label>Adres</label><input type="text" name="adres" value="<?= e($_POST['adres'] ?? '') ?>"></div>
        <div class="form-row"><label>Postcode</label><input type="text" name="postcode" value="<?= e($_POST['postcode'] ?? '') ?>"></div>
        <div class="form-row"><label>Plaats</label><input type="text" name="plaats" value="<?= e($_POST['plaats'] ?? '') ?>"></div>
        <div class="form-row">
            <label>Opmerkingen</label>
            <textarea name="opmerkingen" rows="3"><?= e($_POST['opmerkingen'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn btn-accent">💾 Opslaan</button>
            <a href="klanten.php" class="btn btn-secondary">⬅ Annuleren</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
