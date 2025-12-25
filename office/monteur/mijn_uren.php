<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$pageTitle = "Mijn uren (bijzondere uren)";

/* ---------------------------------------------------------
   Uursoorten (speciale tabel)
---------------------------------------------------------- */
$uursoorten = $conn->query("
    SELECT uursoort_id, code, omschrijving
    FROM uursoorten_uren
    ORDER BY code ASC
");

/* ---------------------------------------------------------
   Verwerken POST (uren toevoegen)
---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $datum = $_POST['datum'] ?? date("Y-m-d");
    $start = $_POST['starttijd'] ?? "";
    $eind  = $_POST['eindtijd'] ?? "";
    $uursoort_id = intval($_POST['uursoort_id'] ?? 0);
    $opm = trim($_POST['beschrijving'] ?? "");

    if (!$datum || !$start || !$eind || !$uursoort_id) {
        setFlash("Vul alle verplichte velden in.", "error");
    } else {

        $s = strtotime($start);
        $e = strtotime($eind);

        if ($e <= $s) {
            setFlash("Eindtijd moet later zijn dan starttijd.", "error");
        } else {

            $duur_minuten = ($e - $s) / 60;

            $stmt = $conn->prepare("
                INSERT INTO urenregistratie
                (user_id, werkbon_id, datum, starttijd, eindtijd, uursoort_id, beschrijving, duur_minuten)
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isssisi",
                $monteur_id,
                $datum,
                $start,
                $eind,
                $uursoort_id,
                $opm,
                $duur_minuten
            );

            if ($stmt->execute()) {
                setFlash("Uur geregistreerd.", "success");
                header("Location: /monteur/mijn_uren.php");
                exit;
            } else {
                setFlash("Fout bij opslaan: " . $stmt->error, "error");
            }
        }
    }
}

/* ---------------------------------------------------------
   Uren lijst ophalen
---------------------------------------------------------- */
$uren = $conn->prepare("
    SELECT u.*, s.code, s.omschrijving AS soort
    FROM urenregistratie u
    LEFT JOIN uursoorten_uren s ON s.uursoort_id = u.uursoort_id
    WHERE u.user_id = ?
    ORDER BY u.datum DESC, u.starttijd DESC
");
$uren->bind_param("i", $monteur_id);
$uren->execute();
$res = $uren->get_result();

ob_start();
?>

<div class="page-section">
    <h2 class="section-title">Mijn uren</h2>

    <div class="card">
        <form method="post" class="form-grid">

            <div>
                <label>Datum *</label>
                <input type="date" name="datum" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div>
                <label>Starttijd *</label>
                <input type="time" name="starttijd" required>
            </div>

            <div>
                <label>Eindtijd *</label>
                <input type="time" name="eindtijd" required>
            </div>

            <div>
                <label>Uursoort *</label>
                <select name="uursoort_id" required>
                    <option value="">-- Kies --</option>
                    <?php while ($u = $uursoorten->fetch_assoc()): ?>
                        <option value="<?= $u['uursoort_id'] ?>">
                            <?= $u['code'] ?> – <?= htmlspecialchars($u['omschrijving']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="full">
                <label>Opmerking</label>
                <textarea name="beschrijving" placeholder="Optioneel"></textarea>
            </div>

            <div class="full">
                <button class="btn-primary btn-block">
                    <i class="fa-solid fa-save"></i> Opslaan
                </button>
            </div>

        </form>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Mijn geregistreerde uren</h3>

        <?php if ($res->num_rows == 0): ?>
            <p><em>Geen uren geregistreerd.</em></p>
        <?php else: ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Tijd</th>
                    <th>Uursoort</th>
                    <th>Beschrijving</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['datum']) ?></td>
                    <td><?= substr($r['starttijd'],0,5) ?> - <?= substr($r['eindtijd'],0,5) ?></td>
                    <td><?= htmlspecialchars($r['code']) ?> – <?= htmlspecialchars($r['soort']) ?></td>
                    <td><?= nl2br(htmlspecialchars($r['beschrijving'])) ?></td>
                    <td>
                        <?php if ($r['goedgekeurd'] == 1): ?>
                            <span class="badge badge-success">Goedgekeurd</span>
                        <?php elseif ($r['goedgekeurd'] == -1): ?>
                            <span class="badge badge-danger">Afgekeurd</span>
                        <?php else: ?>
                            <span class="badge badge-warning">In behandeling</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>

</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
