<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);

// accepteer beide parameter-namen:
$werkbon_id = (int)($_GET['werkbon_id'] ?? ($_GET['id'] ?? 0));

if ($monteur_id <= 0 || $werkbon_id <= 0) {
    setFlash("Geen geldige werkbon opgegeven.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

// Werkbon ophalen + ownership check
$stmt = $conn->prepare("
    SELECT werkbon_id, werkbonnummer, monteur_id, handtekening_klant
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/mijn_planning.php");
    exit;
}

$pageTitle = "Handtekening – Werkbon " . e($wb['werkbonnummer'] ?? (string)$werkbon_id);

$existing = (string)($wb['handtekening_klant'] ?? '');
$existingUrl = '';
if ($existing !== '') {
    // DB kan "uploads/..." of "/uploads/..." bevatten → normaliseren
    $existingUrl = ($existing[0] === '/') ? $existing : '/' . $existing;
}

ob_start();
?>

<div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
    <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>" class="btn btn-secondary">⬅ Terug naar werkbon</a>
</div>

<div class="card">
    <h3>Handtekening klant</h3>

    <?php if ($existingUrl): ?>
        <p style="margin:0 0 10px;"><strong>Huidige handtekening:</strong></p>
        <img src="<?= e($existingUrl) ?>" alt="Handtekening"
             style="max-width:320px; border:1px solid #d1d5db; padding:8px; background:#fff; border-radius:10px;">
        <hr style="margin:15px 0;">
    <?php else: ?>
        <p style="opacity:.75; margin-top:0;">Nog geen handtekening opgeslagen.</p>
    <?php endif; ?>

    <p style="margin:0 0 10px;"><strong>Nieuwe handtekening:</strong></p>

    <div style="max-width:360px;">
        <canvas id="sigCanvas" width="340" height="160"
                style="width:100%; border:1px solid #111; background:#fff; border-radius:10px;"></canvas>

        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
            <button type="button" class="btn btn-secondary" id="btnClear">Wissen</button>
            <button type="button" class="btn" id="btnSave">💾 Opslaan</button>
        </div>

        <div id="saveMsg" style="margin-top:10px; font-size:13px; opacity:.8;"></div>
    </div>
</div>

<script>
(function(){
  const canvas = document.getElementById('sigCanvas');
  const ctx = canvas.getContext('2d');
  const btnClear = document.getElementById('btnClear');
  const btnSave = document.getElementById('btnSave');
  const msg = document.getElementById('saveMsg');

  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#000';

  let drawing = false;
  let lastX = 0, lastY = 0;

  function getPos(e){
    const r = canvas.getBoundingClientRect();
    if (e.touches && e.touches[0]) {
      return {
        x: e.touches[0].clientX - r.left,
        y: e.touches[0].clientY - r.top
      };
    }
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  }

  function start(e){
    drawing = true;
    const p = getPos(e);
    lastX = p.x; lastY = p.y;
  }

  function move(e){
    if (!drawing) return;
    e.preventDefault();
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastX = p.x; lastY = p.y;
  }

  function end(){
    drawing = false;
  }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);

  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  canvas.addEventListener('touchend', end);

  btnClear.addEventListener('click', () => {
    ctx.clearRect(0,0,canvas.width,canvas.height);
    msg.textContent = '';
  });

  btnSave.addEventListener('click', async () => {
    msg.textContent = 'Opslaan...';
    btnSave.disabled = true;

    try {
      const dataUrl = canvas.toDataURL('image/png');

      const res = await fetch('/monteur/save_signature.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          werkbon_id: <?= (int)$werkbon_id ?>,
          image: dataUrl
        })
      });

      const data = await res.json();
      if (!data || !data.ok) {
        msg.textContent = 'Fout: ' + (data?.msg || 'onbekend');
        btnSave.disabled = false;
        return;
      }

      msg.textContent = 'Opgeslagen ✅ (pagina wordt vernieuwd)';
      setTimeout(() => location.reload(), 400);

    } catch (e) {
      msg.textContent = 'Verbinding mislukt.';
      btnSave.disabled = false;
    }
  });
})();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
