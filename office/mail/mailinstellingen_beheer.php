<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

checkRole(['Admin']); // Alleen Admin mag deze pagina zien

// Ophalen huidige instellingen
$res = $conn->query("SELECT * FROM mailinstellingen LIMIT 1");
$instelling = $res->fetch_assoc();

// Opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host          = trim($_POST['host']);
    $poort         = intval($_POST['poort']);
    $gebruikersnaam= trim($_POST['gebruikersnaam']);
    $wachtwoord    = trim($_POST['wachtwoord']);
    $van_naam      = trim($_POST['van_naam']);
    $van_email     = trim($_POST['van_email']);
    $beveiliging   = trim($_POST['beveiliging']);

    if ($instelling) {
        // Update bestaande rij
        $stmt = $conn->prepare("
            UPDATE mailinstellingen
            SET host=?, poort=?, gebruikersnaam=?, wachtwoord=?, van_naam=?, van_email=?, beveiliging=?
            WHERE id=?
        ");
        $stmt->bind_param("sisssssi", $host, $poort, $gebruikersnaam, $wachtwoord, $van_naam, $van_email, $beveiliging, $instelling['id']);
    } else {
        // Nieuwe rij
        $stmt = $conn->prepare("
            INSERT INTO mailinstellingen (host, poort, gebruikersnaam, wachtwoord, van_naam, van_email, beveiliging)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sisssss", $host, $poort, $gebruikersnaam, $wachtwoord, $van_naam, $van_email, $beveiliging);
    }

    if ($stmt->execute()) {
        setFlash("✅ Mailinstellingen opgeslagen.", "success");
        header("Location: mailinstellingen_beheer.php");
        exit;
    } else {
        setFlash("❌ Fout bij opslaan: " . $stmt->error, "error");
    }
    $stmt->close();
}

// Helper
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<div class="page-header">
    <h2>Mailinstellingen beheren</h2>
    <a href="../index.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" class="form-styled">
        <label>SMTP Host*</label>
        <input type="text" name="host" value="<?= e($instelling['host'] ?? '') ?>" required>

        <label>Poort*</label>
        <input type="number" name="poort" value="<?= e($instelling['poort'] ?? 465) ?>" required>

        <label>Gebruikersnaam*</label>
        <input type="text" name="gebruikersnaam" value="<?= e($instelling['gebruikersnaam'] ?? '') ?>" required>

        <label>Wachtwoord*</label>
        <input type="password" name="wachtwoord" value="<?= e($instelling['wachtwoord'] ?? '') ?>" required>

        <label>Van naam*</label>
        <input type="text" name="van_naam" value="<?= e($instelling['van_naam'] ?? '') ?>" required>

        <label>Van email*</label>
        <input type="email" name="van_email" value="<?= e($instelling['van_email'] ?? '') ?>" required>

        <label>Beveiliging*</label>
        <select name="beveiliging" required>
            <option value="">-- Kies --</option>
            <?php foreach (['ssl','tls','none'] as $opt): ?>
                <option value="<?= $opt ?>" <?= (($instelling['beveiliging'] ?? '') === $opt ? 'selected' : '') ?>><?= strtoupper($opt) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="form-actions">
            <button type="submit" class="btn">💾 Opslaan</button>
            <a href="../index.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Mailinstellingen beheren";
include __DIR__ . "/../template/template.php";
