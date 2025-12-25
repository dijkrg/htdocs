<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Geen geldige werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* --------------------------------------------------
   Werkbon ophalen + ownership check
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT werkbon_id, werkbonnummer, monteur_id
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$werkbon || (int)$werkbon['monteur_id'] !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* --------------------------------------------------
   Objecten gekoppeld aan werkbon
-------------------------------------------------- */
$objecten = [];
$q = $conn->prepare("
    SELECT
        o.object_id,
        o.code,
        o.omschrijving,
        o.datum_onderhoud,
        o.fabricagejaar,
        s.naam AS status_naam
    FROM werkbon_objecten wo
    JOIN objecten o ON wo.object_id = o.object_id
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE wo.werkbon_id = ?
    ORDER BY o.code ASC
");
$q->bind_param("i", $werkbon_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) {
    $objecten[] = $r;
}
$q->close();

$pageTitle = "Objecten – Werkbon " . e($werkbon['werkbonnummer']);

ob_start();
?>

<div class="header-actions" style="display:flex; gap:10px; margin-bottom:15px;">
    <a href="/monteur/mijn_planning.php" class="btn btn-secondary">⬅ Terug</a>
    <a href="/monteur/werkbon_object_toevoegen.php?werkbon_id=<?= $werkbon_id ?>" class="btn">➕ Object toevoegen</a>
</div>

<div class="card">
    <h3>Objecten gekoppeld aan werkbon <?= e($werkbon['werkbonnummer']) ?></h3>

    <?php if (empty($objecten)): ?>
        <p class="empty-msg">Geen objecten gekoppeld aan deze werkbon.</p>
    <?php else: ?>
        <table class="data-table small-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Omschrijving</th>
                    <th>Onderhoud</th>
                    <th>Fabricagejaar</th>
                    <th>Status</th>
                    <th style="width:120px;">Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($objecten as $o): ?>
                <tr>
                    <td><?= e($o['code']) ?></td>
                    <td><?= e($o['omschrijving']) ?></td>
                    <td><?= e($o['datum_onderhoud'] ?? '-') ?></td>
                    <td><?= e($o['fabricagejaar'] ?? '-') ?></td>
                    <td><?= e($o['status_naam'] ?? '-') ?></td>
                    <td>
                        <a class="btn btn-danger"
                           href="/monteur/werkbon_object_verwijder.php?werkbon_id=<?= $werkbon_id ?>&object_id=<?= (int)$o['object_id'] ?>"
                           onclick="return confirm('Object ontkoppelen van werkbon?')">🗑</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
