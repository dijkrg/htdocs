<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';
checkRole(['Admin','Manager']);

$errors = [];

// Volgende personeelsnummer ophalen (1052+)
$res = $conn->query("SELECT MAX(personeelsnummer) AS maxnr FROM medewerkers WHERE personeelsnummer >= 1052");
$row = $res->fetch_assoc();
$nextPersNr = $row && $row['maxnr'] ? $row['maxnr'] + 1 : 1052;

if (isset($_POST['opslaan'])) {
    $voornaam      = trim($_POST['voornaam']);
    $achternaam    = trim($_POST['achternaam']);
    $adres         = trim($_POST['adres']);
    $postcode      = trim($_POST['postcode']);
    $plaats        = trim($_POST['plaats']);
    $telefoon      = trim($_POST['telefoon']);
    $email         = trim($_POST['email']);
    $geboortedatum = $_POST['geboortedatum'] ?? null;
    $rol           = $_POST['rol'];

    if ($voornaam === '' || $achternaam === '' || $email === '' || $rol === '') {
        $errors[] = "Voornaam, achternaam, email en rol zijn verplicht.";
    }

    if (empty($errors)) {
        // Medewerker invoegen
        $stmt = $conn->prepare("
            INSERT INTO medewerkers (personeelsnummer, voornaam, achternaam, adres, postcode, plaats, telefoon, email, geboortedatum, rol)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isssssssss",
            $nextPersNr, $voornaam, $achternaam, $adres, $postcode, $plaats,
            $telefoon, $email, $geboortedatum, $rol
        );

        if ($stmt->execute()) {
            // Token voor wachtwoord reset
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

            $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $email, $token, $expires);
            $stmt2->execute();
            $stmt2->close();

            // Reset-link
            $resetUrl = "https://office.abcbrandbeveiliging.nl/wachtwoord_reset.php?token=$token";

            // Mail versturen
            require_once __DIR__ . '/mail/mailer.php';
            sendMail(
                $email,
                "Welkom bij ABC Brandbeveiliging",
                "
                <p>Beste " . htmlspecialchars($voornaam) . ",</p>
                <p>Je account is aangemaakt. Stel hier je wachtwoord in:</p>
                <p><a href='$resetUrl'>$resetUrl</a></p>
                <p>Deze link is geldig tot: $expires.</p>
                <p>Met vriendelijke groet,<br>ABC Brandbeveiliging</p>
                "
            );

            setFlash("✅ Medewerker succesvol toegevoegd en mail verstuurd.", "success");
            header("Location: medewerkers.php");
            exit;
        } else {
            $errors[] = "Fout bij opslaan: " . $stmt->error;
        }
        $stmt->close();
    }

    foreach ($errors as $err) setFlash($err, "error");
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<div class="page-header">
    <h2>Nieuwe medewerker toevoegen</h2>
    <a href="medewerkers.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-styled">
        <label>Personeelsnummer</label>
        <input type="text" value="<?= $nextPersNr ?>" disabled>

        <label>Voornaam*</label>
        <input type="text" name="voornaam" value="<?= e($_POST['voornaam'] ?? '') ?>" required>

        <label>Achternaam*</label>
        <input type="text" name="achternaam" value="<?= e($_POST['achternaam'] ?? '') ?>" required>

        <label>Adres</label>
        <input type="text" name="adres" value="<?= e($_POST['adres'] ?? '') ?>">

        <label>Postcode</label>
        <input type="text" name="postcode" value="<?= e($_POST['postcode'] ?? '') ?>">

        <label>Plaats</label>
        <input type="text" name="plaats" value="<?= e($_POST['plaats'] ?? '') ?>">

        <label>Telefoon</label>
        <input type="text" name="telefoon" value="<?= e($_POST['telefoon'] ?? '') ?>">

        <label>Email (gebruikersnaam)*</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label>Geboortedatum</label>
        <input type="date" name="geboortedatum" value="<?= e($_POST['geboortedatum'] ?? '') ?>">

        <label>Rol*</label>
        <select name="rol" required>
            <option value="">-- Kies rol --</option>
            <?php foreach (['Admin','Manager','Planning','Monteur'] as $r): ?>
                <option value="<?= $r ?>" <?= (($_POST['rol'] ?? '') === $r ? 'selected' : '') ?>><?= $r ?></option>
            <?php endforeach; ?>
        </select>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">➕ Toevoegen</button>
            <a href="medewerkers.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Nieuwe medewerker toevoegen";
include __DIR__ . "/template/template.php";
