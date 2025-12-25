<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
if ($monteur_id <= 0) {
    setFlash("Geen geldige monteur sessie.", "error");
    header("Location: /logout.php");
    exit;
}

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

/* -----------------------------
   Datum helpers (weekdagen only)
----------------------------- */
$tz = new DateTimeZone('Europe/Amsterdam');
$today = new DateTimeImmutable('now', $tz);

function isWeekend(DateTimeImmutable $d): bool {
    $n = (int)$d->format('N'); // 1=ma ... 7=zo
    return $n >= 6;
}
function moveWorkday(DateTimeImmutable $d, int $dir): DateTimeImmutable {
    // $dir = -1 of +1
    $x = $d;
    do {
        $x = $x->modify(($dir > 0 ? '+1 day' : '-1 day'));
    } while (isWeekend($x));
    return $x;
}
function nlDay(DateTimeImmutable $d): string {
    $map = [
        'Monday'=>'Maandag','Tuesday'=>'Dinsdag','Wednesday'=>'Woensdag','Thursday'=>'Donderdag',
        'Friday'=>'Vrijdag','Saturday'=>'Zaterdag','Sunday'=>'Zondag'
    ];
    $en = $d->format('l');
    return $map[$en] ?? $en;
}

/* -----------------------------
   Selected date (Y-m-d)
----------------------------- */
$raw = (string)($_GET['d'] ?? '');
if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
    $selected = DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz) ?: $today;
} else {
    $selected = $today;
}

// als weekend -> naar eerstvolgende werkdag
if (isWeekend($selected)) {
    $selected = moveWorkday($selected, +1);
}

$selectedDate = $selected->format('Y-m-d');
$prevDate = moveWorkday($selected, -1)->format('Y-m-d');
$nextDate = moveWorkday($selected, +1)->format('Y-m-d');

$navLabel = nlDay($selected) . ' ' . $selected->format('d-m-Y');

/* -----------------------------
   Telefoonveld in klanten detecteren
   (bij jou: telefoonnummer)
----------------------------- */
$telCol = null;
$tryCols = ['telefoon', 'telefoonnummer', 'tel', 'mobiel', 'gsm', 'phone', 'mobile'];

$colRes = $conn->query("SHOW COLUMNS FROM klanten");
$cols = [];
while ($colRes && ($c = $colRes->fetch_assoc())) {
    $cols[strtolower((string)$c['Field'])] = true;
}
foreach ($tryCols as $c) {
    if (isset($cols[$c])) { $telCol = $c; break; }
}
$telSelect = $telCol ? "k.`{$telCol}`" : "NULL";

/* -----------------------------
   Werkbonnen ophalen
----------------------------- */
$werkbonnen = [];

$sql = "
    SELECT
        w.werkbon_id,
        w.werkbonnummer,
        w.uitvoerdatum,
        w.starttijd,
        w.eindtijd,
        w.status,                 -- planner status
        w.werkzaamheden_status,   -- monteur status (open/bezig/...)
        t.naam AS type_naam,

        k.bedrijfsnaam AS klantnaam,

        -- bellen: werkadres.telefoon -> anders klanten.telefoonveld
        COALESCE(wa.telefoon, {$telSelect}) AS bel_tel,

        -- werkadres (voorkeur voor navigatie)
        wa.adres    AS wa_adres,
        wa.postcode AS wa_postcode,
        wa.plaats   AS wa_plaats,

        -- klantadres fallback
        k.adres     AS klant_adres,
        k.postcode  AS klant_postcode,
        k.plaats    AS klant_plaats

    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    LEFT JOIN type_werkzaamheden t ON w.type_werkzaamheden_id = t.id
    WHERE w.monteur_id = ?
      AND w.uitvoerdatum = ?
    ORDER BY w.starttijd ASC, w.werkbon_id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $monteur_id, $selectedDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $werkbonnen[] = $r;
}
$stmt->close();

/* -----------------------------
   UI helpers
----------------------------- */
function hm(?string $t): string { return $t ? substr($t, 0, 5) : '-'; }

function wsLabel(string $s): string {
    return match ($s) {
        'bezig'      => 'Bezig',
        'onderweg'   => 'Onderweg',
        'op_locatie' => 'Op locatie',
        'gereed'     => 'Gereed',
        default      => 'Open',
    };
}

$pageTitle = "Mijn Planning";
ob_start();
?>

