<?php
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

// Monteurs, maar eventueel ook Manager/Planner mogen afronden:
$allowedRoles = ['Monteur','Planner','Manager','Admin'];
if (!in_array($rol, $allowedRoles, true)) {
    setFlash("Geen toegang tot afronden.", "error");
    header("Location: /index.php");
    exit;
}

$werkbon_id = intval($_GET['id'] ?? 0);
if ($werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /monteur/monteur_planning.php");
    exit;
}

/* 🔍 Werkbon + klant + monteur ophalen */
$stmt = $conn->prepare("
    SELECT 
        w.*,
        k.bedrijfsnaam,
        k.email AS klant_email,
        k.telefoonnummer,
        CONCAT_WS(', ', k.adres, k.postcode, k.plaats) AS klant_adres_vol,
        wa.bedrijfsnaam AS wa_bedrijfsnaam,
        CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats) AS wa_adres_vol,
        wa.telefoon AS wa_tel,
        m.email AS monteur_email,
        m.voornaam, m.achternaam
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN medewerkers m ON w.monteur_id = m.medewerker_id
    WHERE w.werkbon_id = ?
");
$stmt->bind_param('i', $werkbon_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$werkbon) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: /monteur/monteur_planning.php");
    exit;
}

// Check: monteur mag alleen eigen werkbonnen afronden (tenzij Planner/Manager/Admin)
if ($rol === 'Monteur' && (int)$werkbon['monteur_id'] !== (int)$monteur_id) {
    setFlash("Je mag alleen je eigen werkbonnen afronden.", "error");
    header("Location: /monteur/monteur_planning.php");
    exit;
}

