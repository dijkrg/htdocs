<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin mag instellingen beheren
if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang tot mailinstellingen.", "error");
    header("Location: ../index.php");
    exit;
}

// Ophalen huidige instellingen
$res = $conn->query("SELECT * FROM mailinstellingen LIMIT 1");
$config = $res->fetch_assoc();

// Opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    $host        = trim($_POST['host']);
    $poort       = intval($_POST['poort']);
    $gebruiker   = trim($_POST['gebruikersnaam']);
    $wachtwoord  = trim($_POST['wachtwoord']);
    $van_naam    = trim($_POST['van_naam']);
    $van_email   = trim($_POST['van_email']);
    $beveiliging = trim($_POST['beveiliging']);

    if ($config) {
        $stmt = $conn->prepare("UPDATE mailinstellingen SET host=?, poort=?, gebruikersnaam=?, wachtwoord=?, van_naam=?, van_email=?, beveiliging=? WHERE id=?");
        $stmt->bind_param("sisssssi", $host, $poort, $gebruiker, $wachtwoord, $van_naam, $van_email, $beveiliging, $config['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO mailinstellingen (host, poort, gebruikersnaam, wachtwoord, van_naam, van_email, beveiliging) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sisssss", $host, $poort, $gebruiker, $wachtwoord, $van_naam, $van_email, $beveiliging);
    }

    if ($stmt->execute()) {
        setFlash("✅ Mailinstellingen opgeslagen.", "success");
        header("Location: mailinstellingen.php");
        exit;
    } else {
        setFlash("Fout bij opslaan: " . $stmt->error, "error");
    }
}

// Testmail verzenden
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testmail'])) {
    require_once __DIR__ . '/mailer.php';
    $ontvanger = trim($_POST['ontvanger']);
    if (filter_var($ontvanger, FILTER_VALIDATE_EMAIL)) {
        $result = sendMail($ontvanger, "Testmail ABC Brand", "<p>Dit is een testmail vanuit het systeem.</p>");
        $testResult = $result === true 
            ? "✅ Testmail succesvol verstuurd naar: $ontvanger" 
            : "❌ Fout bij verzenden: " . htmlspecialchars($result);
    } else {
        $testResult = "❌ Ongeldig e-mailadres.";
    }
}

$pageTitle = "Mailinstellingen";
ob_start();
?>
<div class="page-header">
    <h2>Mailinstellingen</h2>
</div>

<div class="card" style="max-width:600px;">
    <form method="post" class="form-styled">
        <label>SMTP Host</label>
        <input type="text" name="host" value="<?= htmlspecialchars($config['host'] ?? '') ?>" required>

        <label>Poort</label>
        <input type="number" name="poort" value="<?= htmlspecialchars($config['poort'] ?? 465) ?>" required>

        <label>Gebruikersnaam</label>
        <input type="text" name="gebruikersnaam" value="<?= htmlspecialchars($config['gebruikersnaam'] ?? '') ?>" required>

        <label>Wachtwoord</label>
        <input type="password" name="wachtwoord" value="<?= htmlspecialchars($config['wachtwoord'] ?? '') ?>" required>

        <label>Van naam</label>
        <input type="text" name="van_naam" value="<?= htmlspecialchars($config['van_naam'] ?? '') ?>" required>

        <label>Van e-mail</label>
        <input type="email" name="van_email" value="<?= htmlspecialchars($config['van_email'] ?? '') ?>" required>

        <label>Beveiliging</label>
        <select name="beveiliging">
            <?php foreach (['none','ssl','tls'] as $opt): ?>
                <option value="<?= $opt ?>" <?= (($config['beveiliging'] ?? '') === $opt) ? 'selected' : '' ?>>
                    <?= strtoupper($opt) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
        </div>
    </form>
</div>

<br>

<div class="card" style="max-width:600px;">
    <h3>📧 Testmail verzenden</h3>
    <?php if ($testResult): ?>
        <p><?= $testResult ?></p>
    <?php endif; ?>
    <form method="post" class="form-styled">
        <label>Ontvanger</label>
        <input type="email" name="ontvanger" placeholder="bijv. test@abcbrandbeveiliging.nl" required>
        <div class="form-actions">
            <button type="submit" name="testmail" class="btn">Verstuur testmail</button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
