<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "✏️ Bestelling bewerken";

// ─────────────────────────────
// ID ophalen
// ─────────────────────────────
$bestelling_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bestelling_id <= 0) {
    setFlash("Geen bestelling geselecteerd.", "error");
    header("Location: bestellingen.php");
    exit;
}

// ─────────────────────────────
// Bestelling ophalen
// ─────────────────────────────
$stmt = $conn->prepare("
    SELECT b.*, l.naam AS leverancier_naam
    FROM bestellingen b
    LEFT JOIN leveranciers l ON b.leverancier_id = l.leverancier_id
    WHERE b.bestelling_id = ?
");
$stmt->bind_param("i", $bestelling_id);
$stmt->execute();
$bestelling = $stmt->get_result()->fetch_assoc();

if (!$bestelling) {
    setFlash("Bestelling niet gevonden.", "error");
    header("Location: bestellingen.php");
    exit;
}

// ─────────────────────────────
// Artikelen ophalen
// ─────────────────────────────
$regelsResult = $conn->prepare("
    SELECT ba.id, ba.aantal, ba.inkoopprijs,
           a.artikelnummer, a.omschrijving
    FROM bestelling_artikelen ba
    LEFT JOIN artikelen a ON ba.artikel_id = a.artikel_id
    WHERE ba.bestelling_id = ?
    ORDER BY ba.id ASC
");
$regelsResult->bind_param("i", $bestelling_id);
$regelsResult->execute();
$regels = $regelsResult->get_result();

// ─────────────────────────────
// Form verwerken
// ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? $bestelling['status'];
    $opmerking = trim($_POST['opmerking'] ?? '');
    $regelsInput = $_POST['regels'] ?? [];

    // Update bestelling
    $upd = $conn->prepare("UPDATE bestellingen SET status = ?, opmerking = ? WHERE bestelling_id = ?");
    $upd->bind_param("ssi", $status, $opmerking, $bestelling_id);
    $upd->execute();

    // Regels bijwerken
    foreach ($regelsInput as $regel_id => $r) {
        $aantal = (int)($r['aantal'] ?? 0);
        $inkoopprijs = (float)($r['inkoopprijs'] ?? 0);
        if ($aantal > 0) {
            $update = $conn->prepare("UPDATE bestelling_artikelen SET aantal = ?, inkoopprijs = ? WHERE id = ? AND bestelling_id = ?");
            $update->bind_param("idii", $aantal, $inkoopprijs, $regel_id, $bestelling_id);
            $update->execute();
        }
    }

    setFlash("Bestelling succesvol bijgewerkt ✅", "success");
    header("Location: bestelling_detail.php?id=" . $bestelling_id);
    exit;
}

// ─────────────────────────────
ob_start();
?>

<div class="page-header">
    <h2>✏️ Bestelling bewerken</h2>
    <div class="header-actions">
        <a href="bestelling_detail.php?id=<?= $bestelling_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <tbody>
            <tr>
                <th style="width:200px;">Bestelnummer</th>
                <td><?= htmlspecialchars($bestelling['bestelnummer'] ?? '') ?></td>
            </tr>
            <tr>
                <th>Leverancier</th>
                <td><?= htmlspecialchars($bestelling['leverancier_naam'] ?? 'Onbekend') ?></td>
            </tr>
            <tr>
                <th>Datum</th>
                <td><?= !empty($bestelling['besteldatum']) ? date('d-m-Y', strtotime($bestelling['besteldatum'])) : '-' ?></td>
            </tr>
        </tbody>
    </table>
</div>

<form method="post" class="form-card" style="margin-top:20px;">
    <h3>📦 Artikelen</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th style="width:80px;">Aantal</th>
                <th style="width:120px;">Inkoopprijs (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($regels->num_rows > 0): ?>
                <?php while ($r = $regels->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['artikelnummer'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['omschrijving'] ?? '') ?></td>
                        <td>
                            <input type="number" name="regels[<?= (int)$r['id'] ?>][aantal]" 
                                   value="<?= htmlspecialchars($r['aantal'] ?? '0') ?>" min="0">
                        </td>
                        <td>
                            <input type="number" name="regels[<?= (int)$r['id'] ?>][inkoopprijs]" 
                                   value="<?= htmlspecialchars(number_format((float)($r['inkoopprijs'] ?? 0), 2, '.', '')) ?>" 
                                   step="0.01" min="0">
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">Geen artikelen gekoppeld aan deze bestelling.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="form-row" style="margin-top:15px;">
        <label for="status">Status</label>
        <select name="status" id="status">
            <?php
            $statusOpties = ['open' => 'Open', 'gedeeltelijk' => 'Gedeeltelijk', 'afgehandeld' => 'Afgehandeld', 'geannuleerd' => 'Geannuleerd'];
            foreach ($statusOpties as $key => $label):
                $sel = ($bestelling['status'] ?? '') === $key ? 'selected' : '';
                echo "<option value='$key' $sel>$label</option>";
            endforeach;
            ?>
        </select>
    </div>

    <div class="form-row">
        <label for="opmerking">Opmerking</label>
        <textarea name="opmerking" id="opmerking" rows="2"><?= htmlspecialchars($bestelling['opmerking'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">💾 Opslaan</button>
        <a href="bestelling_detail.php?id=<?= $bestelling_id ?>" class="btn btn-secondary">Annuleren</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
