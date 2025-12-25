<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/init.php';

// ========================
// 🔐 Toegang
// ========================
if (empty($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: login.php");
    exit;
}

$pageTitle = "Objecten overzicht";

// ========================
// 🔍 Zoek & sort onthouden
// ========================
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['zoek']) || isset($_GET['sort'])) {
    $_SESSION['objecten_filter'] = [
        'zoek' => $_GET['zoek'] ?? '',
        'sort' => $_GET['sort'] ?? 'asc'
    ];
} elseif (!isset($_SESSION['objecten_filter'])) {
    $_SESSION['objecten_filter'] = ['zoek' => '', 'sort' => 'asc'];
}

$zoek = $_SESSION['objecten_filter']['zoek'];
$sort = $_SESSION['objecten_filter']['sort'];

if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    $_SESSION['objecten_filter'] = ['zoek' => '', 'sort' => 'asc'];
    header("Location: objecten.php");
    exit;
}

// ========================
// ⚙️ SQL-filter + sortering
// ========================
$zoekSql = '';
if ($zoek !== '') {
    $zoekClean = $conn->real_escape_string($zoek);
    $zoekSql = "AND (
        o.code LIKE '%$zoekClean%' OR 
        o.omschrijving LIKE '%$zoekClean%' OR 
        k.bedrijfsnaam LIKE '%$zoekClean%' OR 
        wa.bedrijfsnaam LIKE '%$zoekClean%'
    )";
}
$orderSql = ($sort === 'desc') ? 'DESC' : 'ASC';

// ========================
// 📄 Query
// ========================
$sql = "
    SELECT 
        o.object_id, o.code, o.omschrijving, o.resultaat,
        o.merk, o.type, o.rijkstypekeur, o.fabricagejaar, 
        o.beproeving_nen671_3, o.verdieping, o.locatie, 
        o.opmerkingen, o.datum_onderhoud,
        k.debiteurnummer, k.bedrijfsnaam AS klantnaam,
        wa.bedrijfsnaam AS werkadres_naam,
        os.kleur AS resultaat_kleur
    FROM objecten o
    LEFT JOIN klanten k       ON k.klant_id = o.klant_id
    LEFT JOIN werkadressen wa ON wa.werkadres_id = o.werkadres_id
    LEFT JOIN object_status os ON os.naam = o.resultaat
    WHERE 1 $zoekSql
    ORDER BY (o.code+0) $orderSql
";
$result = $conn->query($sql);

// ========================
// 🔦 Highlight helper
// ========================
if (!function_exists('highlight')) {
    function highlight($text, $zoek) {
        $text = (string)($text ?? '');
        if ($zoek === '' || $text === '') return htmlspecialchars($text);
        $escaped = htmlspecialchars($text);
        $escapedTerm = htmlspecialchars($zoek);
        return preg_replace('/(' . preg_quote($escapedTerm, '/') . ')/i', '<mark>$1</mark>', $escaped);
    }
}

ob_start();
?>
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <h2>📦 Objecten overzicht</h2>

  <div class="header-actions" style="display:flex;align-items:center;gap:10px;">
      <form method="get" action="" style="display:flex;align-items:center;gap:6px;">
        <input type="text" name="zoek" placeholder="🔍 Zoeken..." value="<?= htmlspecialchars($zoek) ?>" 
               style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;min-width:180px;">
        <select name="sort" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
            <option value="asc"  <?= $sort==='asc'?'selected':'' ?>>Code ↑</option>
            <option value="desc" <?= $sort==='desc'?'selected':'' ?>>Code ↓</option>
        </select>
        <button type="submit" class="btn">Toepassen</button>
        <?php if ($zoek !== '' || $sort!=='asc'): ?>
          <a href="objecten.php?reset=1" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
      </form>

      <a href="object_toevoegen.php" class="btn">➕ Nieuw object</a>
      <button type="button" class="btn" title="Tabel configuratie" onclick="openModal('configModal')">
          <i class="fa-solid fa-wrench"></i>
      </button>
  </div>
</div>

