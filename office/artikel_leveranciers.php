<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// ✅ Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$artikel_id = intval($_GET['id'] ?? 0);
if ($artikel_id <= 0) {
    setFlash("Ongeldig artikel.", "error");
    header("Location: artikelen.php");
    exit;
}

// ✅ Artikel ophalen
$stmt = $conn->prepare("SELECT artikelnummer, omschrijving FROM artikelen WHERE artikel_id = ?");
$stmt->bind_param("i", $artikel_id);
$stmt->execute();
$artikel = $stmt->get_result()->fetch_assoc();

if (!$artikel) {
    setFlash("Artikel niet gevonden.", "error");
    header("Location: artikelen.php");
    exit;
}

// ✅ Leveranciers ophalen
$leveranciers = $conn->query("SELECT leverancier_id, naam FROM leveranciers ORDER BY naam ASC");

// ✅ Bestaande koppelingen
$rows = $conn->query("
    SELECT al.*, l.naam 
    FROM artikel_leveranciers al
    JOIN leveranciers l ON l.leverancier_id = al.leverancier_id
    WHERE al.artikel_id = {$artikel_id}
    ORDER BY l.naam
");

// ✅ Bewerkmodus
$bewerk_id = isset($_GET['bewerk']) ? intval($_GET['bewerk']) : 0;
$bewerk = null;
if ($bewerk_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM artikel_leveranciers WHERE id = ?");
    $stmt->bind_param("i", $bewerk_id);
    $stmt->execute();
    $bewerk = $stmt->get_result()->fetch_assoc();
}

// ✅ Opslaan of bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leverancier_id = intval($_POST['leverancier_id']);
    $inkoopprijs    = floatval($_POST['inkoopprijs']);
    $levertijd      = intval($_POST['levertijd_dagen']);
    $referentie     = trim($_POST['referentie']);
    $opmerkingen    = trim($_POST['opmerkingen']);
    $actief         = isset($_POST['actief']) ? 1 : 0;

    if ($bewerk_id > 0) {
        $stmt = $conn->prepare("
            UPDATE artikel_leveranciers 
            SET leverancier_id=?, inkoopprijs=?, levertijd_dagen=?, referentie=?, opmerkingen=?, actief=? 
            WHERE id=?");
        $stmt->bind_param("idsssii", $leverancier_id, $inkoopprijs, $levertijd, $referentie, $opmerkingen, $actief, $bewerk_id);
        $stmt->execute();
        setFlash("Leverancier bijgewerkt ✅", "success");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO artikel_leveranciers (artikel_id, leverancier_id, inkoopprijs, levertijd_dagen, referentie, opmerkingen, actief) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsssi", $artikel_id, $leverancier_id, $inkoopprijs, $levertijd, $referentie, $opmerkingen, $actief);
        $stmt->execute();
        setFlash("Leverancier toegevoegd ✅", "success");
    }

    header("Location: artikel_leveranciers.php?id={$artikel_id}");
    exit;
}

// ✅ Verwijderen
if (isset($_GET['verwijder'])) {
    $id = intval($_GET['verwijder']);
    $conn->query("DELETE FROM artikel_leveranciers WHERE id = {$id}");
    setFlash("Leverancier verwijderd ❌", "success");
    header("Location: artikel_leveranciers.php?id={$artikel_id}");
    exit;
}

$pageTitle = "Leveranciers beheren — " . htmlspecialchars($artikel['omschrijving']);
ob_start();
?>

<div class="page-header">
    <h2>🏢 Leveranciers voor artikel: <strong><?= htmlspecialchars($artikel['artikelnummer'] . ' — ' . $artikel['omschrijving']) ?></strong></h2>
    <div class="header-actions">
        <a href="artikel_detail.php?id=<?= $artikel_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <h3>Gekoppelde leveranciers</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Leverancier</th>
                <th style="width:100px;">Prijs (€)</th>
                <th style="width:120px;">Levertijd</th>
                <th>Referentie</th>
                <th>Opmerkingen</th>
                <th style="width:100px;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows->num_rows > 0): while ($r = $rows->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['naam']) ?></td>
                    <td style="text-align:right;">€ <?= number_format($r['inkoopprijs'], 2, ',', '.') ?></td>
                    <td><?= $r['levertijd_dagen'] ?> dagen</td>
                    <td><?= htmlspecialchars($r['referentie']) ?></td>
                    <td><?= htmlspecialchars($r['opmerkingen']) ?></td>
                    <td class="actions">
                        <a href="?id=<?= $artikel_id ?>&bewerk=<?= $r['id'] ?>">✏️</a>
                        <a href="?id=<?= $artikel_id ?>&verwijder=<?= $r['id'] ?>" onclick="return confirm('Weet je zeker dat je deze koppeling wilt verwijderen?')">🗑</a>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6" style="text-align:center;">Nog geen leveranciers gekoppeld.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 🔽 Bewerk- of nieuwformulier -->
<?php if ($bewerk_id > 0 || isset($_GET['nieuw'])): ?>
<div class="card leverancier-bewerk">
    <h3><?= $bewerk_id ? '✏️ Leverancier bewerken' : '➕ Nieuwe leverancier' ?></h3>

    <form method="post" class="form-card">
        <label for="leverancier_id">Leverancier *</label>
        <select name="leverancier_id" id="leverancier_id" required>
            <option value="">— Kies leverancier —</option>
            <?php mysqli_data_seek($leveranciers, 0);
            while ($l = $leveranciers->fetch_assoc()): ?>
                <option value="<?= $l['leverancier_id'] ?>" <?= ($bewerk && $bewerk['leverancier_id'] == $l['leverancier_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['naam']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="inkoopprijs">Inkoopprijs (€)</label>
        <input type="number" step="0.01" name="inkoopprijs" id="inkoopprijs"
               value="<?= htmlspecialchars($bewerk['inkoopprijs'] ?? '') ?>">

        <label for="levertijd_dagen">Levertijd (dagen)</label>
        <input type="number" name="levertijd_dagen" id="levertijd_dagen"
               value="<?= htmlspecialchars($bewerk['levertijd_dagen'] ?? '') ?>">

        <label for="referentie">Leveranciersreferentie</label>
        <input type="text" name="referentie" id="referentie"
               value="<?= htmlspecialchars($bewerk['referentie'] ?? '') ?>">

        <label for="opmerkingen">Opmerkingen</label>
        <textarea name="opmerkingen" id="opmerkingen" rows="2"><?= htmlspecialchars($bewerk['opmerkingen'] ?? '') ?></textarea>

        <label><input type="checkbox" name="actief" <?= (!isset($bewerk['actief']) || $bewerk['actief']) ? 'checked' : '' ?>> Actief</label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="artikel_leveranciers.php?id=<?= $artikel_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php endif; ?>

<style>
.leverancier-bewerk {
    margin-top: 25px;
    border: 2px solid #eee;
    background: #fafafa;
    transition: background-color 0.4s ease;
}
.highlight-flash {
    animation: flashHighlight 1.2s ease-in-out;
}
@keyframes flashHighlight {
    0%   { background-color: #fff6b3; }
    50%  { background-color: #fff9cc; }
    100% { background-color: #fafafa; }
}
</style>

<!-- 🔽 Scroll & Highlight Script -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('bewerk')) return;

    const editCard = document.querySelector('.leverancier-bewerk');
    if (editCard) {
        editCard.classList.add('highlight-flash');
        setTimeout(() => {
            editCard.scrollIntoView({ behavior: "smooth", block: "start" });
        }, 300);
        setTimeout(() => {
            editCard.classList.remove('highlight-flash');
        }, 2500);
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
