<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

$pageTitle = "Planboard - Monteurs (Weekoverzicht zonder tijdlijn)";

// ✅ Monteurs ophalen
$monteurs = [];
$res = $conn->query("
    SELECT medewerker_id, CONCAT(voornaam, ' ', achternaam) AS naam
    FROM medewerkers
    WHERE rol = 'Monteur'
    ORDER BY achternaam, voornaam
");
while ($r = $res->fetch_assoc()) {
    $monteurs[] = [
        'id'    => (int)$r['medewerker_id'],
        'title' => $r['naam']
    ];
}
$monteursJson = json_encode($monteurs, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Planboard - Monteurs</title>

    <link rel="stylesheet" href="/template/style.css">
    <link rel="icon" href="/template/favicon.ico">
    <link rel="apple-touch-icon" href="/template/ABCBFAV.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ✅ FullCalendar Scheduler (bevat interaction al) -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.10/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.10/index.global.min.js"></script>

    <style>
        .submenu { margin-top:10px; border-top:1px solid #e5e7eb; }
        .submenu-toggle{
            width:100%; background:none; border:none; font-size:15px;
            color:var(--sidebar-link,#333); padding:12px 20px; text-align:left;
            cursor:pointer; display:flex; justify-content:space-between; align-items:center;
            transition:background .2s ease;
        }
        .submenu-toggle:hover{ background:var(--sidebar-hover,#e2e8f0); }
        .toggle-icon{ font-weight:bold; font-size:16px; }
        .submenu-list{ list-style:none; padding-left:20px; margin:0; display:none; }
        .submenu-list li a{
            display:block; padding:8px 15px; color:var(--sidebar-link,#333);
            font-size:14px; border-radius:4px;
        }
        .submenu-list li a:hover{ background:var(--sidebar-hover,#e2e8f0); }
        .submenu.open .submenu-list{ display:block; }

        #calendar { max-width:100%; margin:10px auto; }

        .fc-event, .fc-timeline-event, .fc-event-main {
            background-color:#2954cc !important;
            color:#fff !important;
            border:none !important;
        }

        .werkbon-block{ font-size:12px; line-height:1.35; white-space:normal; }
        .werkbon-block .wb-time{ font-weight:700; color:#e5e7eb; }
        .werkbon-block .wb-title{ font-weight:600; margin-top:2px; color:#fff; }
        .werkbon-block .wb-klant{ color:#f1f5f9; margin-top:2px; }
        .werkbon-block .wb-status{ color:#e5e7eb; margin-top:2px; font-size:11px; }

        .event-popup{
            position:fixed; inset:0; background:rgba(0,0,0,0.5);
            display:flex; justify-content:center; align-items:center; z-index:9999;
        }
        .event-popup-content{
            background:#fff; padding:20px; border-radius:10px; width:440px; max-width:95%;
            position:relative; box-shadow:0 4px 20px rgba(0,0,0,0.2);
        }
        .event-popup-content .popup-close{
            position:absolute; top:8px; right:10px; background:none; border:none;
            font-size:20px; cursor:pointer;
        }
        .popup-body{ margin-top:10px; }
        .popup-row{ display:flex; flex-direction:column; margin-bottom:8px; font-size:13px; }
        .popup-row label{ font-weight:600; margin-bottom:2px; }
        .popup-row input[type="text"], .popup-row input[type="time"]{
            padding:6px 8px; border-radius:6px; border:1px solid #ddd; font-size:13px;
        }
        .popup-text{
            padding:6px 8px; background:#f9fafb; border-radius:6px; border:1px solid #e5e7eb;
            min-height:30px;
        }
        .popup-footer{
            margin-top:12px; display:flex; justify-content:space-between; align-items:center;
            gap:8px; flex-wrap:wrap;
        }
        .popup-left, .popup-right{ display:flex; gap:8px; align-items:center; }

        .btn-popup{
            background:transparent; color:#333; border:1px solid #ccc; padding:6px 10px;
            font-size:14px; border-radius:6px; cursor:pointer; text-decoration:none;
        }
        .btn-popup:hover{ background:#f3f4f6; color:#111; border-color:#bbb; }

        .btn-popup-outline{
            background:#fff; color:#333; border:1px dashed #aaa; padding:6px 10px;
            font-size:13px; border-radius:6px; cursor:pointer;
        }
        .btn-popup-outline:hover{ background:#f3f4f6; }
    </style>
</head>

<body>

<div class="topbar">
    <div class="topbar-left">
        <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fa-solid fa-bars"></i>
        </button>
        <img src="/template/ABCBFAV.png" alt="Logo" class="logo-header">
    </div>
    <div class="topbar-right">
        <?php if (!empty($_SESSION['user'])): ?>
            <span>👤 <?= htmlspecialchars($_SESSION['user']['naam'] ?? 'Gebruiker') ?></span>
        <?php endif; ?>
        <button id="darkModeToggle" class="dark-toggle"><i class="fa-solid fa-moon"></i></button>
        <a href="/profiel.php"><i class="fa-solid fa-user"></i> Mijn Profiel</a>
        <a href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Uitloggen</a>
    </div>
</div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <nav>
            <a href="/index.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="/planboard/planboard.php" class="active"><i class="fa-solid fa-calendar-days"></i> <span>Planboard</span></a>
            <a href="/klanten.php"><i class="fa-solid fa-users"></i> <span>Klanten</span></a>
            <a href="/werkbonnen.php"><i class="fa-solid fa-file-lines"></i> <span>Werkbonnen</span></a>
            <a href="/artikelen.php"><i class="fa-solid fa-box"></i> <span>Artikelen</span></a>
            <a href="/magazijn/index.php"><i class="fa-solid fa-warehouse"></i> <span>Magazijn</span></a>
            <a href="/contracten.php"><i class="fa-solid fa-file-contract"></i> <span>Contracten</span></a>
            <a href="/objecten.php"><i class="fa-solid fa-cubes"></i> <span>Objecten</span></a>

            <div class="submenu">
                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <i class="fa-solid fa-gear"></i> Instellingen
                    <span class="toggle-icon">+</span>
                </button>
                <ul class="submenu-list">
                    <li><a href="/systeembeheer/bedrijfsgegevens.php"><i class="fa-solid fa-building"></i> Bedrijfsgegevens</a></li>
                    <li><a href="/medewerkers.php"><i class="fa-solid fa-id-card"></i> Medewerkers</a></li>
                    <li><a href="/systeemrechten.php"><i class="fa-solid fa-shield-halved"></i> Systeemrechten</a></li>
                    <li><a href="/systeembeheer/uursoorten.php"><i class="fa-solid fa-clock"></i> Uursoorten</a></li>
                    <li><a href="/systeembeheer/type_werkzaamheden.php"><i class="fa-solid fa-file-import"></i> Type werkzaamheden</a></li>
                    <li><a href="/systeembeheer/categorieen.php"><i class="fa-solid fa-tags"></i> Categorieën</a></li>
                    <li><a href="/systeembeheer/object_status.php"><i class="fa-solid fa-traffic-light"></i> Objectstatus</a></li>
                    <li><a href="/magazijn/magazijnen.php"><i class="fa-solid fa-warehouse"></i> Magazijnbeheer</a></li>
                    <li><a href="/systeembeheer/pdf_instellingen.php"><i class="fa-solid fa-file-pdf"></i> PDF-instellingen</a></li>
                    <li><a href="/mail/mail_log.php"><i class="fa-solid fa-envelope"></i> Mail log</a></li>
                    <li><a href="/import/index.php"><i class="fa-solid fa-file-import"></i> Importeren</a></li>
                </ul>
            </div>
        </nav>

        <div class="sidebar-footer">
            <button onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
                <i class="fa-solid fa-angles-left"></i>
            </button>
        </div>
    </aside>

    <main class="content">
        <h2>Planboard</h2>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0;">Planning Monteurs</h3>

                <!-- ✅ JOUW knoppen (niet FullCalendar) -->
                <div class="calendar-nav" style="display:flex; justify-content:flex-end; gap:8px;">
                    <button id="prevWeek" class="btn btn-secondary">‹</button>
                    <button id="today" class="btn btn-secondary">Vandaag</button>
                    <button id="nextWeek" class="btn btn-secondary">›</button>
                </div>
            </div>

            <div id="calendar"></div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>In te plannen werkbonnen</h3>
            <p style="font-size:13px; color:#555; margin-top:6px;">
                Sleep een werkbon naar een monteur in de kalender om deze in te plannen.
            </p>

            <div class="table-responsive">
                <table class="data-table" id="unplannedTable">
                    <thead>
                    <tr>
                        <th>Werkbonnummer</th>
                        <th>Type werkzaamheden</th>
                        <th>Klant</th>
                        <th>Werkadres</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody><!-- via JS --></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const calendarEl = document.getElementById('calendar');
  const resources  = <?= $monteursJson ?>;

  // -------------------------
  // Popup helper
  // -------------------------
  function showEventPopup(werkbon) {
    if (!werkbon || !werkbon.werkbon_id) {
      alert('Kan werkbon niet openen (ongeldige data).');
      return;
    }

    const fmtNL = (iso) => {
      if (!iso) return '';
      const p = String(iso).split('-');
      if (p.length !== 3) return iso;
      return `${p[2]}-${p[1]}-${p[0]}`;
    };

    const datumNL = fmtNL(werkbon.uitvoerdatum);
    const start   = (werkbon.starttijd ?? '').slice(0, 5);
    const eind    = (werkbon.eindtijd ?? '').slice(0, 5);
    const omschrijvingHtml = (werkbon.omschrijving ?? '').replace(/\n/g, '<br>');

    const popup = document.createElement('div');
    popup.className = 'event-popup';

    popup.innerHTML = `
      <div class="event-popup-content">
        <button class="popup-close" aria-label="Sluiten">&times;</button>
        <h3>Werkbon ${werkbon.werkbonnummer ?? ''}</h3>

        <div class="popup-body">
          <div class="popup-row">
            <label>Datum</label>
            <input type="text" id="wbDatum" value="${datumNL || ''}" readonly>
          </div>

          <div class="popup-row">
            <label>Starttijd</label>
            <input type="time" id="wbStarttijd" value="${start}">
          </div>

          <div class="popup-row">
            <label>Eindtijd</label>
            <input type="time" id="wbEindtijd" value="${eind}">
          </div>

          <div class="popup-row">
            <label>Klant</label>
            <div class="popup-text">${werkbon.klant ?? '-'}</div>
          </div>

          <div class="popup-row">
            <label>Werkadres</label>
            <div class="popup-text">${werkbon.werkadres ?? '-'}</div>
          </div>

          <div class="popup-row">
            <label>Omschrijving</label>
            <div class="popup-text">${omschrijvingHtml}</div>
          </div>
        </div>

        <div class="popup-footer">
          <div class="popup-left">
            <button type="button" class="btn-popup-outline" data-unplan>↩ Terug naar "In te plannen"</button>
          </div>
          <div class="popup-right">
            <button type="button" class="btn-popup" data-save>💾 Tijden opslaan</button>
            <a class="btn-popup" href="/werkbon_bewerk.php?id=${werkbon.werkbon_id}">✏️ Bewerken</a>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(popup);

    popup.querySelector('.popup-close')?.addEventListener('click', () => popup.remove());
    popup.addEventListener('click', (e) => { if (e.target === popup) popup.remove(); });

    // Unplan (optioneel: als jij api/unplan_event.php hebt)
    popup.querySelector('[data-unplan]')?.addEventListener('click', () => {
      if (!confirm('Werkbon terugzetten naar "In te plannen"?')) return;

      fetch('/planboard/api/unplan_event.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: werkbon.werkbon_id })
      })
      .then(r => r.json())
      .then(d => {
        if (!d.success) { alert('Fout: ' + (d.error || 'Onbekend')); return; }
        window.calendar?.refetchEvents?.();
        loadUnplanned();
        popup.remove();
      })
      .catch(() => alert('Unplan API fout.'));
    });

    // Tijden opslaan
    popup.querySelector('[data-save]')?.addEventListener('click', () => {
      const s = popup.querySelector('#wbStarttijd')?.value || '';
      const e = popup.querySelector('#wbEindtijd')?.value || '';

      fetch('/planboard/api/update_event.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          id: werkbon.werkbon_id,
          uitvoerdatum: (werkbon.uitvoerdatum ?? null),
          starttijd: s || null,
          eindtijd: e || null,
          monteur_id: werkbon.monteur_id ? Number(werkbon.monteur_id) : null
        })
      })
      .then(r => r.json())
      .then(d => {
        if (!d.success) { alert('Fout: ' + (d.error || 'Onbekend')); return; }
        window.calendar?.refetchEvents?.();
        loadUnplanned();
        popup.remove();
      })
      .catch(() => alert('Opslaan tijden mislukt.'));
    });
  }

  // -------------------------
  // Calendar
  // -------------------------
  window.calendar = new FullCalendar.Calendar(calendarEl, {
    schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
    initialView: 'resourceTimelineWeek',

    headerToolbar: false,              // ✅ geen ingebouwde toolbar
    resourceAreaHeaderContent: 'Monteurs',
    firstDay: 1,
    hiddenDays: [0, 6],
    locale: 'nl',
    weekNumbers: true,
    weekNumberCalculation: 'ISO',
    height: 'auto',

    slotDuration: { days: 1 },
    slotMinWidth: 140,
    resourceAreaWidth: '200px',
    slotLabelFormat: [{ weekday: 'short', day: '2-digit', month: '2-digit' }],

    editable: true,
    droppable: true,
    resources: resources,

    // Drop vanuit "In te plannen"
    eventReceive: function(info) {
      const werkbonId = info.event.id;
      const startStr  = info.event.startStr; // YYYY-MM-DD of ISO
      const ymd       = String(startStr).split('T')[0];

      const monteurId = info.event.getResources()[0]?.id;

      if (!monteurId) {
        alert("Sleep de werkbon naar een monteur-rij.");
        info.revert();
        return;
      }

      fetch('/planboard/api/plan_werkbon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: werkbonId,
          monteur_id: monteurId,
          start: ymd,
          end: null
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          info.event.remove();
          window.calendar.refetchEvents();
          loadUnplanned();
        } else {
          alert('❌ Fout bij plannen: ' + (data.error || 'Onbekende fout'));
          info.revert();
        }
      })
      .catch(err => { console.error(err); info.revert(); });
    },

    // ✅ VERPLAATSEN (FIX: geen toISOString → geen -1 dag bug)
    eventDrop: function(info) {
      persistMove(info);
    },

    // Open popup
    eventClick: function(info) {
      const werkbonId = info.event.id;

      const url = new URL('/planboard/api/get_event_detail.php', window.location.origin);
      url.searchParams.set('id', werkbonId);

      fetch(url.toString())
        .then(res => res.json())
        .then(data => {
          if (data && data.success) showEventPopup(data.werkbon);
          else alert('Fout bij ophalen: ' + (data?.error || 'Onbekend'));
        })
        .catch(err => {
          console.error(err);
          alert('Kan werkbon niet openen (API fout).');
        });
    },

    // Event content
    eventContent: function(arg) {
      const d = arg.event.extendedProps || {};

      const startTxt = d.starttijd ? String(d.starttijd).slice(0,5) : '';
      const endTxt   = d.eindtijd ? String(d.eindtijd).slice(0,5) : '';
      const time     = (startTxt || endTxt) ? `${startTxt}${endTxt ? ' - ' + endTxt : ''}` : '';

      const klant  = d.klant || '';
      const adres  = d.werkadres || '';

      const status = d.status || '';

      const wsRaw = d.werkzaamheden_status || '';
      const wsMap = { open:'Open', bezig:'Bezig', onderweg:'Onderweg', op_locatie:'Op locatie', gereed:'Gereed' };
      const ws = wsMap[String(wsRaw).toLowerCase()] || wsRaw;

      const msRaw = d.monteur_status || '';
      const msMap = { onderweg:'Onderweg', op_locatie:'Op locatie', gereed:'Gereed' };
      const ms = msMap[String(msRaw).toLowerCase()] || msRaw;

      const html = `
        <div class="werkbon-block">
          <div class="wb-time">${time}</div>
          <div class="wb-title">${arg.event.title}</div>
          <div class="wb-klant">${klant}${adres ? ' | ' + adres : ''}</div>
          ${status ? `<div class="wb-status">Werkbon: ${status}</div>` : ''}
          ${wsRaw ? `<div class="wb-status">Monteur status: ${ws}</div>` : ''}
          ${msRaw ? `<div class="wb-status">Monteur: ${ms}</div>` : ''}
        </div>
      `;
      return { html };
    },

    // ✅ absolute endpoint
    events: '/planboard/api/fetch_events.php'
  });

  window.calendar.render();

  // Jouw knoppen
  document.getElementById('prevWeek')?.addEventListener('click', () => window.calendar.prev());
  document.getElementById('today')?.addEventListener('click', () => window.calendar.today());
  document.getElementById('nextWeek')?.addEventListener('click', () => window.calendar.next());

  // Veilig refreshen
  setInterval(() => {
    if (window.calendar && typeof window.calendar.refetchEvents === 'function') {
      window.calendar.refetchEvents();
    }
  }, 10000);

  // -------------------------
  // Helpers
  // -------------------------
  function persistMove(info) {
    const werkbonId = info.event.id;

    // ✅ FIX: pak exact de dag waarop je dropt (geen UTC shift)
    const dateStr = String(info.event.startStr || '').split('T')[0];

    const monteurId = info.event.getResources()[0]?.id || null;

    fetch('/planboard/api/update_event.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        id: werkbonId,
        uitvoerdatum: dateStr || null,
        monteur_id: monteurId
      })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert('Fout bij opslaan: ' + (data.error || 'Onbekend'));
        info.revert();
      }
    })
    .catch(err => {
      console.error(err);
      info.revert();
    });
  }

  // Ongepland laden
  window.loadUnplanned = function loadUnplanned() {
    fetch('/planboard/api/fetch_unplanned.php')
      .then(res => res.json())
      .then(data => {
        const tbody = document.querySelector('#unplannedTable tbody');
        tbody.innerHTML = '';

        (data || []).forEach(item => {
          const tr = document.createElement('tr');
          tr.classList.add('draggable-row');
          tr.dataset.id = item.id;

          tr.innerHTML = `
            <td>${item.werkbonnummer ?? ''}</td>
            <td>${item.type_werkzaamheden ?? '-'}</td>
            <td>${item.klant ?? '-'}</td>
            <td>${item.werkadres ?? '-'}</td>
            <td>${item.status ?? '-'}</td>
          `;
          tbody.appendChild(tr);

          // Sleepbaar maken
          new FullCalendar.Draggable(tr, {
            eventData: {
              id: String(item.id),
              title: `${item.werkbonnummer} - ${item.klant}`,
              extendedProps: {
                type_werkzaamheden: item.type_werkzaamheden,
                klant: item.klant,
                werkadres: item.werkadres,
                status: item.status,
                werkzaamheden_status: item.werkzaamheden_status || '',
                monteur_status: item.monteur_status || ''
              }
            }
          });

          // Klik: open popup ook vanuit tabel
          tr.addEventListener('click', () => {
            const url = new URL('/planboard/api/get_event_detail.php', window.location.origin);
            url.searchParams.set('id', item.id);

            fetch(url.toString())
              .then(r => r.json())
              .then(d => {
                if (d && d.success) showEventPopup(d.werkbon);
                else alert('Fout bij ophalen: ' + (d?.error || 'Onbekend'));
              })
              .catch(() => alert('Kan werkbon niet openen (API fout).'));
          });
        });
      })
      .catch(err => console.error(err));
  };

  loadUnplanned();
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const body = document.body;
  const toggle = document.getElementById("darkModeToggle");

  if (localStorage.getItem("darkMode") === "enabled") {
    body.classList.add("dark-mode");
    toggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
  }

  toggle.addEventListener("click", () => {
    body.classList.toggle("dark-mode");
    const enabled = body.classList.contains("dark-mode");
    localStorage.setItem("darkMode", enabled ? "enabled" : "disabled");
    toggle.innerHTML = enabled ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
  });

  document.querySelectorAll(".flash").forEach(flash => {
    const timeout = flash.classList.contains("flash-error") ? 7000 : 4000;
    setTimeout(() => {
      flash.style.transition = "opacity 0.5s ease";
      flash.style.opacity = "0";
      setTimeout(() => flash.remove(), 600);
    }, timeout);
  });
});

function toggleSubmenu(button) {
  const submenu = button.closest('.submenu');
  const icon = button.querySelector('.toggle-icon');
  const isOpen = submenu.classList.toggle('open');
  icon.textContent = isOpen ? '−' : '+';
}
</script>

</body>
</html>
