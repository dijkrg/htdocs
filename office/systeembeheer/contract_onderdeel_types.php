<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// 🔐 Alleen Admin / Manager
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: /index.php");
    exit;
}

/* ======================================================
   POST-ACTIES: Toevoegen / Bewerken / Actief wisselen
====================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------------------------
       Toevoegen
    ---------------------------- */
    if ($_POST['actie'] === 'toevoegen') {
        $naam = trim($_POST['naam'] ?? "");
        if ($naam === "") {
            setFlash("Naam mag niet leeg zijn.", "error");
            header("Location: contract_onderdeel_types.php");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO contract_onderdeel_types (naam, actief) VALUES (?, 1)");
        $stmt->bind_param("s", $naam);
        $stmt->execute();
        $stmt->close();

        setFlash("Onderdeel toegevoegd.", "success");
        header("Location: contract_onderdeel_types.php");
        exit;
    }

    /* ---------------------------
       Bewerken
    ---------------------------- */
    if ($_POST['actie'] === 'bewerken') {
        $id = intval($_POST['type_id']);
        $naam = trim($_POST['naam'] ?? "");

        if ($naam === "") {
            setFlash("Naam mag niet leeg zijn.", "error");
            header("Location: contract_onderdeel_types.php");
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE contract_onderdeel_types
            SET naam = ?
            WHERE type_id = ?
        ");
        $stmt->bind_param("si", $naam, $id);
        $stmt->execute();
        $stmt->close();

        setFlash("Onderdeel bijgewerkt.", "success");
        header("Location: contract_onderdeel_types.php");
        exit;
    }

    /* ---------------------------
       Actief / Inactief wisselen
    ---------------------------- */
    if ($_POST['actie'] === 'toggle') {
        $id = intval($_POST['type_id']);
        $conn->query("UPDATE contract_onderdeel_types SET actief = 1 - actief WHERE type_id = $id");

        setFlash("Status gewijzigd.", "success");
        header("Location: contract_onderdeel_types.php");
        exit;
    }
}

/* ======================================================
   OPHALEN LIJST
====================================================== */

$result = $conn->query("
    SELECT *
    FROM contract_onderdeel_types
    ORDER BY actief DESC, sort_order ASC, naam ASC
");

$pageTitle = "Onderhoudsonderdelen beheren";
ob_start();
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h2>🛠 Onderhoudsonderdelen</h2>
    <button class="btn btn-accent" onclick="openNieuw()">➕ Nieuw onderdeel</button>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Status</th>
                <th style="width:150px;">Acties</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="3" style="text-align:center;color:#777;">Geen onderdelen gevonden.</td>
            </tr>
        <?php endif; ?>

        <?php while ($row = $result->fetch_assoc()):
            $statusLabel = $row['actief'] ? "🟢 Actief" : "🔴 Inactief";
            $statusColor = $row['actief'] ? "#2e7d32" : "#c62828";
        ?>
            <tr>
                <td><?= htmlspecialchars($row['naam']) ?></td>
                <td><span style="color:<?= $statusColor ?>;font-weight:600;"><?= $statusLabel ?></span></td>

                <td class="actions">

                    <!-- Bewerken -->
                    <a href="#" onclick='openBewerk(<?= json_encode($row) ?>)'>✏️</a>

                    <!-- Actief/Inactief -->
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="actie" value="toggle">
                        <input type="hidden" name="type_id" value="<?= $row['type_id'] ?>">
                        <button class="link-btn" onclick="return confirm('Status wijzigen?');">🔄</button>
                    </form>

                </td>
            </tr>
        <?php endwhile; ?>

        </tbody>
    </table>
</div>


<!-- ======================================================
     POPUP NIEUW ONDERDEEL
====================================================== -->

<div class="popup-overlay" id="popupNieuw" style="display:none;">
    <div class="popup-content">
        <h3>➕ Nieuw onderdeel</h3>

        <form method="post">
            <input type="hidden" name="actie" value="toevoegen">

            <label>Naam*</label>
            <input type="text" name="naam" required>

            <div class="popup-buttons">
                <button class="btn btn-accent" type="submit">💾 Opslaan</button>
                <button type="button" class="btn btn-secondary" onclick="closePopup('popupNieuw')">Annuleer</button>
            </div>
        </form>
    </div>
</div>


<!-- ======================================================
     POPUP BEWERKEN
====================================================== -->

<div class="popup-overlay" id="popupBewerk" style="display:none;">
    <div class="popup-content">
        <h3>✏️ Onderdeel bewerken</h3>

        <form method="post">
            <input type="hidden" name="actie" value="bewerken">
            <input type="hidden" name="type_id" id="edit_type_id">

            <label>Naam*</label>
            <input type="text" name="naam" id="edit_naam" required>

            <div class="popup-buttons">
                <button class="btn" type="submit">💾 Opslaan</button>
                <button type="button" class="btn btn-secondary" onclick="closePopup('popupBewerk')">Annuleren</button>
            </div>
        </form>
    </div>
</div>

<style>
.popup-overlay {
    position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.45);
    display:flex; justify-content:center; align-items:center;
    z-index:9999;
}
.popup-content {
    background:white; padding:20px 25px; border-radius:10px;
    width:380px;
}
.popup-buttons { display:flex; justify-content:flex-end; gap:10px; margin-top:15px; }
.link-btn { background:none; border:none; cursor:pointer; font-size:16px; }
</style>

<script>
function openNieuw() {
    document.getElementById('popupNieuw').style.display = 'flex';
}
function openBewerk(data) {
    document.getElementById('edit_type_id').value = data.type_id;
    document.getElementById('edit_naam').value = data.naam;
    document.getElementById('popupBewerk').style.display = 'flex';
}
function closePopup(id) {
    document.getElementById(id).style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
