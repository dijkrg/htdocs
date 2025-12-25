<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

<form method="post" action="uren_toevoegen.php">
    <input type="hidden" name="werkbon_id" value="<?= $werkbon['werkbon_id'] ?>">
    <label>Uursoort:</label>
    <select name="uursoort_id">
        <?php foreach($uursoorten as $u): ?>
            <option value="<?= $u['uursoort_id'] ?>">
                <?= htmlspecialchars($u['code']) ?> - <?= htmlspecialchars($u['omschrijving']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label>Uren:</label>
    <input type="number" step="0.25" name="uren" required>
    <label>Opmerkingen:</label>
    <input type="text" name="opmerkingen">
    <button type="submit" class="btn">Toevoegen</button>
</form>
