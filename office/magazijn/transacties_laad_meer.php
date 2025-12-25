<?php
require_once __DIR__ . '/../includes/init.php';
header("Content-Type: text/html; charset=UTF-8");

// 25 extra transacties ophalen
$res = $conn->query("
    SELECT 
        t.transactie_id,
        a.artikelnummer,
        a.omschrijving,
        m.naam AS magazijn_naam,
        t.type,
        t.aantal,
        DATE_FORMAT(t.datum, '%d-%m-%Y %H:%i') AS datum
    FROM voorraad_transacties t
    JOIN artikelen a ON a.artikel_id = t.artikel_id
    JOIN magazijnen m ON m.magazijn_id = t.magazijn_id
    ORDER BY t.datum DESC
    LIMIT 25 OFFSET 5
");

while ($t = $res->fetch_assoc()):
    $kleur = match ($t['type']) {
        'ontvangst' => 'color:#2e7d32;',
        'verkoop', 'uitgifte' => 'color:#d32f2f;',
        'correctie' => 'color:#1976d2;',
        'overboeking' => 'color:#f57c00;',
        default => ''
    };
?>
<tr>
    <td><?= $t['datum'] ?></td>
    <td><?= htmlspecialchars($t['artikelnummer']) ?> — <?= htmlspecialchars($t['omschrijving']) ?></td>
    <td style="<?= $kleur ?>"><?= ucfirst($t['type']) ?></td>
    <td style="text-align:right;"><?= (int)$t['aantal'] ?></td>
    <td><?= htmlspecialchars($t['magazijn_naam']) ?></td>
</tr>
<?php endwhile; ?>
