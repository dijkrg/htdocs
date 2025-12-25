<?php
// =====================================================
// ⚙️ Objectstatus — Instellingen (ABCB stijl)
// =====================================================
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';

// ✅ Toegang: alleen Admin & Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'] ?? '', ['Admin', 'Manager'], true)) {
    setFlash('Geen toegang.', 'error');
    header('Location: ../index.php');
    exit;
}

// Kleine helper
function kleurHex(string $kleur): string {
    return match ($kleur) {
        'groen'  => '#28a745',
        'oranje' => '#ff9800',
        'rood'   => '#dc3545',
        default  => '#6b7280', // gray
    };
}

// ✅ Acties verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = $_POST['actie'] ?? '';

    // Beveiliging: normaliseer input
    $status_id = isset($_POST['status_id']) ? (int)$_POST['status_id'] : 0;
    $naam      = trim($_POST['naam'] ?? '');
    $kleur     = $_POST['kleur'] ?? 'groen';
    if (!in_array($kleur, ['groen', 'oranje', 'rood'], true)) {
        $kleur = 'groen';
    }

    if ($actie === 'toevoegen') {
        if ($naam === '') {
            setFlash('❌ Naam is verplicht.', 'error');
        } else {
            $stmt = $conn->prepare("INSERT INTO object_status (naam, kleur) VALUES (?, ?)");
            $stmt->bind_param("ss", $naam, $kleur);
            if ($stmt->execute()) {
                setFlash("✅ Status ‘{$naam}’ toegevoegd.", 'success');
            } else {
                setFlash("❌ Fout bij toevoegen: " . $stmt->error, 'error');
            }
            $stmt->close();
        }
        header("Location: object_status.php");
        exit;
    }

    if ($actie === 'bewerken') {
        if ($status_id <= 0 || $naam === '') {
            setFlash('❌ Ongeldige invoer.', 'error');
            header("Location: object_status.php");
            exit;
        }
        $stmt = $conn->prepare("UPDATE object_status SET naam=?, kleur=? WHERE status_id=?");
        $stmt->bind_param("ssi", $naam, $kleur, $status_id);
        if ($stmt->execute()) {
            setFlash("✏️ Status ‘{$naam}’ bijgewerkt.", 'success');
        } else {
            setFlash("❌ Fout bij bijwerken: " . $stmt->error, 'error');
        }
        $stmt->close();
        header("Location: object_status.php");
        exit;
    }

    if ($actie === 'verwijderen') {
        if ($status_id <= 0) {
            setFlash('❌ Ongeldige ID.', 'error');
            header("Location: object_status.php");
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM object_status WHERE status_id=?");
        $stmt->bind_param("i", $status_id);
        if ($stmt->execute()) {
            setFlash("🗑️ Status verwijderd.", 'success');
        } else {
            // Vaak FK-constraint: status in gebruik bij objecten
            setFlash("❌ Kon niet verwijderen (mogelijk in gebruik): " . $stmt->error, 'error');
        }
        $stmt->close();
        header("Location: object_status.php");
        exit;
    }
}

// ✅ Data ophalen
$res = $conn->query("SELECT status_id, naam, kleur FROM object_status ORDER BY naam ASC");

$pageTitle = "Objectstatus — Instellingen";
ob_start();
?>

<!-- 🧭 Titel + acties -->
<div class="page-header">
    <h2>⚙️ Objectstatus — Instellingen</h2>
    <div class="header-actions">
        <button class="btn" onclick="openAddModal()">
            ➕ Nieuwe status
        </button>
        <a href="../index.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<?php getFlash(); ?>

<!-- 📋 Tabel (volle breedte) -->
<div class="card" style="width:100%;">
    <table class="data-table" style="width:100%;">
        <thead>
            <tr>
                <th style="width:90px;">ID</th>
                <th>Naam</th>
                <th style="width:180px;">Kleur</th>
                <th style="width:240px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()):
                $hex = kleurHex($r['kleur']);
            ?>
            <tr>
                <td><?= (int)$r['status_id'] ?></td>
                <td><?= htmlspecialchars($r['naam']) ?></td>
                <td>
                    <span class="badge-kleur" style="--badge: <?= $hex ?>">
                        <i class="fa-solid fa-circle"></i> <?= htmlspecialchars(ucfirst($r['kleur'])) ?>
                    </span>
                </td>
                <td class="actions">
                    <button class="btn btn-small"
                        onclick="openEditModal(
                            <?= (int)$r['status_id'] ?>,
                            '<?= htmlspecialchars($r['naam'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($r['kleur'], ENT_QUOTES) ?>'
                        )">
                        ✏️ Bewerken
                    </button>

                    <form method="post" style="display:inline;" onsubmit="return confirm('Deze status verwijderen?');">
                        <input type="hidden" name="actie" value="verwijderen">
                        <input type="hidden" name="status_id" value="<?= (int)$r['status_id'] ?>">
                        <button type="submit" class="btn btn-small btn-danger">🗑️ Verwijderen</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280;">Geen statussen gevonden.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ➕ Modal: Nieuwe status -->
<div id="addModal" class="modal-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;">
    <h3>Nieuwe status toevoegen</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="toevoegen">
        <div>
            <label>Naam*</label>
            <input type="text" name="naam" required>
        </div>
        <div>
            <label>Kleur*</label>
            <select name="kleur">
                <option value="groen">🟢 Groen</option>
                <option value="oranje">🟠 Oranje</option>
                <option value="rood">🔴 Rood</option>
            </select>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn">➕ Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Annuleren</button>
        </div>
    </form>
  </div>
</div>

<!-- ✏️ Modal: Bewerken -->
<div id="editModal" class="modal-overlay" style="display:none;">
  <div class="modal" style="max-width:520px;">
    <h3>Status bewerken</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="actie" value="bewerken">
        <input type="hidden" name="status_id" id="editId">
        <div>
            <label>Naam*</label>
            <input type="text" name="naam" id="editNaam" required>
        </div>
        <div>
            <label>Kleur*</label>
            <select name="kleur" id="editKleur">
                <option value="groen">🟢 Groen</option>
                <option value="oranje">🟠 Oranje</option>
                <option value="rood">🔴 Rood</option>
            </select>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn">💾 Opslaan</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuleren</button>
        </div>
    </form>
  </div>
</div>

<style>
/* Tabel + badges */
.data-table { border-collapse: collapse; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; }
.data-table th { background: #f8f9fa; color: #1f2937; font-weight: 600; }
.data-table tr:hover { background: #f2f6ff; }
.actions .btn-small { margin-right: 6px; }

.badge-kleur {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--badge, #e5e7eb);
    color: #fff;
    padding: 4px 10px;
    border-radius: 999px;
    box-shadow: inset 0 0 0 9999px rgba(0,0,0,.15);
}
.badge-kleur i { font-size: 10px; }

/* Modals */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; width: 100%; padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid > div { display: flex; flex-direction: column; }
.form-grid label { font-weight: 600; margin-bottom: 6px; }
.form-grid input, .form-grid select { padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
.modal-footer { grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }

/* Responsive */
@media (max-width: 720px) {
    .form-grid { grid-template-columns: 1fr; }
}
</style>

<script>
// Open/close modals
function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function openEditModal(id, naam, kleur) {
    document.getElementById('editId').value   = id;
    document.getElementById('editNaam').value = naam;
    document.getElementById('editKleur').value= kleur;
    document.getElementById('editModal').style.display = 'flex';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Sluiten bij overlay-click
['addModal','editModal'].forEach(mid => {
  const el = document.getElementById(mid);
  el.addEventListener('click', (e) => { if (e.target === el) closeModal(mid); });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
