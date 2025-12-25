<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot magazijnoverboekingen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🔄 Magazijn → Magazijn overboeking";

// Magazijnen ophalen
$magazijnen = $conn->query("
    SELECT magazijn_id, naam, type
    FROM magazijnen
    ORDER BY type, naam
");
if (!$magazijnen) {
    setFlash("Kon magazijnen niet laden.", "error");
    header("Location: transacties.php");
    exit;
}

ob_start();
?>
<div class="page-header">
    <h2>🔄 Magazijn → Magazijn overboeking</h2>
    <a href="transacties.php" class="btn btn-secondary">⬅ Terug naar transacties</a>
</div>

<div class="card">
    <form method="post" action="transactie_overboeken.php" class="form-styled">
        <label for="artikel_zoek">Zoek artikel (nummer of omschrijving)</label>
        <input type="text" id="artikel_zoek" placeholder="Begin met typen...">

        <label for="artikel_id">Gevonden artikelen *</label>
        <select name="artikel_id" id="artikel_id" required>
            <option value="">-- Zoek en selecteer artikel --</option>
        </select>

<div class="grid-2" style="display:grid; gap:12px; grid-template-columns: 1fr 1fr;">
    <div>
        <label for="bron_magazijn">Van magazijn *</label>
        <select name="bron_magazijn" id="bron_magazijn" required>
            <option value="">-- Kies bronmagazijn --</option>
            <?php
            mysqli_data_seek($magazijnen, 0);
            while ($m = $magazijnen->fetch_assoc()): ?>
                <option value="<?= (int)$m['magazijn_id'] ?>">
                    <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <!-- 🔹 Hier komt de actuele voorraadregel -->
        <p id="voorraadInfo" style="margin-top:6px; font-size:14px; color:#555;">
            Beschikbare voorraad: —
        </p>
    </div>

    <div>
        <label for="doel_magazijn">Naar magazijn *</label>
        <select name="doel_magazijn" id="doel_magazijn" required>
            <option value="">-- Kies doelmagazijn --</option>
            <?php
            mysqli_data_seek($magazijnen, 0);
            while ($m = $magazijnen->fetch_assoc()): ?>
                <option value="<?= (int)$m['magazijn_id'] ?>">
                    <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</div>

        <label for="aantal">Aantal *</label>
        <input type="number" name="aantal" id="aantal" step="0.01" min="0.01" required>

        <label for="opmerking">Opmerking</label>
        <input type="text" name="opmerking" id="opmerking" placeholder="Bijv. herverdeling voorraad / naar voertuig">

        <div class="form-actions">
            <button type="submit" class="btn">🚚 Overboeken</button>
            <a href="transacties.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<script>
// -----------------------------
// 🔍 Live zoeken van artikelen
// -----------------------------
document.getElementById('artikel_zoek').addEventListener('input', function() {
    const zoekterm = this.value.trim();
    const dropdown = document.getElementById('artikel_id');

    if (zoekterm.length < 2) {
        dropdown.innerHTML = '<option value="">-- Zoek en selecteer artikel --</option>';
        document.getElementById('voorraadInfo').textContent = 'Beschikbare voorraad: —';
        return;
    }

    fetch('../zoek_artikelen.php?q=' + encodeURIComponent(zoekterm))
        .then(res => res.json())
        .then(data => {
            dropdown.innerHTML = '<option value="">-- Selecteer artikel --</option>';
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.artikel_id;
                opt.textContent = `${item.artikelnummer} - ${item.omschrijving} (€ ${item.inkoopprijs})`;
                dropdown.appendChild(opt);
            });
        })
        .catch(err => console.error('Zoekfout:', err));
});

const artikelSelect = document.getElementById('artikel_id');
const bronSelect = document.getElementById('bron_magazijn');
const voorraadInfo = document.getElementById('voorraadInfo');

function updateVoorraad() {
    const artikel_id = parseInt(artikelSelect.value);
    const magazijn_id = parseInt(bronSelect.value);
    if (!artikel_id || !magazijn_id) {
        voorraadInfo.textContent = "Beschikbare voorraad: —";
        return;
    }

    fetch(`voorraad_huidig.php?artikel_id=${artikel_id}&magazijn_id=${magazijn_id}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                voorraadInfo.textContent = "Fout: " + data.error;
            } else {
                voorraadInfo.textContent = `Beschikbare voorraad: ${data.aantal}`;
                voorraadInfo.style.color = data.aantal > 0 ? "#333" : "#d9534f";
            }
        })
        .catch(err => {
            voorraadInfo.textContent = "Voorraad ophalen mislukt.";
            console.error(err);
        });
}

artikelSelect.addEventListener('change', updateVoorraad);
bronSelect.addEventListener('change', updateVoorraad);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
