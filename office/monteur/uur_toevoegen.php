<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']); // alleen monteurs

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Ongeldige werkbon.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

/* ✓ Werkbon ophalen om datum + info te tonen + ownership */
$stmt = $conn->prepare("
    SELECT werkbonnummer, uitvoerdatum, klant_id
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
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

/* ✅ Uursoorten ophalen (WERKBON → uit uursoorten) */
$uursoorten = $conn->query("
    SELECT uursoort_id, code, omschrijving
    FROM uursoorten
    ORDER BY code ASC
");
if (!$uursoorten) {
    setFlash("Fout bij ophalen uursoorten: " . $conn->error, "error");
}

/* ===============================================================
   VERWERKEN FORMULIER
   =============================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uursoort_id  = (int)($_POST['uursoort_id'] ?? 0);
    $starttijd    = (string)($_POST['starttijd'] ?? '');
    $eindtijd     = (string)($_POST['eindtijd'] ?? '');
    $beschrijving = trim((string)($_POST['beschrijving'] ?? ''));

    // validatie
    if ($uursoort_id <= 0 || $starttijd === '' || $eindtijd === '') {
        setFlash("Vul alle verplichte velden in.", "error");
    } else {

        $s = strtotime($starttijd);
        $e = strtotime($eindtijd);

        if ($s === false || $e === false) {
            setFlash("Ongeldige start- of eindtijd.", "error");
        } elseif ($e <= $s) {
            setFlash("Eindtijd moet later zijn dan starttijd.", "error");
        } else {

            /* ✅ Opslaan in werkbon_uren */
            $stmt = $conn->prepare("
                INSERT INTO werkbon_uren
                (werkbon_id, monteur_id, uursoort_id, datum, begintijd, eindtijd, opmerkingen, goedgekeurd)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'in_behandeling')
            ");
            $stmt->bind_param(
                "iiissss",
                $werkbon_id,
                $monteur_id,
                $uursoort_id,
                $wb['uitvoerdatum'],
                $starttijd,
                $eindtijd,
                $beschrijving
            );

            if ($stmt->execute()) {
                $stmt->close();
                setFlash("Uur succesvol geregistreerd.", "success");
                header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id . "#uren");
                exit;
            } else {
                setFlash("Fout bij opslaan: " . $stmt->error, "error");
            }
            $stmt->close();
        }
    }
}

/* ===============================================================
   PAGINA OUTPUT
   =============================================================== */
$pageTitle = "Uur toevoegen";
ob_start();
?>

<div class="page-section">

<div class="header-actions" style="
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:15px;
">
    <!-- spacer -->
    <div style="flex:1"></div>

    <!-- Annuleren -->
    <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>#uren"
       class="btn btn-secondary">
        ⬅ Annuleren
    </a>

    <!-- Opslaan -->
    <button type="submit"
            form="uurForm"
            class="btn btn-primary">
        💾 Opslaan
    </button>
</div>

  <h2 class="section-title">Uur toevoegen</h2>

  <div class="card">
    <p>
      <strong>Werkbon:</strong> <?= htmlspecialchars((string)$wb['werkbonnummer']) ?><br>
      <strong>Datum:</strong> <?= date("d-m-Y", strtotime((string)$wb['uitvoerdatum'])) ?>
    </p>

    <form id="uurForm" method="post" class="form-grid">

      <!-- Uursoort -->
      <div class="full">
        <label>Uursoort *</label>
        <select name="uursoort_id" required>
          <option value="">-- Kies --</option>
          <?php if ($uursoorten): ?>
            <?php while ($u = $uursoorten->fetch_assoc()): ?>
              <option value="<?= (int)$u['uursoort_id'] ?>">
                <?= htmlspecialchars((string)$u['code']) ?> – <?= htmlspecialchars((string)$u['omschrijving']) ?>
              </option>
            <?php endwhile; ?>
          <?php endif; ?>
        </select>
      </div>

      <!-- Starttijd + Eindtijd naast elkaar -->
      <div>
        <label>Starttijd *</label>
        <input type="time" name="starttijd" required>
      </div>

      <div>
        <label>Eindtijd *</label>
        <input type="time" name="eindtijd" required>
      </div>

      <!-- Beschrijving -->
      <div class="full">
        <label>Beschrijving</label>
        <textarea name="beschrijving" placeholder="Optioneel"></textarea>
      </div>

      <!-- (Geen full-width knoppen meer onderaan) -->
    </form>
  </div>

</div>


<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
