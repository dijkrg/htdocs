<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    exit('Geen toegang.');
}

$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving
    FROM artikelen
    WHERE categorie IS NULL OR categorie <> 'Administratie'
    ORDER BY artikelnummer ASC
");
?>

<select name="artikel_id" id="artikel_id" required>
    <option value="">-- Kies artikel --</option>
    <?php while ($a = $artikelen->fetch_assoc()): ?>
        <option value="<?= $a['artikel_id'] ?>">
            <?= htmlspecialchars($a['artikelnummer']) ?> — <?= htmlspecialchars($a['omschrijving']) ?>
        </option>
    <?php endwhile; ?>
</select>
