<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Alle gearchiveerde werkbonnen ophalen
$result = $conn->query("
    SELECT w.*, 
           w.`status` AS werkbon_status,
           k.debiteurnummer, k.bedrijfsnaam 
    FROM werkbonnen w
    JOIN klanten k ON w.klant_id = k.klant_id
    WHERE w.gearchiveerd = 1
    ORDER BY w.uitvoerdatum DESC
");

$pageTitle = "Archief werkbonnen";
ob_start();
?>
<div class="page-header">
    <h2>📦 Archief werkbonnen</h2>
    <a href="werkbonnen.php" class="btn btn-secondary">⬅ Terug naar overzicht</a>
</div>

<div class="card">
<?php if ($result->num_rows > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nr</th>
                <th>Klant</th>
                <th>Omschrijving</th>
                <th>Uitvoerdatum</th>
                <th>Status</th>
                <th>Werk gereed</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($wb = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($wb['werkbonnummer']) ?></td>
                <td><?= htmlspecialchars($wb['debiteurnummer'])." - ".htmlspecialchars($wb['bedrijfsnaam']) ?></td>
                <td><?= htmlspecialchars($wb['omschrijving']) ?></td>
                <td><?= htmlspecialchars($wb['uitvoerdatum']) ?></td>
                <td><?= htmlspecialchars($wb['werkbon_status']) ?></td>
                <td><?= ($wb['werk_gereed']==1) ? "Ja" : "Nee" ?></td>
                <td>
                    <a href="werkbon_detail.php?id=<?= $wb['werkbon_id'] ?>">📄</a>
                    <a href="werkbon_bewerk.php?id=<?= $wb['werkbon_id'] ?>">✏️</a>
                    <a href="werkbon_terugzetten.php?id=<?= $wb['werkbon_id'] ?>" 
                       onclick="return confirm('Werkbon terugzetten naar overzicht?')">↩ Herstellen</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p><i>Geen werkbonnen in het archief.</i></p>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