$isGereed = (int)($werkbon['werk_gereed'] ?? 0) === 1;

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtDateNL($d) {
    if (!$d || $d === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d-m-Y') : '';
}

/* =========================================
   POST: Afronden verwerken
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isGereed && $rol === 'Monteur') {
        // Monteur mag gereed werk niet opnieuw afronden
        setFlash("Deze werkbon is al afgerond.", "error");
        header("Location: /monteur/monteur_planning.php");
        exit;
    }

    $aanvullend   = trim($_POST['aanvullende_omschrijving'] ?? '');
    $advies       = trim($_POST['toelichting_advies'] ?? '');
    $werk_gereed  = ($_POST['werk_gereed'] ?? 'Nee') === 'Ja' ? 1 : 0;

    $mail_planning = !empty($_POST['mail_planning']);
    $mail_klant    = !empty($_POST['mail_klant']);
    $mail_monteur  = !empty($_POST['mail_monteur']);

    // 🔹 1. Omschrijving bijwerken (aanvulling plakken onder bestaande tekst)
    $nieuweOmschrijving = $werkbon['omschrijving'] ?? '';
    if ($aanvullend !== '') {
        $blok = "\n\n--- Aanvulling monteur (" . date('d-m-Y H:i') . ") ---\n" . $aanvullend;
        $nieuweOmschrijving = trim($nieuweOmschrijving . $blok);
    }

    // 🔹 2. Handtekening verwerken
    $handtekeningPad = $werkbon['handtekening_klant'] ?? null;

    if (!empty($_POST['signature_data'])) {
        $dataUrl = $_POST['signature_data']; // "data:image/png;base64,...."
        if (str_starts_with($dataUrl, 'data:image')) {
            $parts = explode(',', $dataUrl, 2);
            if (count($parts) === 2) {
                $base64 = $parts[1];
                $binary = base64_decode($base64);

                if ($binary !== false && strlen($binary) < 10 * 1024 * 1024) { // < 10MB
                    $dir = __DIR__ . '/../uploads/handtekeningen';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }

                    $filename = 'wb_' . $werkbon_id . '_' . date('Ymd_His') . '.png';
                    $fullpath = $dir . '/' . $filename;

                    if (file_put_contents($fullpath, $binary) !== false) {
                        $handtekeningPad = '/uploads/handtekeningen/' . $filename;
                    }
                }
            }
        }
    }

    // 🔹 3. Werkbon updaten
    $nieuweStatus = $werkbon['status'];
    if ($werk_gereed === 1 && $werkbon['status'] !== 'Compleet' && $werkbon['status'] !== 'Afgehandeld') {
        $nieuweStatus = 'Compleet';
    }

    $stmt = $conn->prepare("
        UPDATE werkbonnen
        SET omschrijving = ?, 
            toelichting_advies = ?, 
            werk_gereed = ?, 
            status = ?, 
            handtekening_klant = ?
        WHERE werkbon_id = ?
    ");
    $stmt->bind_param(
        'ssissi',
        $nieuweOmschrijving,
        $advies,
        $werk_gereed,
        $nieuweStatus,
        $handtekeningPad,
        $werkbon_id
    );
    $stmt->execute();
    $stmt->close();

    // 🔹 4. Mails versturen (TODO: koppel dit aan jouw mailsysteem)
    $onderwerp = "Werkbon {$werkbon['werkbonnummer']} afgerond door monteur";
    $body = "Beste,\n\n"
          . "Werkbon {$werkbon['werkbonnummer']} is zojuist afgerond.\n"
          . "Datum: " . fmtDateNL($werkbon['uitvoerdatum']) . "\n"
          . "Klant: " . ($werkbon['bedrijfsnaam'] ?? '-') . "\n"
          . "Monteur: " . trim(($werkbon['voornaam'] ?? '') . ' ' . ($werkbon['achternaam'] ?? '')) . "\n\n"
          . "Status: " . ($werk_gereed ? 'Werk gereed' : $nieuweStatus) . "\n\n"
          . "Met vriendelijke groet,\n"
          . "ABCB Brandbeveiliging\n";

    // Hier kun je jouw eigen mailfunctie gebruiken, bijv: sendMail($to,$subject,$body)
    // Ik gebruik nu PHP mail() als placeholder (pas dit gerust aan of vervang het).

    // Planning e-mailadres (TODO: netjes uit bedrijfsgegevens halen)
    $planningEmail = 'planning@' . ($_SERVER['SERVER_NAME'] ?? 'example.com');

    if ($mail_planning && filter_var($planningEmail, FILTER_VALIDATE_EMAIL)) {
        @mail($planningEmail, $onderwerp, $body);
    }
    if ($mail_klant && !empty($werkbon['klant_email']) && filter_var($werkbon['klant_email'], FILTER_VALIDATE_EMAIL)) {
        @mail($werkbon['klant_email'], $onderwerp, $body);
    }
    if ($mail_monteur && !empty($werkbon['monteur_email']) && filter_var($werkbon['monteur_email'], FILTER_VALIDATE_EMAIL)) {
        @mail($werkbon['monteur_email'], $onderwerp, $body);
    }

    setFlash("Werkbon is succesvol afgerond" . ($werk_gereed ? " en gemarkeerd als gereed." : "."), "success");
    header("Location: /monteur/monteur_planning.php?datum=" . urlencode($werkbon['uitvoerdatum']));
    exit;
}

/* =========================================
   GET: Formulier tonen
========================================= */

$pageTitle = "Werkbon afronden";
ob_start();
?>

<div class="page-header">
    <h2>✅ Werkbon afronden</h2>
    <p>Werkbon <strong><?= e($werkbon['werkbonnummer']) ?></strong> afronden.</p>
</div>

<div class="card">
    <h3>Werkbon gegevens</h3>
    <table class="detail-table">
        <tr><th>Werkbonnummer</th><td><?= e($werkbon['werkbonnummer']) ?></td></tr>
        <tr><th>Datum</th><td><?= e(fmtDateNL($werkbon['uitvoerdatum'])) ?></td></tr>
        <tr><th>Monteur</th><td><?= e(trim(($werkbon['voornaam'] ?? '').' '.($werkbon['achternaam'] ?? ''))) ?></td></tr>
        <tr>
            <th>Klant / werkadres</th>
            <td>
                <strong><?= e($werkbon['bedrijfsnaam'] ?? '-') ?></strong><br>
                <?php
                    $adres = $werkbon['wa_adres_vol'] ?? '';
                    if (!$adres) $adres = $werkbon['klant_adres_vol'] ?? '';
                    echo e($adres);
                ?>
            </td>
        </tr>
    </table>
</div>

<?php if ($isGereed): ?>
<div class="card" style="border-left:4px solid #16a34a;">
    <strong>Let op:</strong> deze werkbon is al als <strong>Werk gereed</strong> gemarkeerd.
    <?php if (in_array($rol, ['Planner','Manager','Admin'], true)): ?>
        <br>Je kunt de gegevens nog aanpassen en opnieuw mailen.
    <?php else: ?>
        <br>Je kunt de gegevens bekijken, maar niet meer wijzigen.
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" class="card" onsubmit="return prepareSignatureForSubmit();">
    <h3>Afronden</h3>

    <div class="form-grid">
        <div class="form-group">
            <label for="aanvullende_omschrijving">Aanvullende werkomschrijving</label>
            <textarea name="aanvullende_omschrijving" id="aanvullende_omschrijving" rows="4"
                      placeholder="Bijvoorbeeld extra werkzaamheden, bijzonderheden, etc."></textarea>
        </div>

        <div class="form-group">
            <label for="toelichting_advies">Toelichting / Advies</label>
            <textarea name="toelichting_advies" id="toelichting_advies" rows="4"
                      placeholder="Advies aan klant / planning (wordt opgeslagen in werkbon)."><?= e($werkbon['toelichting_advies'] ?? '') ?></textarea>
        </div>
    </div>

    <hr>

    <div class="form-grid">
        <div class="form-group">
            <label>Handtekening klant</label>
            <p style="font-size:12px; color:#555;">Laat de klant tekenen in het vlak hieronder.</p>
            <div style="border:1px solid #ddd; border-radius:6px; padding:6px; background:#f9fafb;">
                <canvas id="signaturePad" width="400" height="180" style="background:#fff; border-radius:4px; border:1px solid #e5e7eb;"></canvas>
                <div style="margin-top:6px; display:flex; justify-content:space-between;">
                    <button type="button" class="btn btn-small btn-secondary" onclick="clearSignature()">🧹 Wissen</button>
                    <span style="font-size:12px; color:#6b7280;">Onderteken met vinger of muis.</span>
                </div>
            </div>
            <?php if (!empty($werkbon['handtekening_klant'])): ?>
                <p style="margin-top:6px; font-size:12px;">
                    Bestaande handtekening: <br>
                    <img src="<?= e($werkbon['handtekening_klant']) ?>" alt="Handtekening klant" style="max-width:200px; border:1px solid #ddd; border-radius:4px; background:#fff;">
                </p>
            <?php endif; ?>
            <input type="hidden" name="signature_data" id="signature_data">
        </div>

        <div class="form-group">
            <label>Werk gereed</label>
            <select name="werk_gereed" id="werk_gereed">
                <option value="Nee" <?= !$isGereed ? 'selected' : '' ?>>Nee, nog niet volledig gereed</option>
                <option value="Ja"  <?=  $isGereed ? 'selected' : '' ?>>Ja, werkzaamheden zijn gereed</option>
            </select>

            <div style="margin-top:10px;">
                <label><strong>Mail versturen naar:</strong></label>
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="mail_planning" value="1" checked>
                        Planning
                    </label><br>
                    <label>
                        <input type="checkbox" name="mail_klant" value="1">
                        Klant <?= $werkbon['klant_email'] ? '(' . e($werkbon['klant_email']) . ')' : '(geen e-mail bekend)' ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="mail_monteur" value="1">
                        Monteur <?= $werkbon['monteur_email'] ? '(' . e($werkbon['monteur_email']) . ')' : '' ?>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:15px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <a href="/monteur/monteur_planning.php?datum=<?= e($werkbon['uitvoerdatum']) ?>" class="btn btn-secondary">
            ⬅ Terug naar planning
        </a>
        <div style="display:flex; gap:8px;">
            <button type="submit" name="action" value="save" class="btn">
                💾 Opslaan & afronden
            </button>
            <button type="submit" name="action" value="save_mail" class="btn btn-accent">
                ✅ Afronden + mailen
            </button>
        </div>
    </div>
</form>

<style>
.form-grid {
    display:grid;
    grid-template-columns: minmax(0,1.5fr) minmax(0,1fr);
    gap:16px;
}
@media (max-width: 900px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
.form-group label {
    font-weight:600;
    font-size:14px;
}
.form-group textarea,
.form-group select {
    width:100%;
    margin-top:4px;
}
.checkbox-group label {
    font-size:14px;
}
</style>

<script>
let canvas, ctx, drawing = false, hasDrawn = false;

document.addEventListener('DOMContentLoaded', function() {
    canvas = document.getElementById('signaturePad');
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#111827';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches[0]) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    function startDraw(e) {
        drawing = true;
        hasDrawn = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }

    function draw(e) {
        if (!drawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        e.preventDefault();
    }

    function endDraw(e) {
        if (!drawing) return;
        drawing = false;
        e.preventDefault();
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);

    canvas.addEventListener('touchstart', startDraw, {passive:false});
    canvas.addEventListener('touchmove', draw, {passive:false});
    canvas.addEventListener('touchend', endDraw, {passive:false});
});

function clearSignature() {
    if (!canvas || !ctx) return;
    ctx.clearRect(0,0,canvas.width, canvas.height);
    hasDrawn = false;
}

function prepareSignatureForSubmit() {
    const sigInput = document.getElementById('signature_data');
    if (canvas && ctx && hasDrawn) {
        sigInput.value = canvas.toDataURL('image/png');
    } else {
        sigInput.value = '';
    }
    return true;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
