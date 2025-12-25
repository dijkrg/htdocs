<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)($_SESSION['user']['id'] ?? 0);
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);
$object_id  = (int)($_GET['object_id'] ?? 0);

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function dateNL(?string $date): string {
    $date = (string)$date;
    if ($date === '' || $date === '0000-00-00') return '-';
    $ts = strtotime($date);
    if (!$ts) return e($date);
    return date('d-m-Y', $ts);
}

if ($monteur_id <= 0 || $werkbon_id <= 0 || $object_id <= 0) {
    http_response_code(400);
    echo "Ongeldige parameters.";
    exit;
}

/* --------------------------------------------------
   1) Werkbon check + ownership + klant_id + werkadres_id
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT werkbon_id, klant_id, werkadres_id, monteur_id, werkbonnummer
    FROM werkbonnen
    WHERE werkbon_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $werkbon_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wb || (int)$wb['monteur_id'] !== $monteur_id) {
    http_response_code(403);
    echo "Geen toegang tot deze werkbon.";
    exit;
}

$klant_id    = (int)($wb['klant_id'] ?? 0);
$wbWerkadres = (int)($wb['werkadres_id'] ?? 0);

/* --------------------------------------------------
   2) Object ophalen (incl werkadres_id)
-------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT
        o.object_id, o.klant_id, o.werkadres_id, o.code, o.omschrijving,
        o.merk, o.type,
        o.datum_installatie,
        o.datum_onderhoud,
        o.fabricagejaar,
        o.beproeving_nen671_3,
        o.rijkstypekeur,
        o.locatie, o.verdieping,
        o.uitgebreid_onderhoud, o.gereviseerd,
        o.resultaat, o.opmerkingen,
        s.naam AS status_naam
    FROM objecten o
    LEFT JOIN object_status s ON o.status_id = s.status_id
    WHERE o.object_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $object_id);
$stmt->execute();
$object = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$object) {
    http_response_code(404);
    echo "Object niet gevonden.";
    exit;
}

/* --------------------------------------------------
   3) Toegang volgens NIEUWE regels (geen auto-koppeling)
   - Werkbon heeft werkadres => object moet op dat werkadres zitten
   - Geen werkadres => object moet klant-object zijn ZONDER werkadres
-------------------------------------------------- */
$objKlant     = (int)($object['klant_id'] ?? 0);
$objWerkadres = (int)($object['werkadres_id'] ?? 0);

$allowed = false;
if ($wbWerkadres > 0) {
    $allowed = ($objWerkadres === $wbWerkadres);
} else {
    $allowed = ($objKlant === $klant_id) && ($objWerkadres === 0);
}

if (!$allowed) {
    http_response_code(403);
    echo "Geen toegang tot dit object (niet binnen klant/werkadres van deze werkbon).";
    exit;
}

/* --------------------------------------------------
   4) Werkbonwijzigingen (laatste 10) -> object_inspecties (optioneel)
-------------------------------------------------- */
$wijzigingen = [];
$hasInspecties = false;
$chk = $conn->query("SHOW TABLES LIKE 'object_inspecties'");
if ($chk && $chk->num_rows > 0) $hasInspecties = true;

