<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot ontvangsten.", "error");
    header("Location: ../index.php");
    exit;
}

// ─────────────────────────────
// Validatie bestelling_id
// ─────────────────────────────
$bestelling_id = isset($_GET['bestelling_id']) ? (int)$_GET['bestelling_id'] : 0;
if ($bestelling_id <= 0) {
    setFlash("Geen bestelling geselecteerd. Ga via Leveranciers > Bestellingen en klik op 📥.", "error");
    header("Location: ../leveranciers/bestellingen.php");
    exit;
}

// ─────────────────────────────
// Bestelling + leverancier ophalen
// ─────────────────────────────
$stmt = $conn->prepare("
    SELECT b.bestelling_id, b.bestelnummer, b.bestel_datum, b.status,
           l.leverancier_id, l.naam AS leverancier_naam
    FROM bestellingen b
    LEFT JOIN leveranciers l ON l.leverancier_id = b.leverancier_id
    WHERE b.bestelling_id = ?
");
$stmt->bind_param("i", $bestelling_id);
$stmt->execute();
$bestelling = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bestelling) {
    setFlash("Bestelling niet gevonden.", "error");
    header("Location: ../leveranciers/bestellingen.php");
    exit;
}

// ─────────────────────────────
// Magazijnen ophalen
// ─────────────────────────────
$magazijnen = $conn->query("SELECT magazijn_id, naam, type FROM magazijnen ORDER BY type='Hoofdmagazijn' DESC, naam ASC");

// ─────────────────────────────
// Bestelregels ophalen
// ─────────────────────────────
$regels = $conn->prepare("
    SELECT
        ba.id              AS regel_id,
        a.artikel_id,
        a.artikelnummer,
        a.omschrijving,
        ba.aantal          AS aantal_besteld,
        COALESCE(ba.aantal_ontvangen,0) AS aantal_ontvangen
    FROM bestelling_artikelen ba
    JOIN artikelen a ON a.artikel_id = ba.artikel_id
    WHERE ba.bestelling_id = ?
      AND (a.categorie IS NULL OR a.categorie <> 'Administratie')
    ORDER BY a.artikelnummer ASC
");
$regels->bind_param("i", $bestelling_id);
$regels->execute();
$resRegels = $regels->get_result();

// ─────────────────────────────
// Paginaopbouw
// ─────────────────────────────
$pageTitle = "📥 Ontvangst boeken (Bestelling #{$bestelling['bestelnummer']})";
ob_start();
?>
<div class="page-header">
    <h2>📥 Ontvangst boeken</h2>
    <div class="header-actions">
        <a href="../leveranciers/bestelling_detail.php?id=<?= $bestelling_id ?>" class="btn btn-secondary">⬅ Terug</a>
        <a href="ontvangst_pdf.php?id=<?= $bestelling_id ?>" target="_blank" class="btn">🧾 Ontvangstbon</a>
        <a href="ontvangsten_lijst.php" class="btn">📄 Log ontvangsten</a>
    </div>
</div>

<div class="card">
    <p style="margin:0;">
        <strong>Bestelling:</strong> #<?= htmlspecialchars($bestelling['bestelnummer']) ?> —
        <?= htmlspecialchars($bestelling['leverancier_naam'] ?? 'Onbekend') ?><br>
        <strong>Datum:</strong> <?= date('d-m-Y', strtotime($bestelling['bestel_datum'])) ?> —
        <strong>Status:</strong> <?= ucfirst($bestelling['status']) ?>
    </p>
</div>

<form method="post" action="transactie_ontvangen.php" class="card form-card" onsubmit="return validateOntvangsten();">
    <input type="hidden" name="bestelling_id" value="<?= $bestelling_id ?>">

    <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div>
            <label for="magazijn_id">📦 Boeken in magazijn</label><br>
            <select name="magazijn_id" id="magazijn_id" required>
                <option value="">— Kies magazijn —</option>
                <?php while ($m = $magazijnen->fetch_assoc()): ?>
                    <option value="<?= (int)$m['magazijn_id'] ?>">
                        <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label for="opmerking_algemeen">Opmerking (optioneel)</label><br>
            <input type="text" name="opmerking_algemeen" id="opmerking_algemeen" placeholder="bijv. pakbon 12345">
        </div>
        <div style="margin-left:auto;">
            <button type="submit" class="btn btn-primary">📦 Ontvangst boeken</button>
        </div>
    </div>

    <div style="margin-top:12px; overflow-x:auto;">
        <table class="data-table compact-table">
            <thead>
                <tr>
                    <th style="min-width:120px;">Artikelnummer</th>
                    <th>Omschrijving</th>
                    <th style="width:110px; text-align:right;">Besteld</th>
                    <th style="width:110px; text-align:right;">Ontvangen</th>
                    <th style="width:130px; text-align:right;">Nog te ontvangen</th>
                    <th style="width:130px;">Nu ontvangen</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $heeftRegels = false;
            while ($r = $resRegels->fetch_assoc()):
                $heeftRegels = true;
                $besteld   = (int)$r['aantal_besteld'];
                $ontv      = (int)$r['aantal_ontvangen'];
                $restant   = max(0, $besteld - $ontv);
            ?>
                <tr<?= $restant > 0 ? '' : ' style="opacity:0.6;"' ?>>
                    <td><?= htmlspecialchars($r['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                    <td style="text-align:right;"><?= $besteld ?></td>
                    <td style="text-align:right;"><?= $ontv ?></td>
                    <td style="text-align:right; font-weight:bold; <?= $restant > 0 ? 'color:#d32f2f;' : 'color:#2e7d32;' ?>">
                        <?= $restant ?>
                    </td>
                    <td>
                        <?php if ($restant > 0): ?>
                            <input type="number"
                                   name="ontvangen[<?= (int)$r['regel_id'] ?>]"
                                   min="0" max="<?= $restant ?>" value="<?= $restant ?>"
                                   style="width:100px; text-align:right;">
                        <?php else: ?>
                            <input type="number" disabled value="0" style="width:100px; text-align:right; opacity:0.7;">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if (!$heeftRegels): ?>
                <tr><td colspan="6" style="text-align:center;">Geen bestelregels gevonden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
function validateOntvangsten() {
    const inputs = document.querySelectorAll('input[name^="ontvangen["]');
    let heeftInvoer = false;
    for (const el of inputs) {
        if (!el.disabled) {
            const v = parseInt(el.value || '0', 10);
            const min = parseInt(el.min || '0', 10);
            const max = parseInt(el.max || '0', 10);
            if (v > 0) heeftInvoer = true;
            if (v < min || v > max) {
                alert('Controleer de ingevoerde aantallen: waarde buiten bereik.');
                el.focus();
                return false;
            }
        }
    }
    if (!heeftInvoer) {
        return confirm("Je boekt 0 stuks. Toch doorgaan?");
    }
    return true;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
