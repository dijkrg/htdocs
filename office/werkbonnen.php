<?php
require_once __DIR__ . '/includes/init.php';

/** Helpers */
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function formatDateNL($date) {
    if (!$date || $date === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d-m-Y') : '';
}

/** Alle actieve werkbonnen ophalen + type naam */
$result = $conn->query("
    SELECT w.*,
           w.`status` AS werkbon_status,
           k.debiteurnummer, k.bedrijfsnaam,
           t.naam AS type_naam
    FROM werkbonnen w
    JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.gearchiveerd = 0
    ORDER BY w.uitvoerdatum DESC
");

$pageTitle = "Werkbonnen overzicht";
ob_start();
?>
<div class="page-header">
    <h2>Werkbonnen overzicht</h2>
    <div class="header-actions">
        <a href="werkbon_toevoegen.php" class="btn">➕ Nieuwe werkbon</a>
        <a href="werkbonnen_archief.php" class="btn btn-secondary">📦 Archief</a>
    </div>
</div>

<div class="card">
<?php if ($result && $result->num_rows > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nr</th>
                <th>Klant</th>
                <th>Type werkzaamheden</th>
                <th>Uitvoerdatum</th>
                <th>Status</th>
                <th>Werk gereed</th>
                <th style="text-align:center;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($wb = $result->fetch_assoc()): ?>
            <tr>
                <td><?= e($wb['werkbonnummer']) ?></td>
                <td><?= e($wb['debiteurnummer']) . " - " . e($wb['bedrijfsnaam']) ?></td>
                <td><?= e($wb['type_naam'] ?? 'Onbekend type') ?></td>
                <td><?= e(formatDateNL($wb['uitvoerdatum'])) ?></td>
                <td><?= e($wb['werkbon_status']) ?></td>
                <td>
                    <?php if ((int)$wb['werk_gereed'] === 1): ?>
                        <span class="badge badge-success">Gereed</span>
                    <?php else: ?>
                        <span class="badge badge-grey">Niet gereed</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="werkbon_detail.php?id=<?= $wb['werkbon_id'] ?>" title="Details">📄</a>
                    <a href="werkbon_bewerk.php?id=<?= $wb['werkbon_id'] ?>" title="Bewerken">✏️</a>
                    <a href="werkbon_verwijder.php?id=<?= $wb['werkbon_id'] ?>" title="Verwijderen" onclick="return confirm('Werkbon verwijderen?')">🗑</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Geen werkbonnen gevonden.</p>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
