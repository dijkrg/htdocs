<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// Alleen ingelogde gebruikers
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$pageTitle = "Nieuw artikel toevoegen";

// Uploadmap aanmaken indien niet aanwezig
$upload_dir = __DIR__ . '/uploads/artikelen/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

// ─────────────────────────────
// Formulierverwerking
// ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artikelnummer = trim($_POST['artikelnummer']);
    $omschrijving  = trim($_POST['omschrijving']);
    $verkoopprijs  = floatval($_POST['verkoopprijs']);
    $btw_tarief    = intval($_POST['btw_tarief']);
    $categorie_id  = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
    $eenheid       = trim($_POST['eenheid']);
    $minimale_voorraad = isset($_POST['minimale_voorraad']) ? intval($_POST['minimale_voorraad']) : 0;

    if ($artikelnummer === '' || $omschrijving === '') {
        setFlash("Artikelnummer en omschrijving zijn verplicht.", "error");
    } else {
        // ✅ Nieuw artikel invoegen
        $stmt = $conn->prepare("
            INSERT INTO artikelen (artikelnummer, omschrijving, verkoopprijs, btw_tarief, categorie_id, eenheid, minimale_voorraad)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdiisi", 
            $artikelnummer, 
            $omschrijving, 
            $verkoopprijs, 
            $btw_tarief, 
            $categorie_id, 
            $eenheid,
            $minimale_voorraad
        );
        $stmt->execute();
        $artikel_id = $conn->insert_id;

        // ✅ Afbeelding uploaden
        if (!empty($_FILES['afbeelding']['name'])) {
            $file = $_FILES['afbeelding'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $newName = 'artikel_' . $artikel_id . '_' . time() . '.' . $ext;
                $target = $upload_dir . $newName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $conn->query("UPDATE artikelen SET afbeelding = '" . $conn->real_escape_string($newName) . "' WHERE artikel_id = " . (int)$artikel_id);
                } else {
                    setFlash("Uploaden mislukt. Controleer bestandsrechten van /uploads/artikelen/.", "error");
                }
            } else {
                setFlash("Ongeldig bestandstype. Alleen JPG, PNG of GIF toegestaan.", "error");
            }
        }

        setFlash("Nieuw artikel succesvol toegevoegd ✅", "success");
        header("Location: artikelen.php");
        exit;
    }
}

// ─────────────────────────────
// Categorieën ophalen
// ─────────────────────────────
$cats = $conn->query("SELECT id, naam FROM categorieen ORDER BY naam ASC");

ob_start();
?>

<!-- 🧭 PAGINA HEADER -->
<div class="page-header">
    <h2>➕ Nieuw artikel</h2>
    <div class="header-actions">
        <a href="artikelen.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<!-- 📋 FORMULIER -->
<div class="card">
<form method="post" enctype="multipart/form-data" class="form-split">

    <!-- 🔹 LINKER KOLOM -->
    <div class="form-left">
        <label for="artikelnummer">Artikelnummer *</label>
        <input type="text" name="artikelnummer" id="artikelnummer" required>

        <label for="omschrijving">Omschrijving *</label>
        <input type="text" name="omschrijving" id="omschrijving" required>

        <label for="verkoopprijs">Verkoopprijs (€)</label>
        <input type="number" step="0.01" name="verkoopprijs" id="verkoopprijs" placeholder="0.00">

        <label for="btw_tarief">BTW-tarief (%)</label>
        <input type="number" step="1" name="btw_tarief" id="btw_tarief" value="21">

        <label for="categorie_id">Categorie</label>
        <select name="categorie_id" id="categorie_id">
            <option value="">-- Geen categorie --</option>
            <?php while ($c = $cats->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['naam']) ?></option>
            <?php endwhile; ?>
        </select>

        <label for="eenheid">Eenheid</label>
        <input type="text" name="eenheid" id="eenheid" placeholder="bijv. stuk, meter, liter">

        <label for="minimale_voorraad">Minimale voorraad</label>
        <input type="number" name="minimale_voorraad" id="minimale_voorraad" min="0" step="1" value="0">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="artikelen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </div>

    <!-- 🔹 RECHTER KOLOM (AFBEELDING) -->
    <div class="form-right">
        <label>Afbeelding</label>
        <div class="image-box">
            <img id="preview" src="/template/no-image.png" alt="Voorbeeld">
        </div>
        <input type="file" name="afbeelding" id="afbeelding" accept="image/*" style="margin-top:10px;" onchange="previewImage(event)">
    </div>

</form>
</div>

<!-- 🎨 STIJL -->
<style>
.form-split {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    align-items: start;
}

.form-left label, .form-right label {
    display: block;
    font-weight: 600;
    margin-top: 10px;
}

.image-box {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 200px;
    background: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 8px;
    overflow: hidden;
}

.image-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.form-actions {
    margin-top: 20px;
}

@media (max-width: 900px) {
    .form-split {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- 🔄 Preview -->
<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const preview = document.getElementById('preview');
    preview.src = URL.createObjectURL(file);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
?>
