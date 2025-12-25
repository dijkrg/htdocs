<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
requireRole(['Admin','Manager']);

$medewerker_id = intval($_GET['id'] ?? 0);

// Medewerker ophalen
$stmt = $conn->prepare("SELECT * FROM medewerkers WHERE medewerker_id=?");
$stmt->bind_param("i", $medewerker_id);
$stmt->execute();
$res = $stmt->get_result();
$medewerker = $res->fetch_assoc();
$stmt->close();

if (!$medewerker) {
    setFlash("Medewerker niet gevonden.", "error");
    header("Location: medewerkers.php");
    exit;
}

// Resetlink versturen
if (isset($_POST['verstuur_resetlink'])) {
    // Oude tokens verwijderen
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email=?");
    $stmt->bind_param("s", $medewerker['email']);
    $stmt->execute();

    // Nieuw token genereren
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+1 day"));

    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, token, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("sss", $medewerker['email'], $token, $expires);
    $stmt->execute();

    $resetLink = "https://office.abcbrandbeveiliging.nl/wachtwoord_reset.php?token=" . urlencode($token);

    // Mail sturen
    require_once __DIR__ . '/mail/mailer.php';
    sendMail(
        $medewerker['email'],
        "Wachtwoord reset",
        "<p>Beste " . htmlspecialchars($medewerker['voornaam']) . ",</p>
         <p>Er is een nieuw wachtwoord-resetlink aangemaakt. Klik op onderstaande link om een nieuw wachtwoord in te stellen:</p>
         <p><a href='$resetLink'>$resetLink</a></p>
         <p>Deze link is geldig tot: $expires</p>"
    );

    setFlash("✅ Nieuwe resetlink verzonden naar " . htmlspecialchars($medewerker['email']), "success");
    header("Location: medewerker_bewerk.php?id=" . $medewerker_id);
    exit;
}

// Opslaan
if (isset($_POST['opslaan'])) {
    $voornaam      = $_POST['voornaam'];
    $achternaam    = $_POST['achternaam'];
    $adres         = $_POST['adres'];
    $postcode      = $_POST['postcode'];
    $plaats        = $_POST['plaats'];
    $telefoon      = $_POST['telefoon'];
    $email         = $_POST['email'];
    $rol           = $_POST['rol'];
    $geboortedatum = $_POST['geboortedatum'];

    // Foto upload
    $foto = $medewerker['foto'];
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $bestandsnaam = "medewerker_" . $medewerker_id . "." . $ext;
        $pad = __DIR__ . "/uploads/" . $bestandsnaam;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $pad)) {
            $foto = "uploads/" . $bestandsnaam;
        }
    }

    // Wachtwoord wijzigen (optioneel)
    $wachtwoord = $medewerker['wachtwoord'];
    if (!empty($_POST['wachtwoord']) && $_POST['wachtwoord'] === $_POST['wachtwoord2']) {
        $wachtwoord = password_hash($_POST['wachtwoord'], PASSWORD_DEFAULT);
    }

    $stmt = $conn->prepare("
        UPDATE medewerkers SET 
            voornaam=?, achternaam=?, adres=?, postcode=?, plaats=?, telefoon=?, 
            email=?, foto=?, geboortedatum=?, rol=?, wachtwoord=?
        WHERE medewerker_id=?
    ");
    $stmt->bind_param(
        "sssssssssssi",
        $voornaam, $achternaam, $adres, $postcode, $plaats, $telefoon,
        $email, $foto, $geboortedatum, $rol, $wachtwoord,
        $medewerker_id
    );

    if ($stmt->execute()) {
        setFlash("Medewerker succesvol bijgewerkt!", "success");
    } else {
        setFlash("Fout bij opslaan: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: medewerkers.php");
    exit;
}

// Content
ob_start();
?>
<h2>Medewerker bewerken</h2>

<form method="post" enctype="multipart/form-data" class="form-styled">
    <label>Voornaam:</label>
    <input type="text" name="voornaam" value="<?= htmlspecialchars($medewerker['voornaam']) ?>" required>

    <label>Achternaam:</label>
    <input type="text" name="achternaam" value="<?= htmlspecialchars($medewerker['achternaam']) ?>" required>

    <label>Adres:</label>
    <input type="text" name="adres" value="<?= htmlspecialchars($medewerker['adres']) ?>">

    <label>Postcode:</label>
    <input type="text" name="postcode" value="<?= htmlspecialchars($medewerker['postcode']) ?>">

    <label>Plaats:</label>
    <input type="text" name="plaats" value="<?= htmlspecialchars($medewerker['plaats']) ?>">

    <label>Telefoon:</label>
    <input type="text" name="telefoon" value="<?= htmlspecialchars($medewerker['telefoon']) ?>">

    <label>Email (gebruikersnaam):</label>
    <input type="email" name="email" value="<?= htmlspecialchars($medewerker['email']) ?>" required>

    <label>Geboortedatum:</label>
    <input type="date" name="geboortedatum" value="<?= htmlspecialchars($medewerker['geboortedatum']) ?>">

    <label>Rol:</label>
    <select name="rol" required>
        <?php foreach (['Admin','Manager','Planning','Monteur'] as $r): ?>
            <option value="<?= $r ?>" <?= ($medewerker['rol'] === $r ? 'selected' : '') ?>><?= $r ?></option>
        <?php endforeach; ?>
    </select>

    <label>Foto:</label>
    <?php if (!empty($medewerker['foto'])): ?>
        <img src="<?= htmlspecialchars($medewerker['foto']) ?>" alt="Foto" width="100"><br>
    <?php endif; ?>
    <input type="file" name="foto">

    <label>Nieuw wachtwoord:</label>
    <input type="password" name="wachtwoord">

    <label>Wachtwoord (controle):</label>
    <input type="password" name="wachtwoord2">

    <div class="form-actions">
        <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
        <a href="medewerkers.php" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<hr>

<form method="post">
    <button type="submit" name="verstuur_resetlink" class="btn btn-warning">
        🔑 Nieuwe resetlink sturen
    </button>
</form>

<?php
$content = ob_get_clean();
$pageTitle = "Medewerker bewerken";
include __DIR__ . "/template/template.php";