if ($hasInspecties) {
    $stmt = $conn->prepare("
        SELECT oi.datum, oi.tijd, oi.resultaat, oi.opmerking,
               CONCAT(m.voornaam, ' ', m.achternaam) AS door
        FROM object_inspecties oi
        LEFT JOIN medewerkers m ON oi.user_id = m.medewerker_id
        WHERE oi.object_id = ? AND oi.werkbon_id = ?
        ORDER BY oi.datum DESC, oi.tijd DESC
        LIMIT 10
    ");
    $stmt->bind_param("ii", $object_id, $werkbon_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $wijzigingen[] = $r;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Object details</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2954cc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" href="/icons/icon-96.png">

    <link rel="stylesheet" href="/template/style.css">
    <link rel="stylesheet" href="/monteur/template/monteur_base.css">
    <link rel="stylesheet" href="/monteur/template/monteur_mobile.css">
    <link rel="stylesheet" href="/monteur/template/monteur.css?v=1">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/monteur/service-worker.js", { scope: "/monteur/" })
            .catch(err => console.error("SW fout:", err));
    }
    </script>

    <style>
        .sidebar{width:70px!important;overflow:hidden!important;background:#1f2937!important;border-right:1px solid #222;position:fixed;top:60px;bottom:0;left:0;padding-top:10px;z-index:1000}
        .sidebar span{display:none!important}
        .sidebar nav a{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;justify-content:center;padding:14px 0!important;color:#ccc;text-decoration:none;font-size:13px;transition:background .15s ease}
        .sidebar nav a i{font-size:22px;color:#fff}
        .sidebar nav a:hover{background:rgba(255,255,255,0.1)}
        .sidebar a::after{content:attr(data-tooltip);position:absolute;left:80px;top:50%;transform:translateY(-50%);background:#111;color:#fff;padding:6px 12px;border-radius:6px;font-size:13px;opacity:0;pointer-events:none;transition:.15s ease;white-space:nowrap}
        .sidebar a::before{content:'';position:absolute;left:72px;top:50%;transform:translateY(-50%);border:6px solid transparent;border-right-color:#111;opacity:0;transition:.15s ease}
        .sidebar a:hover::after,.sidebar a:hover::before{opacity:1}
        .content,.monteur-content{margin-left:70px!important;padding:20px;margin-top:60px}
        .topbar{position:fixed;width:100%;height:60px;top:0;left:0;z-index:1200}
        .hamburger{display:none!important}
        .logo-header{height:32px}
        @media(max-width:480px){.sidebar{width:60px!important}.content{margin-left:60px!important;padding:15px}}

        .header-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:center}
        .subtle-note{font-size:12px;color:#6b7280;margin-top:6px}

        /* ✅ Object-tabel: waarden meer naar links (prettiger lezen) */
        .detail-table th{
            width:260px;
            padding-right:12px;
            white-space:nowrap;
            text-align:left;
        }
        .detail-table td{
            padding-left:8px;
            text-align:left;
        }

        /* ✅ Form layout */
        .obj-checks{
            display:flex;
            flex-direction:column;
            gap:8px;
            margin-top:10px;
            align-items:flex-start;
        }
        .obj-checks label{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:10px;
            width:auto;
            margin:0;
            padding:6px 0;
            text-align:left;
            font-weight:600;
            background:none;
            border:none;
        }
        .obj-checks input[type="checkbox"]{ margin:0; }

        /* ✅ Opslaan rechtsboven in header-actions */
        .header-actions .btn-primary{margin-left:auto}

        .badge-warn{display:inline-block;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:8px 10px;border-radius:10px;font-size:12px}
    </style>
</head>

<body>

<div class="topbar">
    <div class="topbar-left">
        <img src="/template/ABCBFAV.png" class="logo-header" alt="ABCB">
    </div>
    <div class="topbar-right">
        <span>👷 <?= e($_SESSION['user']['voornaam'] ?? 'Monteur') ?></span>
        <button id="darkModeToggle" class="dark-toggle" type="button"><i class="fa-solid fa-moon"></i></button>
        <a href="/logout.php" title="Uitloggen"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="layout">
    <aside class="sidebar">
        <nav>
            <a href="/monteur/monteur_dashboard.php" data-tooltip="Dashboard"><i class="fa-solid fa-gauge"></i></a>
            <a href="/monteur/mijn_planning.php" data-tooltip="Mijn Planning"><i class="fa-solid fa-calendar-days"></i></a>
            <a href="/monteur/mijn_uren.php" data-tooltip="Mijn Uren"><i class="fa-solid fa-clock"></i></a>
        </nav>
    </aside>

    <main class="content monteur-content">

        <div class="header-actions">
            <a href="/monteur/werkbon_view.php?id=<?= (int)$werkbon_id ?>#objecten" class="btn btn-secondary">⬅ Terug</a>

            <button type="button" id="btnOnderhouden" class="btn btn-secondary">
                ✅ Onderhouden (vandaag)
            </button>

            <button type="submit" form="objForm" class="btn btn-primary">
                💾 Opslaan
            </button>

            <span id="saveMsg" class="subtle-note"></span>
        </div>

        <!-- ✅ Geen auto-koppel waarschuwing meer -->

        <!-- ✅ OBJECT (lees-info) -->
        <div class="card">
            <h3>Object</h3>
            <table class="detail-table" style="width:100%;">
                <tr>
                    <th>Werkbon nr.</th>
                    <td id="lblWerkbonnr"><?= e($wb['werkbonnummer'] ?? ('#'.$werkbon_id)) ?></td>
                </tr>

                <tr><th>Omschrijving</th><td id="lblOmschrijving"><?= e($object['omschrijving']) ?></td></tr>
                <tr><th>Merk</th><td id="lblMerk"><?= e($object['merk']) ?></td></tr>
                <tr><th>Type</th><td id="lblType"><?= e($object['type']) ?></td></tr>

                <tr><th>Datum installatie</th><td id="lblInstallatie"><?= dateNL((string)($object['datum_installatie'] ?? '')) ?></td></tr>
                <tr><th>Laatste onderhoud</th><td id="lblOnderhoud"><?= dateNL((string)($object['datum_onderhoud'] ?? '')) ?></td></tr>

                <tr><th>Fabricagejaar</th><td id="lblFabricagejaar"><?= e($object['fabricagejaar'] ?: '-') ?></td></tr>
                <tr><th>Jaar beproeving</th><td id="lblBeproeving"><?= e($object['beproeving_nen671_3'] ?: '-') ?></td></tr>

                <tr><th>Rijkstypekeur</th><td id="lblRijkstypekeur"><?= e($object['rijkstypekeur']) ?></td></tr>

                <tr><th>Uitgebreid onderhoud</th><td id="lblUitgebreid"><?= ((int)$object['uitgebreid_onderhoud'] === 1 ? 'Ja' : 'Nee') ?></td></tr>
                <tr><th>Gereviseerd</th><td id="lblGereviseerd"><?= ((int)$object['gereviseerd'] === 1 ? 'Ja' : 'Nee') ?></td></tr>
            </table>
        </div>

        <!-- ✅ OBJECTGEGEVENS -->
        <div class="card">
            <h3>Objectgegevens</h3>

            <form id="objForm" class="obj-form">

              <div class="row-1">
                <div>
                  <label>Omschrijving *</label>
                  <input type="text" name="omschrijving"
                         value="<?= e($object['omschrijving']) ?>" required>
                </div>
              </div>

              <div class="row-2">
                <div>
                  <label>Merk</label>
                  <input type="text" name="merk"
                         value="<?= e($object['merk']) ?>">
                </div>
                <div>
                  <label>Type</label>
                  <input type="text" name="type"
                         value="<?= e($object['type']) ?>">
                </div>
              </div>

              <div class="row-2">
                <div>
                  <label>Datum installatie</label>
                  <input type="date" name="datum_installatie"
                         value="<?= e($object['datum_installatie']) ?>">
                </div>
                <div>
                  <label>Datum onderhoud</label>
                  <input type="date" name="datum_onderhoud"
                         value="<?= e($object['datum_onderhoud']) ?>">
                </div>
              </div>

              <div class="row-3">
                <div>
                  <label>Fabricagejaar</label>
                  <input type="number" name="fabricagejaar"
                         value="<?= e($object['fabricagejaar']) ?>">
                </div>
                <div>
                  <label>Rijkstypekeur</label>
                  <input type="text" name="rijkstypekeur"
                         value="<?= e($object['rijkstypekeur']) ?>">
                </div>
                <div>
                  <label>Jaar beproeving</label>
                  <input type="number" name="beproeving_nen671_3"
                         value="<?= e($object['beproeving_nen671_3']) ?>">
                </div>
              </div>

              <div class="row-2">
                <div>
                  <label>Verdieping</label>
                  <input type="text" name="verdieping"
                         value="<?= e($object['verdieping']) ?>">
                </div>
                <div>
                  <label>Locatie</label>
                  <input type="text" name="locatie"
                         value="<?= e($object['locatie']) ?>">
                </div>
              </div>

              <div class="obj-checks">
                <label>
                  <input type="checkbox" name="uitgebreid_onderhoud" value="1"
                    <?= ((int)$object['uitgebreid_onderhoud'] === 1 ? 'checked' : '') ?>>
                  Uitgebreid onderhoud
                </label>
                <label>
                  <input type="checkbox" name="gereviseerd" value="1"
                    <?= ((int)$object['gereviseerd'] === 1 ? 'checked' : '') ?>>
                  Gereviseerd
                </label>
              </div>

              <input type="hidden" name="werkbon_id" value="<?= (int)$werkbon_id ?>">
              <input type="hidden" name="object_id" value="<?= (int)$object_id ?>">
            </form>
        </div>

        <!-- ✅ Werkbonwijzigingen -->
        <div class="card">
            <h3>Werkbonwijzigingen</h3>

            <?php if (!$hasInspecties): ?>
                <p class="subtle-note">Tabel <code>object_inspecties</code> niet gevonden. (Geen log beschikbaar.)</p>
            <?php elseif (empty($wijzigingen)): ?>
                <p class="subtle-note">Nog geen wijzigingen/registraties voor dit object binnen deze werkbon.</p>
            <?php else: ?>
                <table class="data-table small-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Tijd</th>
                            <th>Resultaat</th>
                            <th>Opmerking</th>
                            <th>Door</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($wijzigingen as $w): ?>
                        <tr>
                            <td><?= dateNL((string)($w['datum'] ?? '')) ?></td>
                            <td><?= e(substr((string)($w['tijd'] ?? ''), 0, 5)) ?></td>
                            <td><?= e($w['resultaat'] ?? '-') ?></td>
                            <td><?= e($w['opmerking'] ?? '') ?></td>
                            <td><?= e($w['door'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
/* ✅ Onderhouden vandaag (bestaande endpoint) */
document.getElementById('btnOnderhouden')?.addEventListener('click', () => {
  const btn = document.getElementById('btnOnderhouden');
  btn.disabled = true;

  fetch('/monteur/ajax_object_onderhouden.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      werkbon_id: <?= (int)$werkbon_id ?>,
      object_id:  <?= (int)$object_id ?>
    })
  })
  .then(r => r.json())
  .then(d => {
    if (!d?.ok) { alert('Fout: ' + (d?.msg || 'onbekend')); return; }
    if (d.today) document.getElementById('lblOnderhoud').textContent = d.today.split('-').reverse().join('-');
    location.reload();
  })
  .catch(() => alert('Verbinding mislukt.'))
  .finally(() => { btn.disabled = false; });
});

/* ✅ Opslaan objectgegevens */
document.getElementById('objForm')?.addEventListener('submit', (ev) => {
  ev.preventDefault();
  const form = ev.currentTarget;
  const msg  = document.getElementById('saveMsg');
  msg.textContent = 'Opslaan...';

  const fd = new FormData(form);

  // checkboxen netjes als 0/1
  if (!fd.has('uitgebreid_onderhoud')) fd.set('uitgebreid_onderhoud', '0'); else fd.set('uitgebreid_onderhoud', '1');
  if (!fd.has('gereviseerd')) fd.set('gereviseerd', '0'); else fd.set('gereviseerd', '1');

  fetch('/monteur/ajax_object_update.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(d => {
    if (!d?.ok) {
      msg.textContent = '';
      alert('Opslaan mislukt: ' + (d?.msg || 'Onbekend'));
      return;
    }

    msg.textContent = '✅ Opgeslagen';

    // labels updaten (direct zichtbaar)
    document.getElementById('lblOmschrijving').textContent    = fd.get('omschrijving') || '';
    document.getElementById('lblMerk').textContent           = fd.get('merk') || '';
    document.getElementById('lblType').textContent           = fd.get('type') || '';
    document.getElementById('lblRijkstypekeur').textContent   = fd.get('rijkstypekeur') || '';

    const di = fd.get('datum_installatie') || '';
    document.getElementById('lblInstallatie').textContent = di ? di.split('-').reverse().join('-') : '-';

    const dox = fd.get('datum_onderhoud') || '';
    document.getElementById('lblOnderhoud').textContent = dox ? dox.split('-').reverse().join('-') : '-';

    document.getElementById('lblFabricagejaar').textContent = fd.get('fabricagejaar') || '-';
    document.getElementById('lblBeproeving').textContent    = fd.get('beproeving_nen671_3') || '-';

    document.getElementById('lblUitgebreid').textContent  = (fd.get('uitgebreid_onderhoud') === '1') ? 'Ja' : 'Nee';
    document.getElementById('lblGereviseerd').textContent = (fd.get('gereviseerd') === '1') ? 'Ja' : 'Nee';

    setTimeout(() => { msg.textContent = ''; }, 1500);
  })
  .catch(err => {
    console.error(err);
    msg.textContent = '';
    alert('Netwerkfout bij opslaan.');
  });
});
</script>

<script>
/* DARK MODE */
document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const toggle = document.getElementById("darkModeToggle");
  if (localStorage.getItem("darkMode") === "enabled") {
    body.classList.add("dark-mode");
    toggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
  }
  toggle?.addEventListener("click", () => {
    body.classList.toggle("dark-mode");
    const enabled = body.classList.contains("dark-mode");
    localStorage.setItem("darkMode", enabled ? "enabled" : "disabled");
    toggle.innerHTML = enabled ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
  });
});
</script>

</body>
</html>
