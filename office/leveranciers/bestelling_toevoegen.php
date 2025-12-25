<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🧾 Nieuwe bestelling";

// Leveranciers ophalen
$leveranciers = $conn->query("SELECT leverancier_id, naam FROM leveranciers ORDER BY naam ASC");

// Prefill-parameters (vanuit artikel_leveranciers)
$prefill_leverancier_id = isset($_GET['leverancier_id']) ? (int)$_GET['leverancier_id'] : 0;
$prefill_artikel_id     = isset($_GET['artikel_id']) ? (int)$_GET['artikel_id'] : 0;

// Bepaal leverancier_id (POST heeft voorrang)
$leverancier_id = isset($_POST['leverancier_id']) ? intval($_POST['leverancier_id']) : $prefill_leverancier_id;

// Artikelen ophalen — uit artikel_leveranciers
$artikelen = [];
if ($leverancier_id > 0) {
    $res = $conn->prepare("
        SELECT a.artikel_id, a.artikelnummer, a.omschrijving, al.inkoopprijs
        FROM artikel_leveranciers al
        JOIN artikelen a ON al.artikel_id = a.artikel_id
        WHERE al.leverancier_id = ?
        ORDER BY a.artikelnummer ASC
    ");
    $res->bind_param("i", $leverancier_id);
    $res->execute();
    $artikelen = $res->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Opslaan bestelling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    $besteldatum = $_POST['besteldatum'] ?? date('Y-m-d');
    $opmerking   = trim($_POST['opmerking'] ?? '');
    $regels      = $_POST['regels'] ?? [];

    if ($leverancier_id <= 0) {
        setFlash("Selecteer een leverancier.", "error");
    } elseif (empty($regels)) {
        setFlash("Voeg minimaal één artikel toe.", "error");
    } else {
        // 🔢 Automatisch bestelnummer genereren (YY50XXX)
        $jaar = date('y');
        $prefix = $jaar . '50';
        $res = $conn->query("
            SELECT bestelnummer 
            FROM bestellingen 
            WHERE bestelnummer LIKE '{$prefix}%'
            ORDER BY bestelnummer DESC 
            LIMIT 1
        ");

        if ($res && $res->num_rows > 0) {
            $laatste = $res->fetch_assoc()['bestelnummer'];
            $nieuwNummer = (int)$laatste + 1;
        } else {
            $nieuwNummer = (int)($prefix . '001');
        }

        $bestelnummer = (string)$nieuwNummer;

        // 🔹 Bestelling opslaan
        $stmt = $conn->prepare("
            INSERT INTO bestellingen (bestelnummer, leverancier_id, besteldatum, status, opmerking)
            VALUES (?, ?, ?, 'open', ?)
        ");
        $stmt->bind_param("siss", $bestelnummer, $leverancier_id, $besteldatum, $opmerking);
        $stmt->execute();
        $bestelling_id = $conn->insert_id;

        // 🔹 Artikelen opslaan
        $regelStmt = $conn->prepare("
            INSERT INTO bestelling_artikelen (bestelling_id, artikel_id, aantal, inkoopprijs)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($regels as $regel) {
            $artikel_id  = intval($regel['artikel_id'] ?? 0);
            $aantal      = intval($regel['aantal'] ?? 0);
            $inkoopprijs = floatval($regel['inkoopprijs'] ?? 0);
            if ($artikel_id > 0 && $aantal > 0) {
                $regelStmt->bind_param("iiid", $bestelling_id, $artikel_id, $aantal, $inkoopprijs);
                $regelStmt->execute();
            }
        }

        setFlash("Bestelling #{$bestelnummer} aangemaakt ✅", "success");
        header("Location: bestellingen.php");
        exit;
    }
}

ob_start();
?>

<div class="page-header">
    <h2>🧾 Nieuwe bestelling</h2>
    <a href="bestellingen.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card form-card">
<form method="post" id="bestellingForm">
    <!-- Leverancier selecteren -->
    <div class="form-row">
        <label for="leverancier_id">Leverancier</label>
        <select name="leverancier_id" id="leverancier_id" required onchange="document.getElementById('bestellingForm').submit()">
            <option value="">-- Kies leverancier --</option>
            <?php
            $leveranciers->data_seek(0);
            while ($l = $leveranciers->fetch_assoc()):
            ?>
                <option value="<?= $l['leverancier_id'] ?>" <?= $l['leverancier_id'] == $leverancier_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['naam']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="form-row">
        <label>Besteldatum</label>
        <input type="date" name="besteldatum" value="<?= htmlspecialchars($_POST['besteldatum'] ?? date('Y-m-d')) ?>" required>
    </div>

    <div class="form-row">
        <label>Opmerking</label>
        <textarea name="opmerking" rows="2" placeholder="Optioneel"><?= htmlspecialchars($_POST['opmerking'] ?? '') ?></textarea>
    </div>

    <hr>

    <!-- Artikelen -->
    <h3>📦 Artikelen</h3>

    <?php if ($leverancier_id == 0): ?>
        <p style="color:#c00;">Selecteer eerst een leverancier om artikelen te tonen.</p>
    <?php else: ?>
        <table id="artikeltabel" class="data-table">
            <thead>
                <tr>
                    <th style="width:45%;">Artikel</th>
                    <th style="width:15%;">Aantal</th>
                    <th style="width:20%;">Inkoopprijs (€)</th>
                    <th style="width:10%;"></th>
                </tr>
            </thead>
            <tbody id="regelsContainer">
                <tr>
                    <td>
                        <select name="regels[0][artikel_id]" required onchange="updatePrijs(this)">
                            <option value="">-- Kies artikel --</option>
                            <?php foreach ($artikelen as $a): ?>
                                <option value="<?= $a['artikel_id'] ?>" data-prijs="<?= $a['inkoopprijs'] ?>"
                                    <?= ($prefill_artikel_id && $prefill_artikel_id == $a['artikel_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['artikelnummer'].' - '.$a['omschrijving']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="regels[0][aantal]" min="1" required></td>
                    <td><input type="number" name="regels[0][inkoopprijs]" step="0.01" min="0" required></td>
                    <td><button type="button" class="btn-small btn-remove" onclick="verwijderRegel(this)">🗑</button></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:10px;">
            <button type="button" class="btn" id="addRow">➕ Regel toevoegen</button>
        </div>

        <div class="form-actions" style="margin-top:20px;">
            <button type="submit" name="opslaan" value="1" class="btn btn-primary">✅ Opslaan</button>
            <a href="bestellingen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    <?php endif; ?>
</form>
</div>

<script>
let regelIndex = 1;
document.getElementById('addRow')?.addEventListener('click', function() {
    const container = document.getElementById('regelsContainer');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td>
            <select name="regels[${regelIndex}][artikel_id]" required onchange="updatePrijs(this)">
                <option value="">-- Kies artikel --</option>
                <?php foreach ($artikelen as $a):
                    $label = htmlspecialchars($a['artikelnummer'].' - '.$a['omschrijving']);
                    $prijs = $a['inkoopprijs'];
                    echo "<option value='{$a['artikel_id']}' data-prijs='{$prijs}'>$label</option>";
                endforeach; ?>
            </select>
        </td>
        <td><input type="number" name="regels[${regelIndex}][aantal]" min="1" required></td>
        <td><input type="number" name="regels[${regelIndex}][inkoopprijs]" step="0.01" min="0" required></td>
        <td><button type="button" class="btn-small btn-remove" onclick="verwijderRegel(this)">🗑</button></td>
    `;
    container.appendChild(newRow);
    regelIndex++;
});

function verwijderRegel(btn) {
    btn.closest('tr').remove();
}

function updatePrijs(selectEl) {
    const selected = selectEl.options[selectEl.selectedIndex];
    const prijs = selected.getAttribute('data-prijs');
    const prijsInput = selectEl.closest('tr').querySelector('input[name*="[inkoopprijs]"]');
    if (prijs && prijsInput) prijsInput.value = parseFloat(prijs).toFixed(2);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
