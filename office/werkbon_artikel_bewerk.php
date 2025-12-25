<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);
$werkbon_id = intval($_GET['werkbon_id'] ?? 0);

if ($id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldige parameters.", "error");
    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

// Huidige regel ophalen
$stmt = $conn->prepare("
    SELECT wa.*, a.artikelnummer, a.omschrijving, a.verkoopprijs 
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON wa.artikel_id = a.artikel_id
    WHERE wa.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$regel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$regel) {
    setFlash("Artikelregel niet gevonden.", "error");
    header("Location: werkbon_detail.php?id=" . $werkbon_id);
    exit;
}

// Artikellijst ophalen
$artikelen = $conn->query("
    SELECT artikel_id, artikelnummer, omschrijving, verkoopprijs 
    FROM artikelen 
    ORDER BY artikelnummer ASC
");

$errors = [];
if (isset($_POST['opslaan'])) {
    $artikel_id = intval($_POST['artikel_id'] ?? 0);
    $aantal     = floatval($_POST['aantal'] ?? 0);

    if ($artikel_id <= 0 || $aantal <= 0) {
        $errors[] = "Artikel en aantal zijn verplicht.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE werkbon_artikelen 
            SET artikel_id=?, aantal=? 
            WHERE id=?
        ");
        $stmt->bind_param("idi", $artikel_id, $aantal, $id);

        if ($stmt->execute()) {
            setFlash("Artikelregel bijgewerkt ✅", "success");
            header("Location: werkbon_detail.php?id=" . $werkbon_id);
            exit;
        } else {
            $errors[] = "Fout bij opslaan: " . $stmt->error;
        }
        $stmt->close();
    }

    foreach ($errors as $err) setFlash($err, "error");
}

ob_start();
?>
<div class="page-header">
    <h2>Artikelregel bewerken</h2>
    <div class="header-actions">
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <form method="post" class="form-styled">
        <label for="artikel_id">Artikel*</label>
        <select name="artikel_id" id="artikel_id" required>
            <?php while ($a = $artikelen->fetch_assoc()): ?>
                <option value="<?= $a['artikel_id'] ?>" <?= $a['artikel_id'] == $regel['artikel_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['artikelnummer'] . " - " . $a['omschrijving']) ?> (€ <?= number_format($a['verkoopprijs'], 2, ',', '.') ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <label for="aantal">Aantal*</label>
        <input type="number" step="0.01" min="0" name="aantal" id="aantal" value="<?= htmlspecialchars($regel['aantal']) ?>" required>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
            <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Artikelregel bewerken";
include __DIR__ . "/template/template.php";
