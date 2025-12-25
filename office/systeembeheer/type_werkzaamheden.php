<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: ../login.php");
    exit;
}

// ==========================
// TOEVOEGEN
// ==========================
if (isset($_POST['toevoegen'])) {
    $naam = trim($_POST['naam']);
    $beschrijving = trim($_POST['beschrijving']);

    if ($naam !== '') {
        $stmt = $conn->prepare("INSERT INTO type_werkzaamheden (naam, beschrijving) VALUES (?, ?)");
        $stmt->bind_param("ss", $naam, $beschrijving);
        $stmt->execute();
        setFlash("Type werkzaamheden toegevoegd.", "success");
    } else {
        setFlash("Naam is verplicht.", "error");
    }

    header("Location: type_werkzaamheden.php");
    exit;
}

// ==========================
// BEWERKEN
// ==========================
if (isset($_POST['bewerken'])) {
    $id = intval($_POST['id']);
    $naam = trim($_POST['naam']);
    $beschrijving = trim($_POST['beschrijving']);

    if ($naam !== '') {
        $stmt = $conn->prepare("UPDATE type_werkzaamheden SET naam=?, beschrijving=? WHERE id=?");
        $stmt->bind_param("ssi", $naam, $beschrijving, $id);
        $stmt->execute();
        setFlash("Type werkzaamheden bijgewerkt.", "success");
    } else {
        setFlash("Naam is verplicht.", "error");
    }

    header("Location: type_werkzaamheden.php");
    exit;
}

// ==========================
// VERWIJDEREN
// ==========================
if (isset($_GET['verwijderen'])) {
    $id = intval($_GET['verwijderen']);
    $stmt = $conn->prepare("DELETE FROM type_werkzaamheden WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    setFlash("Type werkzaamheden verwijderd.", "success");
    header("Location: type_werkzaamheden.php");
    exit;
}

// ==========================
// OPHALEN
// ==========================
$result = $conn->query("SELECT * FROM type_werkzaamheden ORDER BY naam ASC");
$types = $result->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Type werkzaamheden";

ob_start();
?>

<div class="page-header">
    <h2>🛠 Type werkzaamheden</h2>
</div>

<!-- Toevoegen -->
<div class="card">
    <form method="post">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" name="naam" placeholder="Naam" required>
            <input type="text" name="beschrijving" placeholder="Beschrijving (optioneel)">
            <button type="submit" name="toevoegen" class="btn">
                <i class="fa-solid fa-plus"></i> Toevoegen
            </button>
        </div>
    </form>
</div>


<!-- Overzicht -->
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Naam</th>
                <th>Beschrijving</th>
                <th style="width:120px;">Acties</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($types as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['naam']) ?></td>
                <td><?= htmlspecialchars($t['beschrijving']) ?></td>

                <td class="actions">
                    <!-- Bewerken popup / pagina -->
                    <button 
                        type="button" 
                        class="action-icon" 
                        data-tooltip="Bewerken"
                        onclick="openEditModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['naam']) ?>', '<?= htmlspecialchars($t['beschrijving']) ?>')"
                    >
                        ✏️
                    </button>

                    <!-- Verwijderen -->
                    <a href="?verwijderen=<?= $t['id'] ?>"
                       class="action-icon"
                       data-tooltip="Verwijderen"
                       onclick="return confirm('Weet je zeker dat je dit type wilt verwijderen?')">
                        🗑
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>


<!-- BEWERK MODAL -->
<div id="editModal" class="abcb-popup-overlay">
    <div class="abcb-popup">

        <h2>Bewerk type werkzaamheden</h2>

        <form method="post">
            <input type="hidden" name="bewerken" value="1">
            <input type="hidden" name="id" id="editId">

            <label>Naam</label>
            <input type="text" name="naam" id="editNaam" required>

            <label>Beschrijving</label>
            <input type="text" name="beschrijving" id="editBeschrijving">

            <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                <button type="submit" class="btn">
                    <i class="fa-solid fa-floppy-disk"></i> Opslaan
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Annuleren</button>
            </div>
        </form>

    </div>
</div>


<script>
function openEditModal(id, naam, beschrijving) {
    document.getElementById("editId").value = id;
    document.getElementById("editNaam").value = naam;
    document.getElementById("editBeschrijving").value = beschrijving;

    document.getElementById("editModal").style.display = "flex";
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
