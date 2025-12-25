<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

checkRole(['Admin','Manager']); // Alleen Admin en Manager mogen deze pagina

// Toevoegen
if (isset($_POST['toevoegen'])) {
    $code = trim($_POST['code']);
    $omschrijving = trim($_POST['omschrijving']);
    if ($code && $omschrijving) {
        $stmt = $conn->prepare("INSERT INTO uursoorten (code, omschrijving) VALUES (?,?)");
        $stmt->bind_param("ss", $code, $omschrijving);
        $stmt->execute();
        setFlash("✅ Uursoort toegevoegd.", "success");
        header("Location: uursoorten.php");
        exit;
    } else {
        setFlash("⚠️ Vul alle velden in.", "error");
    }
}

// Bewerken
if (isset($_POST['bewerk'])) {
    $id   = intval($_POST['uursoort_id']);
    $code = trim($_POST['code']);
    $omschrijving = trim($_POST['omschrijving']);
    if ($id && $code && $omschrijving) {
        $stmt = $conn->prepare("UPDATE uursoorten SET code=?, omschrijving=? WHERE uursoort_id=?");
        $stmt->bind_param("ssi", $code, $omschrijving, $id);
        $stmt->execute();
        setFlash("✏️ Uursoort bijgewerkt.", "success");
        header("Location: uursoorten.php");
        exit;
    }
}

// Verwijderen
if (isset($_GET['verwijder'])) {
    $id = intval($_GET['verwijder']);
    $stmt = $conn->prepare("DELETE FROM uursoorten WHERE uursoort_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    setFlash("🗑 Uursoort verwijderd.", "success");
    header("Location: uursoorten.php");
    exit;
}

// Ophalen
$result = $conn->query("SELECT * FROM uursoorten ORDER BY code ASC");

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<div class="page-header">
    <h2>Uursoorten beheren</h2>
</div>

<div class="card">
    <h3>Nieuwe uursoort toevoegen</h3>
    <form method="post" class="form-styled">
        <label>Code</label>
        <input type="text" name="code" required>

        <label>Omschrijving</label>
        <input type="text" name="omschrijving" required>

        <div class="form-actions">
            <button type="submit" name="toevoegen" class="btn">➕ Toevoegen</button>
        </div>
    </form>
</div>

<h3>Bestaande uursoorten</h3>
<table class="data-table small-table">
    <thead>
        <tr>
            <th>Code</th>
            <th>Omschrijving</th>
            <th style="width:120px; text-align:center;">Acties</th>
        </tr>
    </thead>

    <tbody>
    <?php while ($u = $result->fetch_assoc()): ?>
        <tr>
            <form method="post">
                <td>
                    <input type="text" name="code" value="<?= e($u['code']) ?>" required>
                </td>

                <td>
                    <input type="text" name="omschrijving" value="<?= e($u['omschrijving']) ?>" required>
                </td>

<td class="actions">

    <input type="hidden" name="uursoort_id" value="<?= $u['uursoort_id'] ?>">

    <!-- OPSLAAN (emoji) -->
    <button type="submit" name="bewerk"
            title="Opslaan" 
            class="action-icon">
        💾
    </button>

    <!-- VERWIJDEREN (emoji) -->
    <a href="uursoorten.php?verwijder=<?= $u['uursoort_id'] ?>"
       onclick="return confirm('Verwijder deze uursoort?')"
       title="Verwijderen"
       class="action-icon">
        🗑
    </a>

</td>
            </form>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
$pageTitle = "Uursoorten";
include __DIR__ . "/../template/template.php";
