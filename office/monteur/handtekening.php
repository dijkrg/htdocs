<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    setFlash("Geen werkbon ID.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Controleer of werkbon aan monteur hoort
$stmt = $conn->prepare("SELECT werkbonnummer, omschrijving FROM werkbonnen WHERE werkbon_id=? AND monteur_id=?");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();

if (!$wb) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

$pageTitle = "Handtekening";
ob_start();
?>

<a href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>" class="back-btn">⬅ Terug</a>

<h2 class="page-title">Klant handtekening</h2>

<div class="card">

    <p><strong>Werkbon:</strong> #<?= htmlspecialchars($wb['werkbonnummer']) ?></p>
    <p><strong>Omschrijving:</strong> <?= htmlspecialchars($wb['omschrijving']) ?></p>

    <canvas id="signCanvas" class="signature-pad"></canvas>

    <div class="sig-buttons">
        <button class="btn-secondary" type="button" onclick="clearCanvas()">
            <i class="fa-solid fa-eraser"></i> Wissen
        </button>

        <button class="btn-primary" type="button" onclick="saveSignature()">
            <i class="fa-solid fa-check"></i> Opslaan
        </button>
    </div>

</div>

<style>
.signature-pad {
    width: 100%;
    height: 240px;
    border: 2px dashed #999;
    border-radius: 12px;
    background: #fff;
    touch-action: none;
}

.sig-buttons {
    display:flex;
    justify-content: space-between;
    margin-top:15px;
}

.card p {
    margin: 4px 0;
}
</style>

<script>
let canvas = document.getElementById("signCanvas");
let ctx = canvas.getContext("2d");
let drawing = false;

function resizeCanvas() {
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
}
resizeCanvas();

// Touch / Mouse events
function start(e) {
    drawing = true;
    ctx.moveTo(
        (e.touches ? e.touches[0].clientX : e.clientX) - canvas.getBoundingClientRect().left,
        (e.touches ? e.touches[0].clientY : e.clientY) - canvas.getBoundingClientRect().top
    );
}
function draw(e) {
    if (!drawing) return;
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#000";

    ctx.lineTo(
        (e.touches ? e.touches[0].clientX : e.clientX) - canvas.getBoundingClientRect().left,
        (e.touches ? e.touches[0].clientY : e.clientY) - canvas.getBoundingClientRect().top
    );
    ctx.stroke();
}
function stop() { drawing = false; }

canvas.addEventListener("mousedown", start);
canvas.addEventListener("mousemove", draw);
canvas.addEventListener("mouseup", stop);

canvas.addEventListener("touchstart", start);
canvas.addEventListener("touchmove", draw);
canvas.addEventListener("touchend", stop);

function clearCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function saveSignature() {
    let dataURL = canvas.toDataURL("image/png");

    fetch("/monteur/api/save_signature.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            werkbon_id: <?= $werkbon_id ?>,
            signature: dataURL
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            window.location.href = "/monteur/werkbon_view.php?id=<?= $werkbon_id ?>";
        } else {
            alert(res.msg);
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
