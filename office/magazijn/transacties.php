<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang tot voorraadtransacties.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📑 Voorraadtransacties";

// ─────────────────────────────
// Alle transacties ophalen met magazijn + artikel + actuele voorraad
// ─────────────────────────────
$query = "
    SELECT 
        t.transactie_id,
        t.datum,
        t.type,
        t.aantal,
        t.opmerking,
        a.artikelnummer,
        a.omschrijving,
        m.naam AS magazijn_naam,
        m.type AS magazijn_type,
        vm.aantal AS actuele_voorraad
    FROM voorraad_transacties t
    LEFT JOIN artikelen a ON t.artikel_id = a.artikel_id
    LEFT JOIN magazijnen m ON t.magazijn_id = m.magazijn_id
    LEFT JOIN voorraad_magazijn vm ON vm.artikel_id = t.artikel_id AND vm.magazijn_id = t.magazijn_id
    WHERE (a.categorie IS NULL OR a.categorie <> 'Administratie')
    ORDER BY t.datum DESC
    LIMIT 250
";

$result = $conn->query($query);

ob_start();
?>

<div class="page-header">
    <h2>📑 Voorraadtransacties</h2>
    <a href="transactie_toevoegen.php" class="btn">➕ Nieuwe transactie</a>
</div>

<div class="card">
    <p>Hieronder zie je alle voorraadmutaties per magazijn.  
    De kolom ‘Actuele voorraad’ toont het huidige aantal in dat magazijn.</p>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:150px;">Datum</th>
                <th>Magazijn</th>
                <th>Artikel</th>
                <th>Type</th>
                <th style="text-align:right;">Aantal</th>
                <th style="text-align:right;">Actuele voorraad</th>
                <th>Opmerking</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        // kleur bepalen per type
                        $kleur = match($row['type']) {
                            'ontvangst' => 'color:green;',
                            'uitgifte'  => 'color:#d32f2f;',
                            'correctie' => 'color:#1565c0;',
                            default => ''
                        };
                    ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($row['datum'])) ?></td>
                        <td><?= htmlspecialchars($row['magazijn_naam'] ?? '—') ?> <small>(<?= htmlspecialchars($row['magazijn_type'] ?? '') ?>)</small></td>
                        <td><?= htmlspecialchars($row['artikelnummer'] ?? '') ?> — <?= htmlspecialchars($row['omschrijving'] ?? '') ?></td>
                        <td style="<?= $kleur ?>"><?= ucfirst($row['type']) ?></td>
                        <td style="text-align:right;"><?= (int)$row['aantal'] ?></td>
                        <td style="text-align:right; font-weight:600;">
                            <?= is_null($row['actuele_voorraad']) ? '—' : (int)$row['actuele_voorraad'] ?>
                        </td>
                        <td><?= htmlspecialchars($row['opmerking'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Nog geen transacties geregistreerd.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
