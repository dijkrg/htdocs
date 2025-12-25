<?php
require_once __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot magazijnen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📦 Overzicht magazijnen";
$result = $conn->query("SELECT * FROM magazijnen ORDER BY naam ASC");

ob_start();
?>
<div class="page-header">
    <h2>📦 Magazijnen</h2>
    <a href="magazijn_toevoegen.php" class="btn">➕ Nieuw magazijn</a>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Type</th>
                <th>Locatie</th>
                <th style="width:120px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while($r = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['naam']) ?></td>
                    <td><?= ucfirst($r['type']) ?></td>
                    <td><?= htmlspecialchars($r['locatie']) ?></td>
                    <td class="actions">
                        <a href="magazijn_bewerk.php?id=<?= $r['magazijn_id'] ?>" title="Bewerken">✏️</a>
                        <a href="magazijn_verwijder.php?id=<?= $r['magazijn_id'] ?>" title="Verwijderen" onclick="return confirm('Magazijn verwijderen?')">🗑</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">Geen magazijnen gevonden</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
