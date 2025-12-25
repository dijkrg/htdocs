<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$monteur_naam = htmlspecialchars($_SESSION['user']['voornaam'] . " " . $_SESSION['user']['achternaam']);

$pageTitle = "Mijn Planning";

// Welke dag?
$offset = intval($_GET['d'] ?? 0);
$datum = date("Y-m-d", strtotime("$offset day"));
$datumLabel = strftime("%A %e %B %Y", strtotime($datum));

// Werkbonnen ophalen
$stmt = $conn->prepare("
    SELECT 
        w.werkbon_id,
        w.werkbonnummer,
        w.starttijd,
        w.eindtijd,
        w.monteur_status,
        k.bedrijfsnaam,
        CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats) AS werkadres
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    WHERE w.monteur_id = ?
      AND w.uitvoerdatum = ?
      AND w.gearchiveerd = 0
    ORDER BY w.starttijd ASC
");
$stmt->bind_param("is", $monteur_id, $datum);
$stmt->execute();
$res = $stmt->get_result();

ob_start();
?>

<h2 class="page-title">📅 Mijn Planning</h2>

<!-- Navigatieblokken -->
<div class="planning-nav">
    <a href="mijn_planning.php?d=<?= $offset-1 ?>" class="nav-btn">⬅ Vorige dag</a>
    <div class="nav-date"><?= ucfirst($datumLabel) ?></div>
    <a href="mijn_planning.php?d=<?= $offset+1 ?>" class="nav-btn">Volgende dag ➡</a>
</div>

<!-- Werkbonnenlijst -->
<div class="planning-list">
<?php if ($res->num_rows === 0): ?>
    <p class="no-wb">Geen werkbonnen gepland voor deze dag.</p>
<?php else: ?>
    <?php while ($wb = $res->fetch_assoc()): ?>
        <div class="wb-card">
            <div class="wb-header">
                <div>
                    <strong><?= htmlspecialchars($wb['werkbonnummer']) ?></strong><br>
                    <?= htmlspecialchars($wb['bedrijfsnaam']) ?><br>
                    <small><?= htmlspecialchars($wb['werkadres']) ?></small>
                </div>
                <div class="wb-time">
                    <?= substr($wb['starttijd'],0,5) ?> – <?= substr($wb['eindtijd'],0,5) ?>
                </div>
            </div>

            <div class="wb-status">
                <span class="status-label">Status: 
                    <?= $wb['monteur_status'] ?: 'Nog niet gestart' ?>
                </span>
            </div>

            <!-- Statusknoppen -->
            <div class="wb-buttons">
                <button onclick="updateStatus(<?= $wb['werkbon_id'] ?>,'onderweg')" class="btn-onderweg">Onderweg</button>
                <button onclick="updateStatus(<?= $wb['werkbon_id'] ?>,'op_locatie')" class="btn-oplocatie">Op locatie</button>
                <button onclick="updateStatus(<?= $wb['werkbon_id'] ?>,'gereed')" class="btn-gereed">Gereed</button>
            </div>

            <a href="/monteur/werkbon_view.php?id=<?= $wb['werkbon_id'] ?>" class="wb-open">➜ Bekijk werkbon</a>
        </div>
    <?php endwhile; ?>
<?php endif; ?>
</div>

<script>
function updateStatus(id, status){
    fetch("/monteur/api/update_monteur_status.php", {
        method:"POST",
        headers:{ "Content-Type":"application/x-www-form-urlencoded" },
        body:"id="+id+"&status="+status
    })
    .then(r => r.json())
    .then(data => {
        if(data.ok){
            location.reload();
        } else {
            alert("Fout: "+data.msg);
        }
    })
    .catch(()=> alert("Netwerkfout"));
}
</script>

<style>
.planning-nav {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 0;
}
.nav-btn {
    background:#2954cc;
    color:white;
    padding:8px 14px;
    border-radius:8px;
    text-decoration:none;
    font-size:14px;
}
.nav-date {
    font-weight:bold;
    font-size:16px;
}
.planning-list {
    margin-top:15px;
}
.wb-card {
    background:white;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}
.wb-header {
    display:flex;
    justify-content:space-between;
}
.wb-time {
    font-weight:bold;
}
.wb-buttons {
    margin-top:10px;
    display:flex;
    gap:10px;
}
.btn-onderweg { background:#0ea5e9; color:white; border:none; padding:10px; border-radius:8px; flex:1; }
.btn-oplocatie { background:#22c55e; color:white; border:none; padding:10px; border-radius:8px; flex:1; }
.btn-gereed { background:#7c3aed; color:white; border:none; padding:10px; border-radius:8px; flex:1; }

.wb-open {
    display:block;
    margin-top:10px;
    text-align:right;
    font-size:14px;
    text-decoration:none;
    color:#2954cc;
    font-weight:bold;
}
.no-wb {
    padding:20px;
    text-align:center;
    color:#777;
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
