<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

// ✅ Artikel ophalen
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldig artikel.", "error");
    header("Location: artikelen.php");
    exit;
}

$artikel_id = (int)$_GET['id'];

// ✅ Basisgegevens ophalen
$stmt = $conn->prepare("
    SELECT a.*, c.naam AS categorie_naam
    FROM artikelen a
    LEFT JOIN categorieen c ON a.categorie_id = c.id
    WHERE a.artikel_id = ?
");
$stmt->bind_param("i", $artikel_id);
$stmt->execute();
$artikel = $stmt->get_result()->fetch_assoc();

if (!$artikel) {
    setFlash("Artikel niet gevonden.", "error");
    header("Location: artikelen.php");
    exit;
}

// ✅ Voorraad per magazijn ophalen
$voorraad_sql = "
    SELECT 
        m.naam AS magazijn_naam,
        m.locatie AS stelling,
        vm.aantal,
        vm.laatste_update
    FROM voorraad_magazijn vm
    JOIN magazijnen m ON vm.magazijn_id = m.magazijn_id
    WHERE vm.artikel_id = ?
    ORDER BY m.naam ASC
";
$voorraad_stmt = $conn->prepare($voorraad_sql);
$voorraad_stmt->bind_param("i", $artikel_id);
$voorraad_stmt->execute();
$voorraad_result = $voorraad_stmt->get_result();

$pageTitle = "Artikel details";
ob_start();
?>

<!-- 🔹 Pagina-header -->
<div class="page-header">
    <h2>📦 Artikel: <span><?= htmlspecialchars($artikel['omschrijving']) ?></span></h2>
    <div class="header-actions">
        <a href="artikel_bewerk.php?id=<?= $artikel_id ?>" class="btn">✏️ Bewerken</a>
        <a href="artikel_leveranciers.php?id=<?= $artikel_id ?>" class="btn btn-accent">🏢 Leveranciers beheren</a>
        <a href="artikelen.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<!-- 🔹 Artikelgegevens -->
<!-- 🔹 Leveranciers -->
<div class="card">
    <h3>🏢 Leveranciers & Inkoopcondities</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Leverancier</th>
                <th>Inkoopprijs</th>
                <th>Levertijd</th>
                <th>Referentie</th>
                <th>Opmerkingen</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $levQuery = $conn->prepare("
                SELECT l.naam, al.inkoopprijs, al.levertijd_dagen, al.referentie, al.opmerkingen
                FROM artikel_leveranciers al
                JOIN leveranciers l ON al.leverancier_id = l.leverancier_id
                WHERE al.artikel_id = ? AND al.actief = 1
                ORDER BY l.naam ASC
            ");
            $levQuery->bind_param('i', $artikel_id);
            $levQuery->execute();
            $levResult = $levQuery->get_result();

            if ($levResult->num_rows > 0):
                while ($row = $levResult->fetch_assoc()):
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['naam']) ?></td>
                    <td>€ <?= number_format($row['inkoopprijs'], 2, ',', '.') ?></td>
                    <td><?= $row['levertijd_dagen'] ? $row['levertijd_dagen'] . ' dagen' : '-' ?></td>
                    <td><?= htmlspecialchars($row['referentie'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['opmerkingen'] ?? '-') ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" style="text-align:center;">Geen leveranciers gekoppeld</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 💅 Stijlen voor nette opmaak -->
<style>
.page-header h2 {
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header h2 span {
    font-weight: 600;
    color: #222;
    margin-left: 4px;
}
.artikel-info th {
    text-align: left;
    width: 200px;           /* 🔹 bepaalt afstand tussen label en waarde */
    padding-right: 25px;    /* 🔹 extra ruimte rechts van label */
    font-weight: 600;
    color: #333;
    vertical-align: top;
}
.artikel-info td {
    text-align: left;
    padding: 6px 8px;
    color: #222;
}
.artikel-info tr:nth-child(even) {
    background: #fafafa;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