<style>
/* Eén-regel navigatie */
.planning-nav{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  padding:8px 10px;
  border-radius:12px;
  margin:0 auto;
}
.planning-nav .nav-btn{
  all:unset;
  cursor:pointer;
  font-weight:800;
  font-size:16px;
  line-height:1;
  padding:4px 8px;
  border-radius:10px;
}
.planning-nav .nav-btn:hover{ color:#2954cc; }
.planning-nav .nav-label{
  font-weight:800;
  font-size:16px;
  white-space:nowrap;
}

/* Werkbon cards */
.wb-list{ display:flex; flex-direction:column; gap:10px; }
.wb-item{
  background:#f9fafb;
  border:1px solid #e5e7eb;
  border-radius:12px;
  padding:12px 14px;
}
.wb-row{ display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
.wb-title{ font-weight:700; margin-bottom:6px; }
.wb-meta{ display:flex; gap:10px; flex-wrap:wrap; font-size:13px; opacity:.85; }
.ws-badge{
  display:inline-block;
  padding:4px 8px;
  border-radius:8px;
  font-size:12px;
  font-weight:800;
  color:#fff;
  margin-top:8px;
}
.ws-open{background:#64748b}
.ws-bezig{background:#2563eb}
.ws-onderweg{background:#0ea5e9}
.ws-op_locatie{background:#22c55e}
.ws-gereed{background:#16a34a}

/* 3-puntjes knop */
.kebab-btn{
  width:36px;height:36px;
  border:none;border-radius:10px;
  background:#f3f4f6;
  font-size:20px;
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
}
.kebab-btn:hover{ background:#eef2ff; }

/* Floating menu (aan body -> lost overlap definitief op) */
.wb-floatmenu{
  position:fixed;
  min-width:240px;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  box-shadow:0 12px 30px rgba(0,0,0,.16);
  padding:6px;
  z-index:2147483647;
}
.wb-floatmenu a, .wb-floatmenu button, .wb-floatmenu span{
  width:100%;
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 10px;
  border-radius:10px;
  text-decoration:none;
  color:#111;
  font-size:14px;
  background:none;
  border:none;
  text-align:left;
  cursor:pointer;
}
.wb-floatmenu a:hover, .wb-floatmenu button:hover{ background:#eef2ff; }
.wb-floatmenu .disabled{ color:#777; cursor:default; }
.wb-floatmenu .sep{ height:1px; background:#e5e7eb; margin:6px 0; }
.wb-floatmenu .head{ font-weight:800; font-size:12px; opacity:.75; padding:8px 10px; }
.wb-floatmenu .muted{ font-size:12px; opacity:.75; padding:0 10px 8px 10px; }
</style>

<div class="card">
  <div class="planning-nav">
    <a class="nav-btn" href="/monteur/mijn_planning.php?d=<?= e($prevDate) ?>" aria-label="Vorige dag">‹</a>
    <div class="nav-label"><?= e($navLabel) ?></div>
    <a class="nav-btn" href="/monteur/mijn_planning.php?d=<?= e($nextDate) ?>" aria-label="Volgende dag">›</a>
  </div>
</div>

<div class="card">
  <h3>Werkbonnen</h3>

  <?php if (empty($werkbonnen)): ?>
    <p class="empty-msg">Geen werkbonnen voor deze dag.</p>
  <?php else: ?>
    <div class="wb-list">
      <?php foreach ($werkbonnen as $w): ?>
        <?php
          $id = (int)$w['werkbon_id'];
          $titel = trim(($w['werkbonnummer'] ?? '') . ' • ' . ($w['klantnaam'] ?? ''));
          $tijd = hm($w['starttijd'] ?? null) . ' - ' . hm($w['eindtijd'] ?? null);

          $plannerStatus = (string)($w['status'] ?? '');
          $ws = (string)($w['werkzaamheden_status'] ?? 'open');

          // bellen
          $telRaw = (string)($w['bel_tel'] ?? '');
          $tel = preg_replace('/[^0-9+]/', '', $telRaw);

          // navigatie: werkadres voorkeur, anders klantadres
          $route = trim(
              (string)($w['wa_adres'] ?? '') . ' ' . (string)($w['wa_postcode'] ?? '') . ' ' . (string)($w['wa_plaats'] ?? '')
          );
          if ($route === '') {
              $route = trim(
                  (string)($w['klant_adres'] ?? '') . ' ' . (string)($w['klant_postcode'] ?? '') . ' ' . (string)($w['klant_plaats'] ?? '')
              );
          }

          // data attributes voor JS menu
          $menuData = [
              'id' => $id,
              'tel' => $tel,
              'route' => $route,
              'planner' => $plannerStatus,
              'ws' => $ws,
          ];
        ?>

        <div class="wb-item">
          <div class="wb-row">
            <div>
              <div class="wb-title"><?= e($titel) ?></div>
              <div class="wb-meta">
                <span>🕒 <?= e($tijd) ?></span>
                <?php if (!empty($w['type_naam'])): ?>
                  <span>🏷️ <?= e((string)$w['type_naam']) ?></span>
                <?php endif; ?>
                <?php if (!empty($plannerStatus)): ?>
                  <span>📌 <?= e($plannerStatus) ?></span>
                <?php endif; ?>
              </div>

              <span class="ws-badge ws-<?= e($ws) ?>" data-ws-badge="<?= $id ?>">
                <?= e(wsLabel($ws)) ?>
              </span>
            </div>

            <button
              class="kebab-btn"
              type="button"
              aria-label="Acties"
              data-kebab='<?= e(json_encode($menuData, JSON_UNESCAPED_UNICODE)) ?>'
            >⋮</button>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
  let menuEl = null;

  const closeMenu = () => {
    if (menuEl) { menuEl.remove(); menuEl = null; }
  };

  const wsLabel = (s) => {
    switch(String(s||'open')) {
      case 'bezig': return 'Bezig';
      case 'onderweg': return 'Onderweg';
      case 'op_locatie': return 'Op locatie';
      case 'gereed': return 'Gereed';
      default: return 'Open';
    }
  };

  const wsClass = (s) => 'ws-badge ws-' + (String(s||'open'));

  const setStatus = async (werkbonId, status) => {
    const resp = await fetch('/monteur/ajax_update_status.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ werkbon_id: String(werkbonId), status: String(status) }),
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const data = await resp.json().catch(() => null);
    if (!resp.ok || !data?.ok) throw new Error(data?.msg || ('HTTP ' + resp.status));
    return data.status;
  };

  const buildMenu = (data) => {
    const id = Number(data.id || 0);
    const tel = String(data.tel || '');
    const route = String(data.route || '');
    const planner = String(data.planner || '');
    const currentWs = String(data.ws || 'open');

    const canCall = !!tel;
    const canRoute = !!route;

    const googleUrl = canRoute
      ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(route)
      : '';

    const opts = [
      ['open','Open'],
      ['onderweg','Onderweg'],
      ['op_locatie','Op locatie'],
      ['bezig','Bezig'],
      ['gereed','Gereed']
    ];

    const wrap = document.createElement('div');
    wrap.className = 'wb-floatmenu';
    wrap.setAttribute('data-wbid', String(id));

    wrap.innerHTML = `
      <a href="/monteur/werkbon_view.php?id=${id}">📄 Openen</a>
      <a href="/monteur/werkbon_view.php?id=${id}#werkbongegevens">📝 Werkomschrijving</a>
      <a href="/monteur/werkbon_objecten.php?werkbon_id=${id}">🧰 Objecten</a>

      ${canCall
        ? `<a href="tel:${tel}">📞 Bellen</a>`
        : `<span class="disabled">📞 Bellen (geen nummer)</span>`}

      ${canRoute
        ? `<a href="${googleUrl}" target="_blank" rel="noopener">📍 Navigeren</a>`
        : `<span class="disabled">📍 Navigeren (geen adres)</span>`}

      <div class="sep"></div>
      <div class="head">Status (planner)</div>
      <div class="muted">${planner ? planner : '-'}</div>

      <div class="sep"></div>
      <div class="head">Status (monteur)</div>
      ${opts.map(([k,l]) => `
        <button type="button" data-set-ws="${k}" ${k===currentWs?'data-active="1"':''}>${l}</button>
      `).join('')}
    `;

    // click handler menu
    wrap.addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-set-ws]');
      if (!btn) return;

      const newStatus = btn.getAttribute('data-set-ws');
      try{
        await setStatus(id, newStatus);

        // update badge
        const badge = document.querySelector(`[data-ws-badge="${id}"]`);
        if (badge) {
          badge.className = wsClass(newStatus);
          badge.textContent = wsLabel(newStatus);
        }

        // update menu state
        wrap.querySelectorAll('button[data-set-ws]').forEach(b => b.removeAttribute('data-active'));
        btn.setAttribute('data-active','1');

        closeMenu();
      }catch(err){
        alert('Status wijzigen mislukt: ' + (err?.message || 'Onbekend'));
        closeMenu();
      }
    });

    return wrap;
  };

  const positionMenu = (menu, btn) => {
    const r = btn.getBoundingClientRect();
    const margin = 8;

    // default: onder de knop rechts uitlijnen
    let top = r.bottom + margin;
    let left = r.right - menu.offsetWidth;

    // binnen viewport houden
    if (left < margin) left = margin;
    if (top + menu.offsetHeight > window.innerHeight - margin) {
      top = r.top - menu.offsetHeight - margin; // probeer boven de knop
    }
    if (top < margin) top = margin;

    menu.style.top = top + 'px';
    menu.style.left = left + 'px';
  };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('button.kebab-btn[data-kebab]');
    const insideMenu = e.target.closest('.wb-floatmenu');

    if (insideMenu) return;

    if (!btn) {
      closeMenu();
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    closeMenu();

    let data = null;
    try { data = JSON.parse(btn.getAttribute('data-kebab') || '{}'); } catch(_){ data = {}; }

    menuEl = buildMenu(data);
    document.body.appendChild(menuEl);

    // eerst meten, dan positioneren
    positionMenu(menuEl, btn);
  });

  window.addEventListener('resize', closeMenu);
  window.addEventListener('scroll', closeMenu, { passive: true });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
})();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/template/monteur_template.php';
