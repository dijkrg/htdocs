<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot leveranciers-artikelen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🔗 Leverancier-artikel koppelingen";

// Leveranciers ophalen
$leveranciers = $conn->query("
    SELECT leverancier_id, naam 
    FROM leveranciers 
    ORDER BY naam ASC
");

$leverancier_id = isset($_GET['leverancier_id']) ? (int)$_GET['leverancier_id'] : 0;

// Artikelen ophalen
$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving 
    FROM artikelen 
    ORDER BY artikelnummer ASC
");

// Bestaande koppelingen ophalen
$koppelingen = [];
if ($leverancier_id > 0) {
    $res = $conn->query("
        SELECT artikel_id, inkoopprijs, actief 
        FROM leverancier_artikelen 
        WHERE leverancier_id = $leverancier_id
    ");
    while ($r = $res->fetch_assoc()) {
        $koppelingen[$r['artikel_id']] = $r;
    }
}

// Opslaan wijzigingen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $leverancier_id > 0) {
    $nieuwe = $_POST['koppeling'] ?? [];

    // Oude koppelingen wissen
    $conn->query("DELETE FROM leverancier_artikelen WHERE leverancier_id = $leverancier_id");

    // Nieuwe koppelingen invoegen
    $stmt = $conn->prepare("
        INSERT INTO leverancier_artikelen (leverancier_id, artikel_id, inkoopprijs, actief)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($nieuwe as $artikel_id => $data) {
        $artikel_id = (int)$artikel_id;
        $inkoopprijs = (float)($data['inkoopprijs'] ?? 0);
        $actief = isset($data['actief']) ? 1 : 0;
        $stmt->bind_param("iidi", $leverancier_id, $artikel_id, $inkoopprijs, $actief);
        $stmt->execute();
    }

    setFlash("Koppelingen bijgewerkt ✅", "success");
    header("Location: leverancier_artikelen.php?leverancier_id=$leverancier_id");
    exit;
}

ob_start();
?>

<div class="page-header">
    <h2>🔗 Leverancier-artikelen</h2>
</div>

<div class="card">
    <form method="get">
        <label for="leverancier_id"><strong>Kies leverancier:</strong></label>
        <select name="leverancier_id" id="leverancier_id" onchange="this.form.submit()">
            <option value="">-- Selecteer leverancier --</option>
            <?php while ($l = $leveranciers->fetch_assoc()): ?>
                <option value="<?= $l['leverancier_id'] ?>" <?= $l['leverancier_id'] == $leverancier_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['naam']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>
</div>

<?php if ($leverancier_id > 0): ?>
    <div class="card" style="margin-top:20px;">
        <form method="post">
            <h3>📦 Artikelen koppelen aan leverancier</h3>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th style="width:5%;">Koppeling</th>
                        <th style="width:20%;">Artikelnummer</th>
                        <th>Omschrijving</th>
                        <th style="width:15%;">Inkoopprijs (€)</th>
                        <th style="width:90px; text-align:center;">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($artikelen->num_rows > 0): ?>
                        <?php while ($a = $artikelen->fetch_assoc()):
                            $id = $a['artikel_id'];
                            $gekoppeld = isset($koppelingen[$id]);
                            $prijs = $gekoppeld ? $koppelingen[$id]['inkoopprijs'] : 0;
                            $actief = $gekoppeld && $koppelingen[$id]['actief'];
                        ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox" name="koppeling[<?= $id ?>][actief]" <?= $actief ? 'checked' : '' ?>>
                            </td>
                            <td><?= htmlspecialchars($a['artikelnummer']) ?></td>
                            <td><?= htmlspecialchars($a['omschrijving']) ?></td>
                            <td>
                                <input type="number" step="0.01" name="koppeling[<?= $id ?>][inkoopprijs]" value="<?= number_format($prijs, 2, '.', '') ?>" style="width:90px;">
                            </td>
                            <td class="actions">
                                <a href="../artikelen/artikel_detail.php?id=<?= $id ?>" class="action-btn view" title="Bekijk artikel">📄</a>
                                <a href="../artikelen/artikel_bewerk.php?id=<?= $id ?>" class="action-btn edit" title="Bewerk artikel">✏️</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">Geen artikelen gevonden.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="form-actions" style="margin-top:15px;">
                <button type="submit" class="btn">💾 Opslaan</button>
                <a href="leveranciers.php" class="btn btn-secondary">⬅ Terug</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
