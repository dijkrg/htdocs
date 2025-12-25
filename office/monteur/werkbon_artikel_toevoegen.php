<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    setFlash("Geen werkbon ID ontvangen.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Controle of de werkbon van deze monteur is
$stmt = $conn->prepare("
    SELECT werkbonnummer 
    FROM werkbonnen 
    WHERE werkbon_id = ? AND monteur_id = ?
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();

if (!$wb) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Artikelen laden
$artikelen = $conn->query("
    SELECT artikel_id, omschrijving 
    FROM artikelen
    ORDER BY omschrijving ASC
");

// Verwerking formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artikel_id = (int)$_POST['artikel_id'];
    $aantal = (int)$_POST['aantal'];

    if ($artikel_id <= 0 || $aantal <= 0) {
        setFlash("Selecteer een artikel en vul een geldig aantal in.", "error");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO werkbon_artikelen (werkbon_id, artikel_id, aantal)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $werkbon_id, $artikel_id, $aantal);

        if ($stmt->execute()) {
            setFlash("Artikel toegevoegd.", "success");
            header("Location: /monteur/werkbon_view.php?id=$werkbon_id");
            exit;
        } else {
            setFlash("Opslaan mislukt.", "error");
        }
    }
}

$pageTitle = "Artikel toevoegen";
ob_start();
?>

<a href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>" class="back-btn">⬅ Terug</a>

<h2 class="page-title">Artikel toevoegen</h2>

<div class="wb-section">

    <form method="post" class="form-grid">

        <div class="full">
            <label>Artikel *</label>
            <select name="artikel_id" required>
                <option value="">-- Kies artikel --</option>
                <?php while($a = $artikelen->fetch_assoc()): ?>
                    <option value="<?= $a['artikel_id'] ?>">
                        <?= htmlspecialchars($a['omschrijving']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="full">
            <label>Aantal *</label>
            <input type="number" name="aantal" min="1" required>
        </div>

        <div class="full">
            <button class="btn-primary">Toevoegen</button>
        </div>

    </form>

</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
