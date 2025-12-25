<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ✅ Controleer of gebruiker is ingelogd
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Haal medewerker_id op uit sessie
$medewerker_id = $_SESSION['user']['id'] ?? ($_SESSION['medewerker_id'] ?? null);

if (!$medewerker_id) {
    setFlash("Geen medewerker gevonden in sessie.", "error");
    header("Location: login.php");
    exit;
}

// Medewerker ophalen
$stmt = $conn->prepare("SELECT * FROM medewerkers WHERE medewerker_id = ?");
$stmt->bind_param("i", $medewerker_id);
$stmt->execute();
$gebruiker = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gebruiker) {
    setFlash("Medewerker niet gevonden.", "error");
    header("Location: login.php");
    exit;
}

// Opslaan profiel
if (isset($_POST['opslaan'])) {
    $voornaam   = $_POST['voornaam'];
    $achternaam = $_POST['achternaam'];
    $adres      = $_POST['adres'];
    $postcode   = $_POST['postcode'];
    $plaats     = $_POST['plaats'];
    $telefoon   = $_POST['telefoon'];
    $email      = $_POST['email'];

    // Foto upload
    $foto = $gebruiker['foto'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $bestandsnaam = "medewerker_" . $medewerker_id . "." . $ext;
        $pad = __DIR__ . "/uploads/" . $bestandsnaam;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $pad)) {
            $foto = "uploads/" . $bestandsnaam;
        }
    }

    // Wachtwoord (optioneel wijzigen)
    $wachtwoord = $gebruiker['wachtwoord'];
    if (!empty($_POST['wachtwoord']) && $_POST['wachtwoord'] === $_POST['wachtwoord2']) {
        $wachtwoord = password_hash($_POST['wachtwoord'], PASSWORD_DEFAULT);
    }

    // Update query
    $stmt = $conn->prepare("
        UPDATE medewerkers SET 
            voornaam=?, achternaam=?, adres=?, postcode=?, plaats=?, 
            telefoon=?, email=?, foto=?, wachtwoord=? 
        WHERE medewerker_id=?
    ");
    $stmt->bind_param(
        "sssssssssi",
        $voornaam, $achternaam, $adres, $postcode, $plaats,
        $telefoon, $email, $foto, $wachtwoord,
        $medewerker_id
    );

    if ($stmt->execute()) {
        setFlash("Profiel bijgewerkt!", "success");

        // Sessiewaarden ook updaten
        $_SESSION['user']['voornaam'] = $voornaam;
        $_SESSION['user']['achternaam'] = $achternaam;
        $_SESSION['user']['email'] = $email;
    } else {
        setFlash("Fout bij opslaan: " . $stmt->error, "error");
    }
    $stmt->close();

    header("Location: profiel.php");
    exit;
}

// 📌 Uren ophalen voor dit profiel
$q = $conn->prepare("
    SELECT u.*, w.werkbonnummer, us.code AS uursoort_code, us.omschrijving AS uursoort_omschrijving
    FROM werkbon_uren u
    LEFT JOIN werkbonnen w ON u.werkbon_id = w.werkbon_id
    LEFT JOIN uursoorten us ON u.uursoort_id = us.uursoort_id
    WHERE u.monteur_id=?
    ORDER BY u.datum DESC, u.begintijd DESC
    LIMIT 20
");
$q->bind_param("i", $medewerker_id);
$q->execute();
$uren = $q->get_result();

// Content
ob_start();
?>
<div class="page-header">
    <h2>Mijn Profiel</h2>
</div>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-styled">

        <label>Foto:</label>
        <?php if (!empty($gebruiker['foto'])): ?>
            <img src="<?= htmlspecialchars($gebruiker['foto']) ?>" alt="Profielfoto" width="120"><br>
        <?php endif; ?>
        <input type="file" name="foto">

        <label>Voornaam:</label>
        <input type="text" name="voornaam" value="<?= htmlspecialchars($gebruiker['voornaam'] ?? '') ?>" required>

        <label>Achternaam:</label>
        <input type="text" name="achternaam" value="<?= htmlspecialchars($gebruiker['achternaam'] ?? '') ?>" required>

        <label>Adres:</label>
        <input type="text" name="adres" value="<?= htmlspecialchars($gebruiker['adres'] ?? '') ?>">

        <label>Postcode:</label>
        <input type="text" name="postcode" value="<?= htmlspecialchars($gebruiker['postcode'] ?? '') ?>">

        <label>Plaats:</label>
        <input type="text" name="plaats" value="<?= htmlspecialchars($gebruiker['plaats'] ?? '') ?>">

        <label>Telefoon:</label>
        <input type="text" name="telefoon" value="<?= htmlspecialchars($gebruiker['telefoon'] ?? '') ?>">

        <label>Email (gebruikersnaam):</label>
        <input type="email" name="email" value="<?= htmlspecialchars($gebruiker['email'] ?? '') ?>" required>

        <label>Rol:</label>
        <input type="text" value="<?= htmlspecialchars($gebruiker['rol'] ?? '') ?>" disabled>

        <label>Nieuw wachtwoord:</label>
        <input type="password" name="wachtwoord">

        <label>Wachtwoord (controle):</label>
        <input type="password" name="wachtwoord2">

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">Opslaan</button>
        </div>
    </form>
</div>

<h3>Mijn laatste urenregistraties</h3>
<div class="card">
    <table class="data-table small-table">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Werkbon</th>
                <th>Uursoort</th>
                <th>Begintijd</th>
                <th>Eindtijd</th>
                <th>Uren</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($u = $uren->fetch_assoc()): ?>
                <tr>
                    <td><?= date("d-m-Y", strtotime($u['datum'])) ?></td>
                    <td><?= htmlspecialchars($u['werkbonnummer'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['uursoort_code']." - ".$u['uursoort_omschrijving']) ?></td>
                    <td><?= substr($u['begintijd'],0,5) ?></td>
                    <td><?= substr($u['eindtijd'],0,5) ?></td>
                    <td><?= number_format($u['totaal_uren'],2,',','.') ?></td>
                    <td>
                        <?php
                        if ($u['goedgekeurd'] === 'in_behandeling') echo "⏳ In behandeling";
                        elseif ($u['goedgekeurd'] === 'goedgekeurd') echo "✅ Goedgekeurd";
                        else echo "❌ Afgewezen";
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Mijn Profiel";
include __DIR__ . "/template/template.php";
