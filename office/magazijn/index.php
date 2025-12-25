<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// 🔐 Toegangscontrole
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: ../login.php");
    exit;
}

$pageTitle = "🏭 Magazijnbeheer";

// 🔹 Aantal artikelen onder minimale voorraad ophalen
$kritiekQuery = $conn->query("
    SELECT COUNT(*) AS totaal
    FROM artikelen a
    WHERE a.minimale_voorraad > 0
    AND (
        SELECT COALESCE(SUM(vm.aantal), 0)
        FROM voorraad_magazijn vm
        WHERE vm.artikel_id = a.artikel_id
    ) <= a.minimale_voorraad
");
$kritiekAantal = $kritiekQuery ? (int)$kritiekQuery->fetch_assoc()['totaal'] : 0;

// 🔹 Laatste transacties (10)
$transacties = $conn->query("
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
    LIMIT 10
");

ob_start();
?>
<div class="page-header">
    <h2>🏭 Magazijnbeheer</h2>
</div>

<!-- 📦 OVERZICHT TEgels -->
<div class="warehouse-grid">

    <div class="warehouse-card">
        <div class="icon-circle bg-blue"><i class="fa-solid fa-box"></i></div>
        <div class="card-content">
            <h3>Voorraadbeheer</h3>
            <p>Bekijk en beheer actuele voorraad per magazijn.</p>
            <a href="voorraad.php" class="btn btn-primary">Naar Voorraad</a>
        </div>
    </div>

    <div class="warehouse-card">
        <div class="icon-circle bg-green"><i class="fa-solid fa-truck-ramp-box"></i></div>
        <div class="card-content">
            <h3>Bestellingen</h3>
            <p>Beheer en verwerk binnenkomende bestellingen.</p>
            <a href="../leveranciers/bestellingen.php" class="btn btn-primary">Naar Bestellingen</a>
        </div>
    </div>

    <div class="warehouse-card">
        <div class="icon-circle bg-gray"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="card-content">
            <h3>Voorraadcorrectie</h3>
            <p>Corrigeer aantallen bij afwijkingen of tellingen.</p>
            <a href="transactie_correctie.php" class="btn btn-primary">Nieuwe Correctie</a>
        </div>
    </div>

    <!-- ⚠️ Te bestellen -->
    <div class="warehouse-card">
        <div class="icon-circle bg-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="card-content">
            <h3>Te Bestellen</h3>
            <p>
                <?= $kritiekAantal > 0 
                    ? "Er zijn <strong>{$kritiekAantal}</strong> artikelen onder minimale voorraad." 
                    : "Alle artikelen zijn op peil 🎉"; ?>
            </p>
            <a href="te_bestellen.php" class="btn btn-primary">Naar overzicht</a>
        </div>
    </div>

</div>

<!-- 📑 Laatste transacties -->
<div class="transactions-card">
    <div class="transactions-header">
        <h3><i class="fa-solid fa-arrows-rotate"></i> Laatste Transacties</h3>
        <div>
            <a href="transacties.php" class="btn btn-outline">📑 Volledig overzicht</a>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table compact-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Artikel</th>
                    <th>Type</th>
                    <th style="text-align:right;">Aantal</th>
                    <th>Magazijn</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transacties && $transacties->num_rows > 0): ?>
                    <?php while ($t = $transacties->fetch_assoc()): ?>
                        <?php
                            $kleur = match ($t['type']) {
                                'ontvangst'   => 'color:#2e7d32;',
                                'verkoop', 'uitgifte' => 'color:#d32f2f;',
                                'correctie'   => 'color:#1976d2;',
                                'overboeking' => 'color:#f57c00;',
                                'bestelling'  => 'color:#6a1b9a;',
                                'retour'      => 'color:#00838f;',
                                default       => 'color:#555;'
                            };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($t['datum']) ?></td>
                            <td><?= htmlspecialchars($t['artikelnummer']) ?> — <?= htmlspecialchars($t['omschrijving']) ?></td>
                            <td style="<?= $kleur ?> font-weight:bold;"><?= ucfirst($t['type']) ?></td>
                            <td style="text-align:right;"><?= (int)$t['aantal'] ?></td>
                            <td><?= htmlspecialchars($t['magazijn_naam']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#777;">Nog geen transacties geregistreerd.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.warehouse-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.warehouse-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.07);
    display: flex; align-items: center; gap: 18px;
    padding: 18px 20px;
    transition: transform .2s ease, box-shadow .2s ease;
}
.warehouse-card:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,.1); }
.icon-circle { width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:24px; flex-shrink:0; }
.bg-blue{background:#1e88e5}.bg-green{background:#2e7d32}.bg-gray{background:#6c757d}.bg-red{background:#d32f2f}
.card-content h3 { margin:0; font-size:18px; color:#222; }
.card-content p  { color:#555; font-size:14px; margin:4px 0 10px; }
.transactions-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 6px rgba(0,0,0,.08); }
.transactions-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.transactions-header h3 { margin:0; font-size:18px; color:#333; }
.table-container { overflow-x:auto; }
.btn-outline { background:transparent; border:1px solid #ccc; color:#333; margin-left:8px; }
.btn-outline:hover { background:#f5f5f5; }
@media(max-width:800px){.transactions-header{flex-direction:column; align-items:flex-start; gap:10px;}}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
