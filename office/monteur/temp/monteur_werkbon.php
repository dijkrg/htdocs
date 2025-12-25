<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$rol = $_SESSION['user']['rol'] ?? '';
if ($rol !== 'Monteur') {
    setFlash("Geen toegang.", "error");
    header("Location: /index.php");
    exit;
}

$werkbon_id = intval($_GET['id'] ?? 0);
$monteur_id = $_SESSION['user']['id'];

if ($werkbon_id === 0) {
    setFlash("Geen werkbon ID opgegeven.", "error");
    header("Location: monteur_werkbon.php");
    exit;
}

// Controle eigendom
$stmt = $conn->prepare("SELECT * FROM werkbonnen WHERE werkbon_id = ? AND monteur_id = ?");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$werkbon = $stmt->get_result()->fetch_assoc();

if (!$werkbon) {
    setFlash("Deze werkbon is niet aan jou toegewezen.", "error");
    header("Location: monteur_werkbon.php");
    exit;
}

// Functie voor datum
function formatDateNL($date) {
    if (!$date || $date === '0000-00-00') return '-';
    return date("d-m-Y", strtotime($date));
}

ob_start();
?>
<h2>Werkbon #<?= htmlspecialchars($werkbon['werkbonnummer']) ?></h2>
<?php showFlash(); ?>

<a href="monteur_werkbon.php" class="btn btn-secondary" style="margin-bottom:15px;">⬅ Terug</a>

<div class="card mobile-card">
    <h3>Klant</h3>
    <p><strong><?= htmlspecialchars($werkbon['omschrijving']) ?></strong></p>
    <p><?= formatDateNL($werkbon['uitvoerdatum']) ?></p>
</div>

<!-- STATUS KNOPPEN -->
<div class="status-buttons">
    <button class="status-btn" data-status="onderweg">Onderweg</button>
    <button class="status-btn" data-status="op_locatie">Op locatie</button>
    <button class="status-btn" data-status="gereed">Gereed</button>
</div>

<div id="statusFeedback" style="margin-top:10px; font-weight:bold;"></div>

<!-- Werk gereed toggle -->
<div class="card mobile-card">
    <h3>Werk gereed</h3>
    <label class="toggle-switch">
        <input type="checkbox" id="werkGereed" <?= $werkbon['werk_gereed'] ? 'checked' : '' ?>>
        <span class="toggle-slider"></span>
    </label>
</div>

<!-- OBJECTEN -->
<div class="card mobile-card">
    <h3>Objecten</h3>
    <a class="btn" href="werkbon_object_toevoegen.php?werkbon_id=<?= $werkbon_id ?>">+ Object toevoegen</a>

    <?php
    $obj = $conn->query("
        SELECT o.object_id, o.code, o.omschrijving
        FROM werkbon_objecten wo
        JOIN objecten o ON wo.object_id = o.object_id
        WHERE wo.werkbon_id = $werkbon_id
        ORDER BY o.code
    ");
    if ($obj->num_rows == 0): ?>
        <p style="color:#777;">Geen objecten gekoppeld</p>
    <?php else: ?>
        <ul class="mobile-list">
        <?php while ($o = $obj->fetch_assoc()): ?>
            <li>
                <strong><?= htmlspecialchars($o['code']) ?></strong><br>
                <?= htmlspecialchars($o['omschrijving']) ?>
                <a class="btn btn-danger btn-small" 
                   href="werkbon_object_verwijder.php?werkbon_id=<?= $werkbon_id ?>&object_id=<?= $o['object_id'] ?>">🗑</a>
            </li>
        <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- ARTIKELEN -->
<div class="card mobile-card">
    <h3>Artikelen</h3>
    <a class="btn" href="werkbon_artikel_toevoegen.php?werkbon_id=<?= $werkbon_id ?>">+ Artikel toevoegen</a>

    <?php
    $art = $conn->query("
        SELECT wa.id, wa.aantal, a.omschrijving 
        FROM werkbon_artikelen wa
        LEFT JOIN artikelen a ON a.artikel_id = wa.artikel_id
        WHERE wa.werkbon_id = $werkbon_id
    ");
    if ($art->num_rows == 0): ?>
        <p style="color:#777;">Geen artikelen toegevoegd</p>
    <?php else: ?>
        <ul class="mobile-list">
        <?php while ($a = $art->fetch_assoc()): ?>
            <li>
                <strong><?= $a['aantal'] ?>×</strong> — <?= htmlspecialchars($a['omschrijving']) ?>
                <a class="btn btn-danger btn-small"
                   href="werkbon_artikel_verwijder.php?id=<?= $a['id'] ?>&werkbon_id=<?= $werkbon_id ?>">🗑</a>
            </li>
        <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- UREN -->
<div class="card mobile-card">
    <h3>Uren</h3>
    <a class="btn" href="uren_toevoegen.php?werkbon_id=<?= $werkbon_id ?>">+ Uur toevoegen</a>

    <?php
    $ur = $conn->query("
        SELECT * FROM werkbon_uren
        WHERE werkbon_id = $werkbon_id
        ORDER BY datum, begintijd
    ");
    if ($ur->num_rows == 0): ?>
        <p style="color:#777;">Geen uren toegevoegd</p>
    <?php else: ?>
        <ul class="mobile-list">
        <?php while ($u = $ur->fetch_assoc()): ?>
            <li>
                <?= formatDateNL($u['datum']) ?> —
                <?= substr($u['begintijd'],0,5) ?> → <?= substr($u['eindtijd'],0,5) ?>
                <a class="btn btn-danger btn-small"
                   href="uren_verwijder.php?id=<?= $u['werkbon_uur_id'] ?>&werkbon_id=<?= $werkbon_id ?>">🗑</a>
            </li>
        <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>

<style>
.mobile-card { margin-bottom:20px; padding:15px; }

.status-buttons { 
    display:flex;
    gap:10px;
    justify-content:space-between;
    margin-bottom:15px;
}

.status-btn {
    flex:1;
    padding:12px;
    font-size:16px;
    border:none;
    border-radius:8px;
    background:#2954cc;
    color:white;
}

.status-btn:active {
    background:#1e3a8a;
}

.mobile-list {
    list-style:none;
    padding-left:0;
}

.mobile-list li {
    padding:10px 0;
    border-bottom:1px solid #ddd;
}

.btn-small { padding:3px 6px; font-size:13px; }
</style>

<script>
// STATUS UPDATE (AJAX)
document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const status = btn.dataset.status;

        fetch('monteur_status_update.php', {
            method: 'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ id: <?= $werkbon_id ?>, status })
        })
        .then(r => r.json())
        .then(data => {
            if(data.ok){
                document.getElementById('statusFeedback').textContent = 
                    'Status bijgewerkt naar: ' + status.replace('_',' ');
            } else {
                alert(data.msg);
            }
        });
    });
});

// WERK GEREED
document.getElementById('werkGereed').addEventListener('change', function() {
    fetch('/werkbon_toggle_gereed.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            id: <?= $werkbon_id ?>,
            gereed: this.checked ? 1 : 0
        })
    });
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Werkbon Detail";
require_once __DIR__ . '/../template/monteur_template.php';
?>
