a<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: /login.php");
    exit;
}

$rol        = $_SESSION['user']['rol'] ?? '';
$monteur_id = $_SESSION['user']['medewerker_id'] ?? null;

// Alleen monteurs (eventueel kun je hier Manager/Planner toevoegen als je wilt testen)
if ($rol !== 'Monteur') {
    setFlash("Geen toegang tot monteurplanning.", "error");
    header("Location: /index.php");
    exit;
}

if (!$monteur_id) {
    setFlash("Geen monteur-ID gevonden in sessie.", "error");
    header("Location: /index.php");
    exit;
}

// 📅 Datum-logica
$today = new DateTimeImmutable('today');
$datumParam = $_GET['datum'] ?? $today->format('Y-m-d');

// Validatie datum
try {
    $datum = new DateTimeImmutable($datumParam);
} catch (Exception $e) {
    $datum = $today;
}
$datumSql = $datum->format('Y-m-d');

// Voor navigatie
$prevDate = $datum->modify('-1 day')->format('Y-m-d');
$nextDate = $datum->modify('+1 day')->format('Y-m-d');

// 🔍 Werkbonnen van deze monteur op deze dag
$stmt = $conn->prepare("
    SELECT 
        w.*,
        k.bedrijfsnaam,
        k.telefoonnummer,
        CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats) AS werkadres_vol,
        wa.telefoon AS werkadres_tel
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    WHERE w.gearchiveerd = 0
      AND w.monteur_id = ?
      AND w.uitvoerdatum = ?
      AND w.status IN ('Ingepland','Compleet')
    ORDER BY 
        w.werk_gereed ASC,               -- niet-gereed eerst
        (w.starttijd IS NULL), w.starttijd,
        w.werkbonnummer
");
$stmt->bind_param('is', $monteur_id, $datumSql);
$stmt->execute();
$result = $stmt->get_result();

