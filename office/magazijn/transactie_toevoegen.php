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

$pageTitle = "➕ Nieuwe voorraadtransactie";

// 📦 Artikelen ophalen (excl. categorie 'Administratie')
$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving
    FROM artikelen
    WHERE categorie IS NULL OR categorie <> 'Administratie'
    ORDER BY artikelnummer ASC
");

// 🏢 Magazijnen ophalen
$magazijnen = $conn->query("SELECT magazijn_id, naam, type FROM magazijnen ORDER BY naam ASC");

// ─────────────────────────────
// Formulierverwerking
// ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $magazijn_id = (int)($_POST['magazijn_id'] ?? 0);
    $artikel_id  = (int)($_POST['artikel_id'] ?? ($_POST['artikel_id_hidden'] ?? 0));
    $type        = trim($_POST['type'] ?? '');
    $aantal      = (int)($_POST['aantal'] ?? 0);
    $opmerking   = trim($_POST['opmerking'] ?? '');

    if (!$magazijn_id || !$artikel_id || !$type || $aantal <= 0) {
        setFlash("Selecteer een magazijn, artikel, type en vul een geldig aantal in.", "error");
    } else {
        // Huidige voorraad ophalen
        $res = $conn->prepare("
            SELECT aantal 
            FROM voorraad_magazijn 
            WHERE artikel_id = ? AND magazijn_id = ?
        ");
        $res->bind_param("ii", $artikel_id, $magazijn_id);
        $res->execute();
        $res->bind_result($huidig);
        $res->fetch();
        $res->close();

        $nieuwAantal = (int)$huidig;
        switch ($type) {
            case 'ontvangst':   $nieuwAantal += $aantal; break;
            case 'uitgifte':    $nieuwAantal -= $aantal; break;
            case 'correctie':   $nieuwAantal  = $aantal; break;
            default:
                setFlash("Ongeldig type transactie.", "error");
                header("Location: transacties.php");
                exit;
        }

        // Voorraad bijwerken of toevoegen
        $check = $conn->prepare("
            SELECT COUNT(*) 
            FROM voorraad_magazijn 
            WHERE artikel_id = ? AND magazijn_id = ?
        ");
        $check->bind_param("ii", $artikel_id, $magazijn_id);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();

        if ($exists) {
            $upd = $conn->prepare("
                UPDATE voorraad_magazijn 
                SET aantal = ?, laatste_update = NOW() 
                WHERE artikel_id = ? AND magazijn_id = ?
            ");
            $upd->bind_param("iii", $nieuwAantal, $artikel_id, $magazijn_id);
            $upd->execute();
        } else {
            $ins = $conn->prepare("
                INSERT INTO voorraad_magazijn (artikel_id, magazijn_id, aantal, laatste_update)
                VALUES (?, ?, ?, NOW())
            ");
            $ins->bind_param("iii", $artikel_id, $magazijn_id, $nieuwAantal);
            $ins->execute();
        }

        // Transactie loggen
        $stmt = $conn->prepare("
            INSERT INTO voorraad_transacties (artikel_id, magazijn_id, datum, type, aantal, opmerking)
            VALUES (?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->bind_param("iisis", $artikel_id, $magazijn_id, $type, $aantal, $opmerking);
        $stmt->execute();

        setFlash("Transactie succesvol toegevoegd ✅", "success");
        header("Location: transacties.php");
        exit;
    }
}

ob_start();
?>

<div class="page-header">
    <h2>➕ Nieuwe voorraadtransactie</h2>
    <a href="transacties.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <form method="post" class="form-card">

        <!-- 🔽 Type transactie -->
        <label for="type">Type transactie *</label>
        <select name="type" id="type" required>
            <option value="">-- Kies type --</option>
            <option value="ontvangst">📥 Ontvangst</option>
            <option value="uitgifte">📤 Uitgifte</option>
            <option value="correctie">✏️ Correctie</option>
        </select>

        <!-- 🏢 Magazijn -->
        <label for="magazijn_id">Magazijn *</label>
        <select name="magazijn_id" id="magazijn_id" required>
            <option value="">-- Kies magazijn --</option>
            <?php while ($m = $magazijnen->fetch_assoc()): ?>
                <option value="<?= $m['magazijn_id'] ?>">
                    <?= htmlspecialchars($m['naam']) ?> (<?= htmlspecialchars($m['type']) ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <!-- 🔎 Artikel selecteren -->
        <label for="artikel_id">Artikel (dropdown)</label>
        <select name="artikel_id" id="artikel_id" style="width:100%;">
            <option value="">-- Kies artikel --</option>
            <?php while ($a = $artikelen->fetch_assoc()): ?>
                <option value="<?= $a['artikel_id'] ?>">
                    <?= htmlspecialchars($a['artikelnummer']) ?> — <?= htmlspecialchars($a['omschrijving']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <hr>

        <!-- 🔍 Zoekveld met AJAX filter -->
        <label for="artikel_zoek">Of zoek artikel 🔍</label>
        <div class="artikel-zoek-container">
            <input type="text" id="artikel_zoek" placeholder="Zoek artikelnummer of omschrijving..." autocomplete="off">
            <input type="hidden" name="artikel_id_hidden" id="artikel_id_hidden">
            <div id="zoek_resultaten" class="zoek-resultaten"></div>
        </div>

        <!-- 🔢 Aantal -->
        <label for="aantal">Aantal *</label>
        <input type="number" name="aantal" id="aantal" min="1" required>

        <!-- 🗒 Opmerking -->
        <label for="opmerking">Opmerking</label>
        <input type="text" name="opmerking" id="opmerking" placeholder="Bijv. levering leverancier">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="transacties.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>

<!-- 🔎 JavaScript Zoekfilter -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('artikel_zoek');
    const hidden = document.getElementById('artikel_id_hidden');
    const resultaten = document.getElementById('zoek_resultaten');
    let timeout = null;

    if (!input) return;

    input.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(timeout);

        if (query.length < 2) {
            resultaten.style.display = 'none';
            return;
        }

        timeout = setTimeout(() => {
            fetch('/magazijn/artikel_search.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    resultaten.innerHTML = '';
                    if (data.length === 0) {
                        resultaten.innerHTML = '<div>Geen resultaten</div>';
                    } else {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.textContent = item.text;
                            div.dataset.id = item.id;
                            resultaten.appendChild(div);
                        });
                    }
                    resultaten.style.display = 'block';
                })
                .catch(() => {
                    resultaten.innerHTML = '<div>Fout bij zoeken</div>';
                    resultaten.style.display = 'block';
                });
        }, 250);
    });

    resultaten.addEventListener('click', e => {
        if (e.target && e.target.dataset.id) {
            hidden.value = e.target.dataset.id;
            input.value = e.target.textContent;
            resultaten.style.display = 'none';
        }
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.artikel-zoek-container')) {
            resultaten.style.display = 'none';
        }
    });
});
</script>

<!-- 💅 CSS -->
<style>
.artikel-zoek-container { position: relative; width: 100%; }
.zoek-resultaten {
    position: absolute; top: 100%; left: 0; right: 0;
    background: white; border: 1px solid #ccc;
    border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: none; max-height: 200px; overflow-y: auto; z-index: 1000;
}
.zoek-resultaten div { padding: 8px 12px; cursor: pointer; }
.zoek-resultaten div:hover { background: #f2f6ff; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