<!-- ⚙️ Config Modal -->
<div id="configModal" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Tabel configuratie</h3>
    <form id="configForm" style="display:flex; flex-wrap:wrap; gap:10px;">
      <?php
      $kolommen = [
        1=>"Selectie",
        2=>"Code",
        3=>"Klant",
        4=>"Werkadres",
        5=>"Omschrijving",
        6=>"Merk",
        7=>"Type",
        8=>"Rijkstypekeur",
        9=>"Fabricagejaar",
        10=>"Laatste onderhoud",
        11=>"Verdieping",
        12=>"Locatie",
        13=>"Status",
        14=>"Acties"
      ];
      foreach ($kolommen as $idx=>$label): ?>
        <label style="flex:1 1 45%;">
          <input type="checkbox" data-col="<?= $idx ?>" checked> <?= htmlspecialchars($label) ?>
        </label>
      <?php endforeach; ?>
    </form>
    <div class="modal-footer">
      <button id="configSave" class="btn">Opslaan</button>
      <button type="button" class="btn btn-secondary" onclick="closeModal('configModal')">Sluiten</button>
    </div>
  </div>
</div>

<form method="post" action="objecten_bulk_delete.php" id="bulkForm">
  <div style="margin-bottom:10px;">
    <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" 
            style="background:#d32f2f;color:#fff;display:none;transition:opacity .2s ease;">
      🗑 Verwijder geselecteerde
    </button>
    <span id="bulkCount" style="margin-left:10px;color:#555;font-size:13px;"></span>
  </div>

  <div class="card">
    <table class="data-table" id="objectenTable">
      <thead>
        <tr>
          <th style="width:25px;"><input type="checkbox" id="selectAll"></th>
          <th>Code</th>
          <th>Klant</th>
          <th>Werkadres</th>
          <th>Omschrijving</th>
          <th>Merk</th>
          <th>Type</th>
          <th>Rijkstypekeur</th>
          <th>Fabricagejaar</th>
          <th>Laatste onderhoud</th>
          <th>Verdieping</th>
          <th>Locatie</th>
          <th>Status</th>
          <th style="width:150px;">Acties</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
              $kleurHex = '#999';
              if (!empty($row['resultaat_kleur'])) {
                  $kleurHex = match ($row['resultaat_kleur']) {
                      'groen'  => '#28a745',
                      'oranje' => '#ff9800',
                      'rood'   => '#dc3545',
                      default  => '#999'
                  };
              }
              $badge = $row['resultaat']
                  ? '<span style="background:'.$kleurHex.';color:#fff;padding:3px 10px;border-radius:6px;">'
                    . htmlspecialchars($row['resultaat']) . '</span>'
                  : '<em>Geen status</em>';

              $kleurStyle = '';
              $laatsteOnd = '-';
              if (!empty($row['datum_onderhoud']) && $row['datum_onderhoud'] !== '0000-00-00') {
                  $onderhoud = new DateTime($row['datum_onderhoud']);
                  $vandaag = new DateTime();
                  $verschil = $vandaag->diff($onderhoud);
                  $dagen = (int)$verschil->format('%r%a');
                  if ($dagen <= -365) $kleurStyle = 'color:#d32f2f; font-weight:bold;';
                  elseif ($dagen <= -275) $kleurStyle = 'color:#f57c00; font-weight:bold;';
                  $laatsteOnd = date('d-m-Y', strtotime($row['datum_onderhoud']));
              }
            ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= (int)$row['object_id'] ?>"></td>
              <td><?= highlight($row['code'], $zoek) ?></td>
              <td><?= highlight(($row['debiteurnummer'] ?? '') . ' - ' . ($row['klantnaam'] ?? ''), $zoek) ?></td>
              <td><?= highlight($row['werkadres_naam'] ?? '-', $zoek) ?></td>
              <td><?= highlight($row['omschrijving'] ?? '', $zoek) ?></td>
              <td><?= htmlspecialchars($row['merk'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['type'] ?? '') ?></td>
	      <td><?= ($row['rijkstypekeur'] && $row['rijkstypekeur'] != '0') 
               ? htmlspecialchars($row['rijkstypekeur']) 
                : '' ?></td>
              <td><?= htmlspecialchars($row['fabricagejaar'] ?? '') ?></td>
              <td style="<?= $kleurStyle ?>"><?= $laatsteOnd ?></td>
              <td><?= htmlspecialchars($row['verdieping'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['locatie'] ?? '') ?></td>
              <td><?= $badge ?></td>
              <td class="actions">
                <a href="object_detail.php?id=<?= (int)$row['object_id'] ?>" title="Details">📄</a>
                <a href="object_bewerk.php?id=<?= (int)$row['object_id'] ?>" title="Bewerken">✏️</a>
                <a href="#" onclick="confirmDelete(<?= (int)$row['object_id'] ?>); return false;" title="Verwijderen">🗑</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="14" style="text-align:center;color:#777;">Geen objecten gevonden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<link rel="stylesheet" href="template/modal.css">
<script src="template/modal.js"></script>
<script>
function openModal(id){ document.getElementById(id).style.display='block'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }

// ✅ Enkel verwijderen
function confirmDelete(id) {
  if (confirm('Weet je zeker dat je dit object wilt verwijderen?')) {
    window.location.href = 'object_verwijder.php?id=' + id;
  }
}

// ✅ Selecteer alles + bulk verwijderen + knop pas bij ≥2 selecties
document.addEventListener('DOMContentLoaded', ()=>{
  const selectAll = document.getElementById('selectAll');
  const bulkForm  = document.getElementById('bulkForm');
  const bulkBtn   = document.getElementById('bulkDeleteBtn');
  const bulkCount = document.getElementById('bulkCount');
  const checkboxes = () => document.querySelectorAll('input[name="ids[]"]');

  function updateBulkUI(){
    const selected = Array.from(checkboxes()).filter(cb => cb.checked).length;
    if (selected >= 2) {
      bulkBtn.style.display = 'inline-block';
      bulkCount.textContent = `${selected} geselecteerd`;
    } else {
      bulkBtn.style.display = 'none';
      bulkCount.textContent = selected > 0 ? `${selected} geselecteerd` : '';
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', ()=>{
      checkboxes().forEach(cb=>cb.checked = selectAll.checked);
      updateBulkUI();
    });
  }
  checkboxes().forEach(cb=>cb.addEventListener('change', updateBulkUI));

  if (bulkForm) {
    bulkForm.addEventListener('submit', e=>{
      const selected = Array.from(checkboxes()).filter(cb => cb.checked).length;
      if (selected < 2) {
        alert('Selecteer minimaal twee objecten om te verwijderen.');
        e.preventDefault();
        return;
      }
      if (!confirm(`Weet je zeker dat je ${selected} object(en) wilt verwijderen?`)) e.preventDefault();
    });
  }
});

// ✅ Kolomconfiguratie bewaren
document.getElementById('configSave').addEventListener('click', function(e){
  e.preventDefault();
  const prefs = {};
  document.querySelectorAll('#configForm input[type=checkbox]').forEach(chk=>{
    const col = parseInt(chk.dataset.col, 10) - 1;
    prefs[chk.dataset.col] = chk.checked;
    document.querySelectorAll('#objectenTable tr').forEach(tr=>{
      if (tr.cells[col]) tr.cells[col].style.display = chk.checked ? '' : 'none';
    });
  });
  localStorage.setItem('objectenTableConfig', JSON.stringify(prefs));
  closeModal('configModal');
});

window.addEventListener('DOMContentLoaded', ()=>{
  const raw = localStorage.getItem('objectenTableConfig');
  if (!raw) return;
  try {
    const prefs = JSON.parse(raw);
    for (const col in prefs) {
      const chk = document.querySelector(`#configForm input[data-col='${col}']`);
      if (chk) chk.checked = !!prefs[col];
      const idx = parseInt(col,10)-1;
      document.querySelectorAll('#objectenTable tr').forEach(tr=>{
        if (tr.cells[idx]) tr.cells[idx].style.display = prefs[col] ? '' : 'none';
      });
    }
  } catch(e){}
});
</script>

<style>
.data-table{width:100%;border-collapse:collapse;font-size:14px}
.data-table th,.data-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
.data-table th{background:#f8f9fa;font-weight:600;color:#333;white-space:nowrap}
.data-table tr:hover{background:#f2f6ff}
mark { background:#fff59d; color:#000; padding:0 2px; border-radius:2px; }
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:9999}
.modal{background:#fff;border-radius:12px;max-width:640px;width:100%;padding:18px}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
.btn-secondary{background:#ddd;color:#333;}
.btn-secondary:hover{background:#ccc;}
.page-header input[type=text]:focus,
.page-header select:focus{outline:none;border-color:#2954cc;}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/template.php';
