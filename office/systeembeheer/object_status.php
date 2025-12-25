<?php
// =====================================================
// ⚙️ Objectstatus — Instellingen (ABCB uniforme versie)
// =====================================================
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';

// Toegang
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

// Kleur mapping
function kleurHex(string $kleur): string {
    return match ($kleur) {
        'groen'  => '#28a745',
        'oranje' => '#ff9800',
        'rood'   => '#dc3545',
        default  => '#6b7280'
    };
}

// =====================================================
// Form handling
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';
    $status_id = intval($_POST['status_id'] ?? 0);
    $naam = trim($_POST['naam'] ?? '');
    $kleur = $_POST['kleur'] ?? 'groen';

    if (!in_array($kleur, ['groen','oranje','rood'], true)) {
        $kleur = 'groen';
    }

    if ($actie === 'toevoegen') {
        if ($naam === '') {
            setFlash("Naam is verplicht.", "error");
        } else {
            $stmt = $conn->prepare("INSERT INTO object_status (naam, kleur) VALUES (?,?)");
            $stmt->bind_param("ss", $naam, $kleur);
            $stmt->execute();
            $stmt->close();
            setFlash("Status '{$naam}' toegevoegd.", "success");
        }
        header("Location: object_status.php");
        exit;
    }

    if ($actie === 'bewerken') {
        if ($status_id <= 0 || $naam === '') {
            setFlash("Ongeldige invoer.", "error");
        } else {
            $stmt = $conn->prepare("UPDATE object_status SET naam=?, kleur=? WHERE status_id=?");
            $stmt->bind_param("ssi", $naam, $kleur, $status_id);
            $stmt->execute();
            $stmt->close();
            setFlash("Status bijgewerkt.", "success");
        }
        header("Location: object_status.php");
        exit;
    }

    if ($actie === 'verwijderen') {
        if ($status_id > 0) {
            $stmt = $conn->prepare("DELETE FROM object_status WHERE status_id=?");
            $stmt->bind_param("i", $status_id);
            $stmt->execute();
            $stmt->close();
            setFlash("Status verwijderd.", "success");
        }
        header("Location: object_status.php");
        exit;
    }
}

// Data ophalen
$res = $conn->query("SELECT * FROM object_status ORDER BY naam ASC");

$pageTitle = "Objectstatus — Instellingen";
ob_start();
?>

<!-- ================= HEADER ================= -->
<div class="page-header">
    <h2>⚙️ Objectstatus — Instellingen</h2>

    <div class="header-actions">
        <button class="btn" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Nieuwe status
        </button>

        <a href="../index.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Terug
        </a>
    </div>
</div>

<?php showFlash(); ?>

<!-- ================= TABEL ================= -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:80px;">ID</th>
                <th>Naam</th>
                <th style="width:160px;">Kleur</th>
                <th style="width:120px; text-align:center;">Acties</th>
            </tr>
        </thead>

        <tbody>
        <?php if ($res->num_rows): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['status_id'] ?></td>
                    <td><?= htmlspecialchars($r['naam']) ?></td>

                    <td>
                        <span class="status-badge <?= htmlspecialchars($r['kleur']) ?>">
                            <?= ucfirst($r['kleur']) ?>
                        </span>
                    </td>

                    <td class="actions">

                        <!-- Bewerken -->
                        <button class="action-icon"
                            onclick="openEditModal(
                                <?= $r['status_id'] ?>,
                                '<?= htmlspecialchars($r['naam'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($r['kleur'], ENT_QUOTES) ?>'
                            )"
                            data-tooltip="Bewerken">
                            ✏️
                        </button>

                        <!-- Verwijderen -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="actie" value="verwijderen">
                            <input type="hidden" name="status_id" value="<?= $r['status_id'] ?>">
                            <button class="action-icon" onclick="return confirm('Deze status verwijderen?')" data-tooltip="Verwijderen">
                                🗑
                            </button>
                        </form>

                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">Geen statussen gevonden.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ================= MODAL TOEVOEGEN ================= -->
<div id="addModal" class="abcb-popup-overlay" style="display:none;">
    <div class="abcb-popup">

        <h2>Nieuwe status</h2>
        <p class="popup-subtext">Vul hieronder de gegevens in.</p>

        <form method="post">
            <input type="hidden" name="actie" value="toevoegen">

            <label>Naam*</label>
            <input type="text" name="naam" required>

            <label>Kleur*</label>
            <select name="kleur">
                <option value="groen">Groen</option>
                <option value="oranje">Oranje</option>
                <option value="rood">Rood</option>
            </select>

            <div class="abcb-popup-buttons">
                <button class="btn" type="submit">Toevoegen</button>
                <button class="btn btn-secondary" type="button" onclick="closeModal('addModal')">Annuleren</button>
            </div>
        </form>

    </div>
</div>

<!-- ================= MODAL BEWERKEN ================= -->
<div id="editModal" class="abcb-popup-overlay" style="display:none;">
    <div class="abcb-popup">

        <h2>Status bewerken</h2>

        <form method="post">
            <input type="hidden" name="actie" value="bewerken">
            <input type="hidden" name="status_id" id="editId">

            <label>Naam*</label>
            <input type="text" name="naam" id="editNaam" required>

            <label>Kleur*</label>
            <select name="kleur" id="editKleur">
                <option value="groen">Groen</option>
                <option value="oranje">Oranje</option>
                <option value="rood">Rood</option>
            </select>

            <div class="abcb-popup-buttons">
                <button class="btn" type="submit">Opslaan</button>
                <button class="btn btn-secondary" type="button" onclick="closeModal('editModal')">Annuleren</button>
            </div>
        </form>

    </div>
</div>

<script>
function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function openEditModal(id, naam, kleur) {
    document.getElementById('editId').value = id;
    document.getElementById('editNaam').value = naam;
    document.getElementById('editKleur').value = kleur;
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
