<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);
$werkbon_id = intval($_GET['werkbon_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM werkbon_uren WHERE werkbon_uur_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$uur = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$uur) {
    setFlash("Urenregel niet gevonden.", "error");
    header("Location: werkbon_detail.php?id=$werkbon_id");
    exit;
}

// Alleen bewerken als NIET goedgekeurd
if ($uur['goedgekeurd'] === 'goedgekeurd') {
    setFlash("Urenregel is al goedgekeurd en kan niet meer aangepast worden.", "error");
    header("Location: werkbon_detail.php?id=$werkbon_id");
    exit;
}

if (isset($_POST['opslaan'])) {
    $uursoort_id = intval($_POST['uursoort_id']);
    $datum       = $_POST['datum'];
    $begintijd   = $_POST['begintijd'];
    $eindtijd    = $_POST['eindtijd'];
    $opmerkingen = $_POST['opmerkingen'];

    $stmt = $conn->prepare("UPDATE werkbon_uren SET uursoort_id=?, datum=?, begintijd=?, eindtijd=?, opmerkingen=?, goedgekeurd='in_behandeling' WHERE werkbon_uur_id=?");
    $stmt->bind_param("issssi", $uursoort_id, $datum, $begintijd, $eindtijd, $opmerkingen, $id);
    if ($stmt->execute()) {
        setFlash("Urenregel bijgewerkt.", "success");
    }
    header("Location: werkbon_detail.php?id=$werkbon_id");
    exit;
}

$uursoorten = $conn->query("SELECT * FROM uursoorten");
ob_start();
?>
<h2>Urenregel bewerken</h2>
<div class="card">
    <form method="post" class="form-styled">
        <label>Datum</label>
        <input type="date" name="datum" value="<?= htmlspecialchars($uur['datum']) ?>" required>

        <label>Begintijd</label>
        <input type="time" name="begintijd" value="<?= htmlspecialchars($uur['begintijd']) ?>" required>

        <label>Eindtijd</label>
        <input type="time" name="eindtijd" value="<?= htmlspecialchars($uur['eindtijd']) ?>" required>

        <label>Uursoort</label>
        <select name="uursoort_id" required>
            <?php while ($us = $uursoorten->fetch_assoc()): ?>
                <option value="<?= $us['uursoort_id'] ?>" <?= ($us['uursoort_id'] == $uur['uursoort_id'])?'selected':'' ?>>
                    <?= htmlspecialchars($us['code']." - ".$us['omschrijving']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Opmerkingen</label>
        <textarea name="opmerkingen"><?= htmlspecialchars($uur['opmerkingen']) ?></textarea>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">Opslaan</button>
            <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Urenregel bewerken";
include __DIR__ . "/template/template.php";
