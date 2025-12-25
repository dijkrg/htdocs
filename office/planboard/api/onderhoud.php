<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';

<table class="data-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Omschrijving</th>
            <th>Klant</th>
            <th>Volgend onderhoud</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['code']) ?></td>
                    <td><?= htmlspecialchars($row['omschrijving']) ?></td>
                    <td><?= htmlspecialchars($row['bedrijfsnaam']) ?></td>
                    <td><?= date("d-m-Y", strtotime($row['volgend_onderhoud'])) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center;color:#777;">Geen aankomend onderhoud gevonden</td></tr>
        <?php endif; ?>
    </tbody>
</table>
