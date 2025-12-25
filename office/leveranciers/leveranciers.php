<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot leveranciersbeheer.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🏢 Leveranciersbeheer";

// Leveranciers ophalen
$result = $conn->query("
    SELECT leverancier_id, naam, contactpersoon, telefoon, email, plaats, land
    FROM leveranciers
    ORDER BY naam ASC
");

ob_start();
?>

<div class="page-header">
    <h2>🏢 Leveranciers</h2>
    <a href="leverancier_toevoegen.php" class="btn">➕ Nieuwe leverancier</a>
</div>

<div class="card">
    <table class="data-table compact-table">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Contactpersoon</th>
                <th>Telefoon</th>
                <th>Email</th>
                <th>Plaats</th>
                <th>Land</th>
                <th style="width:90px; text-align:center;">Actie</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($l = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['naam']) ?></td>
                        <td><?= htmlspecialchars($l['contactpersoon'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($l['telefoon'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($l['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($l['plaats'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($l['land'] ?: '-') ?></td>
                        <td class="actions">
                            <a href="leverancier_detail.php?id=<?= $l['leverancier_id'] ?>" class="action-btn view" title="Details">📄</a>
                            <a href="leverancier_bewerk.php?id=<?= $l['leverancier_id'] ?>" class="action-btn edit" title="Bewerken">✏️</a>
                            <a href="leverancier_verwijder.php?id=<?= $l['leverancier_id'] ?>" class="action-btn delete" title="Verwijderen" onclick="return confirm('Leverancier verwijderen?')">🗑</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Geen leveranciers gevonden</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';

