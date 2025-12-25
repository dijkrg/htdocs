<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// ✅ Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Haal gebruiker uit sessie
$medewerker_id = $_SESSION['user']['id'] ?? null;

// Default: gekoppeld aan een werkbon (via GET)
$werkbon_id = intval($_GET['werkbon_id'] ?? 0);

// Formulier verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    $werkbon_id   = intval($_POST['werkbon_id'] ?? 0);
    $monteur_id   = intval($_POST['monteur_id'] ?? $medewerker_id);
    $uursoort_id  = intval($_POST['uursoort_id'] ?? 0);
    $datum        = $_POST['datum'] ?? date("Y-m-d");
    $begintijd    = $_POST['begintijd'] ?? '';
    $eindtijd     = $_POST['eindtijd'] ?? '';
    $opmerkingen  = trim($_POST['opmerkingen'] ?? '');

    if ($werkbon_id && $monteur_id && $uursoort_id && $datum && $begintijd && $eindtijd) {
        $stmt = $conn->prepare("
            INSERT INTO werkbon_uren (werkbon_id, monteur_id, uursoort_id, datum, begintijd, eindtijd, opmerkingen)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("iiissss", $werkbon_id, $monteur_id, $uursoort_id, $datum, $begintijd, $eindtijd, $opmerkingen);

        if ($stmt->execute()) {
            setFlash("✅ Uurregistratie toegevoegd.", "success");
            header("Location: werkbon_detail.php?id=" . $werkbon_id);
            exit;
        } else {
            setFlash("❌ Fout bij opslaan: " . $stmt->error, "error");
        }
    } else {
        setFlash("Vul alle verplichte velden in.", "error");
    }
}

// Haal lijst met monteurs
$monteurs = $conn->query("SELECT medewerker_id, voornaam, achternaam, personeelsnummer FROM medewerkers WHERE rol='Monteur' ORDER BY achternaam");

// Haal lijst met uursoorten
$uursoorten = $conn->query("SELECT uursoort_id, code, omschrijving FROM uursoorten");

// Content
ob_start();
?>
<div class="page-header">
    <h2>+ Uren toevoegen</h2>
    <div class="header-actions">
        <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card">
    <form method="post" class="form-styled">
        <input type="hidden" name="werkbon_id" value="<?= $werkbon_id ?>">

        <label>Monteur*</label>
        <select name="monteur_id" required>
            <option value="">-- Selecteer monteur --</option>
            <?php while ($m = $monteurs->fetch_assoc()): ?>
                <option value="<?= $m['medewerker_id'] ?>" <?= ($m['medewerker_id']==$medewerker_id)?'selected':'' ?>>
                    <?= htmlspecialchars($m['voornaam']." ".$m['achternaam']." (".$m['personeelsnummer'].")") ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Uursoort*</label>
        <select name="uursoort_id" required>
            <option value="">-- Selecteer uursoort --</option>
            <?php while ($u = $uursoorten->fetch_assoc()): ?>
                <option value="<?= $u['uursoort_id'] ?>"><?= htmlspecialchars($u['code']." - ".$u['omschrijving']) ?></option>
            <?php endwhile; ?>
        </select>

        <label>Datum*</label>
        <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>

        <label>Begintijd*</label>
        <input type="time" name="begintijd" required>

        <label>Eindtijd*</label>
        <input type="time" name="eindtijd" required>

        <label>Opmerkingen</label>
        <textarea name="opmerkingen"></textarea>

        <div class="form-actions">
            <button type="submit" name="opslaan" class="btn">💾 Opslaan</button>
            <a href="werkbon_detail.php?id=<?= $werkbon_id ?>" class="btn btn-secondary">Annuleren</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$pageTitle = "Uren toevoegen";
include __DIR__ . "/template/template.php";
