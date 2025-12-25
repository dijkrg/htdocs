<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id     = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id     = (int)($_GET['werkbon_id'] ?? 0);
$werkbon_uur_id = (int)($_GET['id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0 || $werkbon_uur_id <= 0) {
    setFlash("Ongeldige invoer.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* ---------------------------------------------------------
   Werkbon ophalen + ownership
---------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT werkbonnummer, uitvoerdatum
    FROM werkbonnen
    WHERE werkbon_id = ? AND monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

/* ---------------------------------------------------------
   Uurregel ophalen + ownership + lock check
---------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT werkbon_uur_id, uursoort_id, datum, begintijd, eindtijd, opmerkingen, goedgekeurd
    FROM werkbon_uren
    WHERE werkbon_uur_id = ? AND werkbon_id = ? AND monteur_id = ?
    LIMIT 1
");
$stmt->bind_param("iii", $werkbon_uur_id, $werkbon_id, $monteur_id);
$stmt->execute();
$uur = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$uur) {
    setFlash("Uurregel niet gevonden.", "error");
    header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#uren");
    exit;
}

if ((string)($uur['goedgekeurd'] ?? '') === 'goedgekeurd') {
    setFlash("Goedgekeurde uren kunnen niet worden aangepast.", "error");
    header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#uren");
    exit;
}

/* ---------------------------------------------------------
   Uursoorten (WERKBON -> uursoorten)
---------------------------------------------------------- */
$uursoorten = $conn->query("
    SELECT uursoort_id, code, omschrijving
    FROM uursoorten
    ORDER BY code ASC
");

/* ---------------------------------------------------------
   Verwerken POST
---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uursoort_id = (int)($_POST['uursoort_id'] ?? 0);
    $datum       = (string)($_POST['datum'] ?? '');
    $begintijd   = (string)($_POST['begintijd'] ?? '');
    $eindtijd    = (string)($_POST['eindtijd'] ?? '');
    $opmerkingen = trim((string)($_POST['opmerkingen'] ?? ''));

    if ($uursoort_id <= 0 || $datum === '' || $begintijd === '' || $eindtijd === '') {
        setFlash("Vul alle verplichte velden in.", "error");
    } else {
        $s = strtotime($begintijd);
        $e = strtotime($eindtijd);

        if ($s === false || $e === false) {
            setFlash("Ongeldige tijd.", "error");
        } elseif ($e <= $s) {
            setFlash("Eindtijd moet later zijn dan starttijd.", "error");
        } else {
            $up = $conn->prepare("
                UPDATE werkbon_uren
                SET uursoort_id = ?, datum = ?, begintijd = ?, eindtijd = ?, opmerkingen = ?
                WHERE werkbon_uur_id = ? AND werkbon_id = ? AND monteur_id = ?
                LIMIT 1
            ");
            // correct: i s s s s i i i
            $up->bind_param(
                "issssiii",
                $uursoort_id,
                $datum,
                $begintijd,
                $eindtijd,
                $opmerkingen,
                $werkbon_uur_id,
                $werkbon_id,
                $monteur_id
            );

            if ($up->execute()) {
                $up->close();
                setFlash("Uurregel bijgewerkt.", "success");
                header("Location: /monteur/werkbon_view.php?id={$werkbon_id}#uren");
                exit;
            }
            setFlash("Fout bij opslaan: " . $up->error, "error");
            $up->close();
        }
    }

    // Bij fout: form opnieuw vullen
    $uur['uursoort_id'] = $uursoort_id;
    $uur['datum']       = $datum;
    $uur['begintijd']   = $begintijd;
    $uur['eindtijd']    = $eindtijd;
    $uur['opmerkingen'] = $opmerkingen;
}

$pageTitle = "Uur bewerken";
ob_start();
?>
<div class="page-section">

  <div class="header-actions" style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">
    <div style="flex:1"></div>
    <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>#uren" class="btn btn-secondary">⬅ Annuleren</a>
    <button type="submit" form="uurForm" class="btn btn-primary">💾 Opslaan</button>
  </div>

  <h2 class="section-title">Uur bewerken</h2>

  <div class="card">
    <p>
      <strong>Werkbon:</strong> <?= htmlspecialchars((string)$wb['werkbonnummer']) ?><br>
      <strong>Datum werkbon:</strong> <?= !empty($wb['uitvoerdatum']) ? date("d-m-Y", strtotime((string)$wb['uitvoerdatum'])) : '-' ?>
    </p>

    <form id="uurForm" method="post" class="form-grid">
      <div class="full">
        <label>Uursoort *</label>
        <select name="uursoort_id" required>
          <option value="">-- Kies --</option>
          <?php if ($uursoorten): while ($u = $uursoorten->fetch_assoc()): ?>
            <option value="<?= (int)$u['uursoort_id'] ?>" <?= ((int)$uur['uursoort_id'] === (int)$u['uursoort_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$u['code']) ?> – <?= htmlspecialchars((string)$u['omschrijving']) ?>
            </option>
          <?php endwhile; endif; ?>
        </select>
      </div>

      <div>
        <label>Datum *</label>
        <input type="date" name="datum" required value="<?= htmlspecialchars((string)($uur['datum'] ?? '')) ?>">
      </div>

      <div></div>

      <div>
        <label>Starttijd *</label>
        <input type="time" name="begintijd" required value="<?= htmlspecialchars(substr((string)($uur['begintijd'] ?? ''), 0, 5)) ?>">
      </div>

      <div>
        <label>Eindtijd *</label>
        <input type="time" name="eindtijd" required value="<?= htmlspecialchars(substr((string)($uur['eindtijd'] ?? ''), 0, 5)) ?>">
      </div>

      <div class="full">
        <label>Beschrijving / opmerkingen</label>
        <textarea name="opmerkingen" placeholder="Optioneel"><?= htmlspecialchars((string)($uur['opmerkingen'] ?? '')) ?></textarea>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
