<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $klant_id = (int)$_POST['klant_id'];
    $artikel_id = (int)$_POST['artikel_id'];
    $prijs = (float)$_POST['prijs'];

    $stmt = $conn->prepare("INSERT INTO klant_prijzen (klant_id, artikel_id, prijs) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE prijs = VALUES(prijs)");
    $stmt->bind_param("iid", $klant_id, $artikel_id, $prijs);
    $stmt->execute();
    setFlash("Prijs opgeslagen", "success");
    header("Location: prijsbeheer.php");
    exit;
}

$klanten = $conn->query("SELECT klant_id, bedrijfsnaam FROM klanten ORDER BY bedrijfsnaam");
$artikelen = $conn->query("SELECT artikel_id, artikelnummer, omschrijving FROM artikelen ORDER BY artikelnummer");

$sql = "
SELECT kp.*, k.bedrijfsnaam, a.artikelnummer, a.omschrijving
FROM klant_prijzen kp
JOIN klanten k ON kp.klant_id = k.klant_id
JOIN artikelen a ON kp.artikel_id = a.artikel_id
ORDER BY k.bedrijfsnaam, a.artikelnummer
";
$prijzen = $conn->query($sql);

$pageTitle = "Prijsbeheer";
ob_start();
?>
<h2>💰 Klant-specifieke prijzen</h2>

<form method="post" class="form-card">
    <label>Klant</label>
    <select name="klant_id" required>
        <option value="">-- Kies klant --</option>
        <?php while($k = $klanten->fetch_assoc()): ?>
            <option value="<?= $k['klant_id'] ?>"><?= $k['bedrijfsnaam'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Artikel</label>
    <select name="artikel_id" required>
        <option value="">-- Kies artikel --</option>
        <?php while($a = $artikelen->fetch_assoc()): ?>
            <option value="<?= $a['artikel_id'] ?>"><?= $a['artikelnummer'] ?> - <?= $a['omschrijving'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Prijs (€)</label>
    <input type="number" step="0.01" name="prijs" required>

    <button type="submit" class="btn btn-primary">Opslaan</button>
</form>

<h3>📋 Overzicht</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Klant</th>
            <th>Artikel</th>
            <th>Prijs</th>
        </tr>
    </thead>
    <tbody>
    <?php while($p = $prijzen->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($p['bedrijfsnaam']) ?></td>
            <td><?= htmlspecialchars($p['artikelnummer']) ?> - <?= htmlspecialchars($p['omschrijving']) ?></td>
            <td>€ <?= number_format($p['prijs'], 2, ',', '.') ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
