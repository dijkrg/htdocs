<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin/Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot PDF-instellingen.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "🧾 PDF-instellingen";

// Uploadmap voor logo’s
$upload_dir = __DIR__ . '/../uploads/logo/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

// Huidige instellingen ophalen (1 record)
$row = $conn->query("SELECT * FROM pdf_instellingen LIMIT 1")->fetch_assoc();
if (!$row) {
    $conn->query("INSERT INTO pdf_instellingen (bedrijfsnaam) VALUES ('ABCBrand')");
    $row = $conn->query("SELECT * FROM pdf_instellingen LIMIT 1")->fetch_assoc();
}

// 📥 Formulierverwerking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bedrijfsnaam  = trim($_POST['bedrijfsnaam']);
    $marge_boven   = (int)$_POST['marge_boven'];
    $marge_onder   = (int)$_POST['marge_onder'];
    $marge_links   = (int)$_POST['marge_links'];
    $marge_rechts  = (int)$_POST['marge_rechts'];
    $lettertype    = trim($_POST['lettertype']);
    $lettergrootte = (int)$_POST['lettergrootte'];
    $kleur_accent  = trim($_POST['kleur_accent']);
    $koptekst      = trim($_POST['koptekst']);
    $voettekst     = trim($_POST['voettekst']);
    $logo_bestand  = $row['logo_bestand'];

    // Upload nieuw logo
    if (!empty($_FILES['logo_bestand']['name'])) {
        $file = $_FILES['logo_bestand'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $newName = 'logo_' . time() . '.' . $ext;
            $target = $upload_dir . $newName;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                if (!empty($row['logo_bestand']) && file_exists($upload_dir . $row['logo_bestand'])) {
                    unlink($upload_dir . $row['logo_bestand']);
                }
                $logo_bestand = $newName;
            }
        }
    }

    // Opslaan
    $stmt = $conn->prepare("
        UPDATE pdf_instellingen SET
            bedrijfsnaam=?, marge_boven=?, marge_onder=?, marge_links=?, marge_rechts=?,
            lettertype=?, lettergrootte=?, kleur_accent=?, koptekst=?, voettekst=?, logo_bestand=?
        WHERE id=?
    ");
    $stmt->bind_param(
        "siiiisissssi",
        $bedrijfsnaam, $marge_boven, $marge_onder, $marge_links, $marge_rechts,
        $lettertype, $lettergrootte, $kleur_accent, $koptekst, $voettekst, $logo_bestand, $row['id']
    );
    $stmt->execute();

    setFlash("PDF-instellingen opgeslagen ✅", "success");
    header("Location: pdf_instellingen.php");
    exit;
}

ob_start();
?>

<div class="page-header">
    <h2>🧾 PDF-instellingen</h2>
    <div class="header-actions">
        <a href="../index.php" class="btn btn-secondary">⬅ Terug</a>
    </div>
</div>

<div class="card form-card">
<form method="post" enctype="multipart/form-data" class="form-split">
    <div class="form-left">
        <label>Bedrijfsnaam</label>
        <input type="text" name="bedrijfsnaam" value="<?= htmlspecialchars($row['bedrijfsnaam']) ?>">

        <label>Lettertype</label>
        <select name="lettertype">
            <?php
            $fonts = ['helvetica','dejavusans','times','courier'];
            foreach ($fonts as $f):
                $sel = ($row['lettertype'] === $f) ? 'selected' : '';
                echo "<option value='$f' $sel>$f</option>";
            endforeach;
            ?>
        </select>

        <label>Lettergrootte</label>
        <input type="number" name="lettergrootte" min="8" max="20" value="<?= (int)$row['lettergrootte'] ?>">

        <label>Kleuraccent</label>
        <input type="color" name="kleur_accent" value="<?= htmlspecialchars($row['kleur_accent']) ?>">

        <label>Marges (mm)</label>
        <div class="grid-4">
            <input type="number" name="marge_boven" placeholder="Boven" value="<?= $row['marge_boven'] ?>">
            <input type="number" name="marge_onder" placeholder="Onder" value="<?= $row['marge_onder'] ?>">
            <input type="number" name="marge_links" placeholder="Links" value="<?= $row['marge_links'] ?>">
            <input type="number" name="marge_rechts" placeholder="Rechts" value="<?= $row['marge_rechts'] ?>">
        </div>

        <label>Koptekst</label>
        <textarea name="koptekst" rows="2"><?= htmlspecialchars($row['koptekst']) ?></textarea>

        <label>Voettekst</label>
        <textarea name="voettekst" rows="2"><?= htmlspecialchars($row['voettekst']) ?></textarea>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="../index.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </div>

    <div class="form-right">
        <label>Logo</label>
        <div class="image-box">
            <?php if (!empty($row['logo_bestand']) && file_exists($upload_dir . $row['logo_bestand'])): ?>
                <img src="../uploads/logo/<?= htmlspecialchars($row['logo_bestand']) ?>" alt="Logo">
            <?php else: ?>
                <div class="placeholder">Geen logo</div>
            <?php endif; ?>
        </div>
        <input type="file" name="logo_bestand" accept="image/*" style="margin-top:10px;">
    </div>
</form>
</div>

<style>
.form-split {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}
.grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.image-box {
    display: flex; justify-content: center; align-items: center;
    width:100%; height:180px; background:#f8f9fa; border:1px solid #ccc; border-radius:8px;
}
.image-box img { max-height:100%; max-width:100%; object-fit:contain; }
.placeholder { color:#888; font-size:14px; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
