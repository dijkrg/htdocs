<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = isset($_GET['werkbon_id']) ? (int)$_GET['werkbon_id'] : 0;

if ($werkbon_id <= 0) {
    setFlash("Ongeldige werkbon.", "error");
    header("Location: /monteur/index.php");
    exit;
}

/* ------------------------------------------------------------
   CHECK OF DE WERKBON BIJ DE MONTEUR HOORT
------------------------------------------------------------ */
$chk = $conn->prepare("
    SELECT werkbon_id, klant_id, werkadres_id 
    FROM werkbonnen 
    WHERE werkbon_id=? AND monteur_id=?
");
$chk->bind_param("ii", $werkbon_id, $monteur_id);
$chk->execute();
$wb = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$wb) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/index.php");
    exit;
}

$klant_id     = (int)$wb['klant_id'];
$werkadres_id = (int)$wb['werkadres_id'];

/* ------------------------------------------------------------
   VOORGESTELDE OBJECTCODE GENEREREN
------------------------------------------------------------ */
$yearPrefix = date("y"); // "25"
$stmt = $conn->prepare("
    SELECT code 
    FROM objecten 
    WHERE code LIKE CONCAT(?, '%')
    ORDER BY code DESC
    LIMIT 1
");
$stmt->bind_param("s", $yearPrefix);
$stmt->execute();
$lastObj = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($lastObj) {
    $lastNum = intval(substr($lastObj['code'], 2)); 
    $newNum  = $lastNum + 1;
} else {
    $newNum = 1;
}

$voorstelCode = $yearPrefix . str_pad($newNum, 5, '0', STR_PAD_LEFT);

/* ------------------------------------------------------------
   VERWERKEN VAN HET FORMULIER
------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code          = trim($_POST['code']);
    $omschrijving  = trim($_POST['omschrijving']);
    $merk          = trim($_POST['merk']);
    $type          = trim($_POST['type']);
    $fabricagejaar = (!empty($_POST['fabricagejaar']) ? (int)$_POST['fabricagejaar'] : null);
    $locatie       = trim($_POST['locatie']);
    $verdieping    = trim($_POST['verdieping']);

    if (empty($code) || empty($omschrijving)) {
        setFlash("Code en omschrijving zijn verplicht.", "error");
    } else {

        /* ------------------------------------------------------------
           FOTO UPLOAD
        ------------------------------------------------------------ */
        $fotoPad = null;

        if (!empty($_FILES['foto']['name'])) {

            $allowed = ['image/jpeg','image/png','image/webp'];
            if (in_array($_FILES['foto']['type'], $allowed)) {

                $dir = __DIR__ . '/../uploads/objecten/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $newFilename = "obj_{$werkbon_id}_" . time() . "." . $ext;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $newFilename)) {
                    $fotoPad = "uploads/objecten/" . $newFilename;
                }
            }
        }

        /* ------------------------------------------------------------
           OBJECT OPSLAAN
        ------------------------------------------------------------ */
        $stmt = $conn->prepare("
            INSERT INTO objecten 
            (code, omschrijving, klant_id, werkadres_id, locatie, verdieping, merk, type, fabricagejaar, afbeelding, datum_installatie)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "ssisssss is",
            $code,
            $omschrijving,
            $klant_id,
            $werkadres_id,
            $locatie,
            $verdieping,
            $merk,
            $type,
            $fabricagejaar,
            $fotoPad
        );

        if ($stmt->execute()) {

            $object_id = $stmt->insert_id;

            /* Koppelen aan werkbon */
            $k = $conn->prepare("
                INSERT INTO werkbon_objecten (werkbon_id, object_id)
                VALUES (?, ?)
            ");
            $k->bind_param("ii", $werkbon_id, $object_id);
            $k->execute();
            $k->close();

            setFlash("Object succesvol toegevoegd.", "success");
            header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#objecten");
            exit;
        }

        setFlash("Fout bij opslaan: " . $stmt->error, "error");
        $stmt->close();
    }
}

$pageTitle = "Object toevoegen";
ob_start();
?>

<style>
.card {
    background:#fff;
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}
.card label {
    font-weight:600;
    margin-top:10px;
    display:block;
}
.card input {
    width:100%;
    padding:10px;
    border:1px solid #ccc;
    border-radius:6px;
}
.preview-img {
    max-width:180px;
    margin-top:10px;
    border-radius:6px;
    display:none;
}
</style>

<h2>➕ Nieuw object toevoegen</h2>

<div class="card">
<form method="post" enctype="multipart/form-data">

    <label>Foto (optioneel)</label>
    <input type="file" name="foto" id="fotoInput" accept="image/*" capture="environment">
    <img id="fotoPreview" class="preview-img">

    <label>Objectcode *</label>
    <input type="text" name="code" value="<?= htmlspecialchars($voorstelCode) ?>" required>

    <label>Omschrijving *</label>
    <input type="text" name="omschrijving" required>

    <label>Merk</label>
    <input type="text" name="merk">

    <label>Type</label>
    <input type="text" name="type">

    <label>Fabricagejaar</label>
    <input type="number" name="fabricagejaar" min="1900" max="<?= date('Y') ?>">

    <label>Locatie</label>
    <input type="text" name="locatie">

    <label>Verdieping</label>
    <input type="text" name="verdieping">

    <button type="submit" class="btn-primary" style="margin-top:15px;width:100%;">✔ Opslaan</button>
</form>
</div>

<a href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>" class="btn btn-secondary" style="width:100%;">⬅ Terug</a>

<script>
document.getElementById('fotoInput').addEventListener('change', function(){
    const file = this.files[0];
    const prev = document.getElementById('fotoPreview');
    if (file){
        prev.src = URL.createObjectURL(file);
        prev.style.display = 'block';
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/monteur_template.php';
?>