$werkbonnen = [];
while ($row = $result->fetch_assoc()) {
    $werkbonnen[] = $row;
}
$stmt->close();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtTime($t)  { return $t ? substr($t, 0, 5) : ''; }
function fmtDateNL($d) {
    if (!$d || $d === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d-m-Y') : '';
}

$pageTitle = "Mijn planning";
ob_start();
?>

<div class="page-header">
    <h2>🧑‍🔧 Mijn planning</h2>
    <p>Overzicht van jouw ingeplande werkbonnen per dag.</p>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <strong>Datum:</strong> <?= e(fmtDateNL($datumSql)) ?>
        </div>
        <div style="display:flex; gap:6px;">
            <a href="?datum=<?= e($prevDate) ?>" class="btn btn-secondary">◀ Vorige dag</a>
            <a href="?datum=<?= e($today->format('Y-m-d')) ?>" class="btn btn-secondary">📅 Vandaag</a>
            <a href="?datum=<?= e($nextDate) ?>" class="btn btn-secondary">Volgende dag ▶</a>
        </div>
    </div>
</div>

<div class="card">
    <h3>Werkbonnen op <?= e(fmtDateNL($datumSql)) ?></h3>

    <?php if (empty($werkbonnen)): ?>
        <p style="color:#777;">Er zijn geen werkbonnen voor deze dag.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
            <?php foreach ($werkbonnen as $wb): 
                $isGereed = (int)($wb['werk_gereed'] ?? 0) === 1;
                $start    = fmtTime($wb['starttijd']);
                $eind     = fmtTime($wb['eindtijd']);
                $tijd     = trim($start . ($eind ? ' - ' . $eind : ''));
                $adres    = $wb['werkadres_vol'] ?: ($wb['klant_adres'] ?? '');
                $telefoon = $wb['werkadres_tel'] ?: ($wb['telefoonnummer'] ?? '');
            ?>
            <div class="werkbon-card <?= $isGereed ? 'wb-gereed' : '' ?>">
                <div class="wb-header-row">
                    <div>
                        <span class="wb-nr">WB <?= e($wb['werkbonnummer']) ?></span>
                        <?php if ($tijd): ?>
                            <span class="wb-time-badge"><?= e($tijd) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="wb-status-row">
                        <span class="wb-status-label <?= $isGereed ? 'status-gereed' : 'status-open' ?>">
                            <?= $isGereed ? 'Werk gereed' : e($wb['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="wb-body">
                    <div><strong>Klant:</strong> <?= e($wb['bedrijfsnaam'] ?? '-') ?></div>
                    <?php if ($adres): ?>
                        <div><strong>Adres:</strong> <?= e($adres) ?></div>
                    <?php endif; ?>
                    <?php if ($telefoon): ?>
                        <div><strong>Tel:</strong> <a href="tel:<?= e($telefoon) ?>"><?= e($telefoon) ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($wb['omschrijving'])): ?>
                        <div class="wb-omschr">
                            <strong>Omschrijving:</strong><br>
                            <?= nl2br(e($wb['omschrijving'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="wb-footer">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="/werkbon_detail.php?id=<?= (int)$wb['werkbon_id'] ?>" class="btn btn-small">
                            📄 Open werkbon (volledig)
                        </a>
                        <?php if (!$isGereed): ?>
                            <a href="/monteur/monteur_afronden.php?id=<?= (int)$wb['werkbon_id'] ?>" class="btn btn-small btn-accent">
                                ✅ Afronden
                            </a>
                        <?php else: ?>
                            <span class="wb-readonly">🔒 Alleen lezen (afgerond)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.werkbon-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
    background: #fff;
}
.werkbon-card.wb-gereed {
    background: #f3f4f6;
    opacity: 0.75;
}
.wb-header-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:6px;
}
.wb-nr {
    font-weight:600;
    font-size:14px;
}
.wb-time-badge {
    display:inline-block;
    margin-left:8px;
    padding:2px 8px;
    font-size:12px;
    border-radius:999px;
    background:#e5f2ff;
    color:#2954cc;
}
.wb-status-row {
    font-size:12px;
}
.wb-status-label {
    padding:2px 8px;
    border-radius:999px;
}
.status-open {
    background:#fff9c4;
    color:#8d6e00;
}
.status-gereed {
    background:#e0f2f1;
    color:#00695c;
}
.wb-body {
    font-size:13px;
    line-height:1.4;
}
.wb-omschr {
    margin-top:4px;
}
.wb-footer {
    margin-top:8px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:12px;
}
.wb-readonly {
    color:#6b7280;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
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

// ─────────────────────────────
// Artikel ophalen
// ─────────────────────────────
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash("Ongeldig artikel.", "error");
    header("Location: artikelen.php");
    exit;
}
$artikel_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM artikelen WHERE artikel_id = ?");
$stmt->bind_param("i", $artikel_id);
$stmt->execute();
$artikel = $stmt->get_result()->fetch_assoc();

if (!$artikel) {
    setFlash("Artikel niet gevonden.", "error");
    header("Location: artikelen.php");
    exit;
}

// ─────────────────────────────
// 🔁 Vorige / Volgende artikel-id bepalen
// ─────────────────────────────
$prev = $conn->query("SELECT artikel_id FROM artikelen WHERE artikel_id < $artikel_id ORDER BY artikel_id DESC LIMIT 1")->fetch_assoc();
$next = $conn->query("SELECT artikel_id FROM artikelen WHERE artikel_id > $artikel_id ORDER BY artikel_id ASC LIMIT 1")->fetch_assoc();
$prev_id = $prev['artikel_id'] ?? null;
$next_id = $next['artikel_id'] ?? null;

// ─────────────────────────────
// Uploadmap instellen
// ─────────────────────────────
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
    $afbeelding    = $artikel['afbeelding'];

    // ✅ Upload nieuwe afbeelding
    if (!empty($_FILES['afbeelding']['name'])) {
        $file = $_FILES['afbeelding'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $newName = 'artikel_' . $artikel_id . '_' . time() . '.' . $ext;
            $target = $upload_dir . $newName;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                if (!empty($artikel['afbeelding']) && file_exists($upload_dir . $artikel['afbeelding'])) {
                    unlink($upload_dir . $artikel['afbeelding']);
                }
                $afbeelding = $newName;
            } else {
                setFlash("Uploaden mislukt. Controleer bestandsrechten van /uploads/artikelen/.", "error");
            }
        } else {
            setFlash("Ongeldig bestandstype. Alleen JPG, PNG of GIF toegestaan.", "error");
        }
    }

    // ✅ Opslaan in database (inclusief minimale voorraad)
    $stmt = $conn->prepare("
        UPDATE artikelen 
        SET artikelnummer = ?, 
            omschrijving = ?, 
            verkoopprijs = ?, 
            btw_tarief = ?, 
            categorie_id = ?, 
            eenheid = ?, 
            minimale_voorraad = ?, 
            afbeelding = ? 
        WHERE artikel_id = ?
    ");
    $stmt->bind_param(
        "ssdiisssi",
        $artikelnummer,
        $omschrijving,
        $verkoopprijs,
        $btw_tarief,
        $categorie_id,
        $eenheid,
        $minimale_voorraad,
        $afbeelding,
        $artikel_id
    );
    $stmt->execute();

    setFlash("Wijzigingen opgeslagen ✅", "success");
    // Blijf op dezelfde pagina
    header("Location: artikel_bewerk.php?id=" . $artikel_id);
    exit;
}

$pageTitle = "Artikel bewerken";
ob_start();
?>

<!-- 🧭 PAGINA HEADER -->
<div class="page-header">
    <h2>✏️ Artikel bewerken</h2>
    <div class="header-actions">
        <a href="artikelen.php" class="btn btn-secondary">⬅ Terug</a>

        <?php if ($prev_id): ?>
            <a href="artikel_bewerk.php?id=<?= $prev_id ?>" class="btn btn-outline">⬅ Vorige</a>
        <?php endif; ?>

        <?php if ($next_id): ?>
            <a href="artikel_bewerk.php?id=<?= $next_id ?>" class="btn btn-outline">Volgende ➡</a>
        <?php endif; ?>
    </div>
</div>

<!-- 📋 FORMULIER -->
<div class="card">
<form method="post" enctype="multipart/form-data" class="form-split">

    <!-- 🔹 LINKER KOLOM -->
    <div class="form-left">
        <label for="artikelnummer">Artikelnummer *</label>
        <input type="text" name="artikelnummer" id="artikelnummer" 
               value="<?= htmlspecialchars($artikel['artikelnummer']) ?>" required>

        <label for="omschrijving">Omschrijving *</label>
        <input type="text" name="omschrijving" id="omschrijving" 
               value="<?= htmlspecialchars($artikel['omschrijving']) ?>" required>

        <label for="verkoopprijs">Verkoopprijs (€)</label>
        <input type="number" step="0.01" name="verkoopprijs" id="verkoopprijs" 
               value="<?= number_format($artikel['verkoopprijs'], 2, '.', '') ?>">

        <label for="btw_tarief">BTW-tarief (%)</label>
        <input type="number" step="1" name="btw_tarief" id="btw_tarief" 
               value="<?= (int)$artikel['btw_tarief'] ?>">

        <label for="categorie_id">Categorie</label>
        <select name="categorie_id" id="categorie_id">
            <option value="">-- Geen categorie --</option>
            <?php
            $cats = $conn->query("SELECT id, naam FROM categorieen ORDER BY naam ASC");
            while ($c = $cats->fetch_assoc()):
            ?>
                <option value="<?= $c['id'] ?>" <?= ($c['id'] == $artikel['categorie_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['naam']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="eenheid">Eenheid</label>
        <input type="text" name="eenheid" id="eenheid" 
               value="<?= htmlspecialchars($artikel['eenheid'] ?? '') ?>" placeholder="bijv. stuk, meter, liter">

        <label for="minimale_voorraad">Minimale voorraad</label>
        <input type="number" name="minimale_voorraad" id="minimale_voorraad" 
               min="0" step="1" value="<?= (int)$artikel['minimale_voorraad'] ?>">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            <a href="artikelen.php" class="btn btn-secondary">Annuleren</a>
        </div>
    </div>

    <!-- 🔹 RECHTER KOLOM (AFBEELDING) -->
    <div class="form-right">
        <label>Afbeelding</label>
        <div class="image-box">
            <img id="preview" 
                 src="<?php
                    $path = $upload_dir . $artikel['afbeelding'];
                    echo (!empty($artikel['afbeelding']) && file_exists($path))
                        ? 'uploads/artikelen/' . htmlspecialchars($artikel['afbeelding'])
                        : '/template/no-image.png';
                 ?>" 
                 alt="Afbeelding">
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

.header-actions .btn-outline {
    background: #f5f5f5;
    border: 1px solid #ccc;
    font-size: 14px;
}
.header-actions .btn-outline:hover {
    background: #e9ecef;
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

<!-- 🔄 Live preview -->
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
