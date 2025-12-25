<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot klanten.", "error");
    header("Location: index.php");
    exit;
}

$pageTitle = "👥 Klanten overzicht";

$zoekterm = trim($_GET['q'] ?? '');
$whereSQL = '';
$params = [];

if ($zoekterm !== '') {
    $zoekterm_like = '%' . $zoekterm . '%';
    $whereSQL = "
        WHERE k.debiteurnummer LIKE ? 
        OR k.bedrijfsnaam LIKE ? 
        OR k.postcode LIKE ?
    ";
    $params = [$zoekterm_like, $zoekterm_like, $zoekterm_like];
}

// ✅ Query klanten
$sql = "
    SELECT k.klant_id, k.debiteurnummer, k.bedrijfsnaam, k.contactpersoon,
           k.telefoonnummer, k.email, k.adres, k.postcode, k.plaats
    FROM klanten k
    $whereSQL
    ORDER BY k.debiteurnummer ASC
";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param("sss", ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 🧭 Direct doorsturen als er 1 resultaat is
if ($zoekterm !== '' && $result->num_rows === 1) {
    $klant = $result->fetch_assoc();
    header("Location: klant_detail.php?id=" . $klant['klant_id']);
    exit;
}

ob_start();
?>

<div class="page-header">
    <h2>👥 Klanten overzicht</h2>
    <div class="header-actions">
        <form method="get" action="klanten.php" style="display:flex; gap:8px; align-items:center;">
            <input type="text" name="q" placeholder="🔍 Zoek debiteur, naam of postcode..."
                   value="<?= htmlspecialchars($zoekterm) ?>"
                   style="padding:8px; border:1px solid #ccc; border-radius:6px; min-width:240px;">
            <button type="submit" class="btn btn-accent">Zoeken</button>
            <?php if ($zoekterm !== ''): ?>
                <a href="klanten.php" class="btn btn-secondary">🔄 Alles</a>
            <?php endif; ?>
        </form>
        <a href="klant_toevoegen.php" class="btn">➕ Nieuwe klant</a>
    </div>
</div>

<div class="card">
    <table class="data-table compact-table">
        <thead>
            <tr>
                <th>Debiteur nr.</th>
                <th>Bedrijfsnaam</th>
                <th>Adres</th>
                <th>Postcode</th>
                <th>Plaats</th>
                <th>Contactpersoon</th>
                <th>Telefoon</th>
                <th>E-mail</th>
                <th style="width:100px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($klant = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($klant['debiteurnummer']) ?></td>
                    <td><?= htmlspecialchars($klant['bedrijfsnaam']) ?></td>
                    <td><?= htmlspecialchars($klant['adres'] ?? '') ?></td>
                    <td><?= htmlspecialchars($klant['postcode'] ?? '') ?></td>
                    <td><?= htmlspecialchars($klant['plaats'] ?? '') ?></td>
                    <td><?= htmlspecialchars($klant['contactpersoon'] ?? '') ?></td>
                    <td><?= htmlspecialchars($klant['telefoonnummer'] ?? '') ?></td>
                    <td><?= htmlspecialchars($klant['email'] ?? '') ?></td>
                    <td class="actions">
                        <a href="klant_detail.php?id=<?= (int)$klant['klant_id'] ?>" title="Details">📄</a>
                        <a href="klant_bewerk.php?id=<?= (int)$klant['klant_id'] ?>" title="Bewerken">✏️</a>
                        <a href="klant_verwijder.php?id=<?= (int)$klant['klant_id'] ?>" title="Verwijderen"
                           onclick="return confirm('Klant verwijderen?')">🗑</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" style="text-align:center; color:#777;">
                    <?= $zoekterm ? "Geen resultaten voor '<strong>" . htmlspecialchars($zoekterm) . "</strong>'." : "Nog geen klanten toegevoegd." ?>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
