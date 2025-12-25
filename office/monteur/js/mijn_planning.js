// /monteur/js/mijn_planning.js
document.addEventListener("DOMContentLoaded", () => {
  const planningDiv = document.getElementById("planningList");
  const prevBtn = document.getElementById("prevDay");
  const nextBtn = document.getElementById("nextDay");
  const dateLabel = document.getElementById("dateLabel");
  const dateSub = document.getElementById("dateSub"); // mag bestaan, we zetten 'm leeg

  const STORAGE_KEY = "abcb_monteur_planning_date"; // YYYY-MM-DD

  if (!planningDiv || !prevBtn || !nextBtn || !dateLabel) {
    console.warn("Planning UI elements ontbreken.");
    return;
  }

  // -----------------------
  // Date helpers
  // -----------------------
  function startOfDay(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
  }

  function isWeekend(d) {
    const day = d.getDay(); // 0=zo,6=za
    return day === 0 || day === 6;
  }

  function shiftWorkday(date, dir) {
    const d = startOfDay(date);
    do {
      d.setDate(d.getDate() + dir);
    } while (isWeekend(d));
    return d;
  }

  function toNL(date) {
    const dd = String(date.getDate()).padStart(2, "0");
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const yyyy = date.getFullYear();
    return `${dd}-${mm}-${yyyy}`;
  }

  function weekdayNL(date) {
    return date.toLocaleDateString("nl-NL", { weekday: "long" });
  }

  function dateOneLine(date) {
    const wd = weekdayNL(date);
    return `${wd.charAt(0).toUpperCase() + wd.slice(1)} ${toNL(date)}`;
  }

  function saveDate(date) {
    const d = startOfDay(date);
    const iso = d.toISOString().slice(0, 10);
    localStorage.setItem(STORAGE_KEY, iso);
  }

  function loadSavedDate() {
    const s = localStorage.getItem(STORAGE_KEY);
    if (!s) return null;
    const d = new Date(`${s}T00:00:00`);
    if (Number.isNaN(d.getTime())) return null;
    return d;
  }

  // Start = opgeslagen datum, anders vandaag (maar nooit weekend)
  let currentDate = loadSavedDate() || new Date();
  currentDate = startOfDay(currentDate);
  if (isWeekend(currentDate)) currentDate = shiftWorkday(currentDate, +1);

  // -----------------------
  // Text helpers
  // -----------------------
  function safe(v) {
    return (v ?? "").toString();
  }

  function fmtHM(t) {
    return t ? safe(t).slice(0, 5) : "-";
  }

  function normalizePhone(raw) {
    const s = safe(raw).trim();
    if (!s) return "";
    return s.replace(/[^\d+]/g, "");
  }

  function mapsUrlFromWb(wb) {
    const full = [wb.adres, wb.postcode, wb.plaats].map(safe).join(" ").trim();
    const q = encodeURIComponent(full);
    return `https://www.google.com/maps/search/?api=1&query=${q}`;
  }

  // -----------------------
  // Kebab menu (FIXED + top z-index)
  // -----------------------
  let openMenuEl = null;
  let openAnchorBtn = null;

  function closeMenu() {
    if (openMenuEl) {
      openMenuEl.remove();
      openMenuEl = null;
      openAnchorBtn = null;
    }
  }

  function getMonteurStatus(wb) {
    // jouw API/DB wisselt soms van veldnaam: probeer meerdere
    return (
      safe(wb.monteur_status).trim() ||
      safe(wb.werkzaamheden_status).trim() ||
      "open"
    );
  }

  function buildMenuHtml(wb) {
    const telRaw = safe(wb.telefoon).trim();
    const telLabel = telRaw ? telRaw : "(geen nummer)";
    const telHref = normalizePhone(telRaw);
    const canCall = Boolean(telHref);

    const statusOptions = [
      ["open", "Open"],
      ["onderweg", "Onderweg"],
      ["op_locatie", "Op locatie"],
      ["bezig", "Bezig"],
      ["gereed", "Gereed"],
    ];

    const plannerStatus = safe(wb.status).trim() || "-";
    const monteurStatus = getMonteurStatus(wb);

    return `
      <div class="wb-menu" role="menu">
        <button class="wb-menu-item" data-act="objecten" type="button">
          <i class="fa-solid fa-cubes"></i> Objecten
        </button>

        <button class="wb-menu-item" data-act="bellen" type="button" ${canCall ? "" : "disabled"}>
          <i class="fa-solid fa-phone"></i> Bellen <span class="wb-menu-muted">${telLabel}</span>
        </button>

        <button class="wb-menu-item" data-act="nav" type="button">
          <i class="fa-solid fa-location-dot"></i> Navigeren
        </button>

        <div class="wb-menu-sep"></div>

        <div class="wb-menu-title">
          <i class="fa-solid fa-flag"></i> Status (planner)
        </div>
        <div class="wb-menu-mutedline">${plannerStatus}</div>

        <div class="wb-menu-sep"></div>

        <div class="wb-menu-title">
          <i class="fa-solid fa-user-gear"></i> Status (monteur)
        </div>
        <div class="wb-menu-mutedline">Huidig: <strong>${monteurStatus}</strong></div>

        ${statusOptions
          .map(
            ([key, label]) => `
              <button class="wb-menu-item" data-act="setstatus" data-status="${key}" type="button">
                ${label}
              </button>`
          )
          .join("")}
      </div>
    `;
  }

  // 🔥 BELANGRIJK: position = FIXED (niet absolute), zodat niets eroverheen kan vallen
  function positionMenuFixed(menu, anchorBtn) {
    const r = anchorBtn.getBoundingClientRect();

    const margin = 8;
    const desiredTop = r.bottom + margin;
    const desiredRight = window.innerWidth - r.right;

    menu.style.position = "fixed";
    menu.style.top = `${Math.max(8, desiredTop)}px`;
    menu.style.right = `${Math.max(8, desiredRight)}px`;
    menu.style.zIndex = "2147483647"; // hoogste practical
  }

  async function setMonteurStatus(werkbon_id, status) {
    const body = new URLSearchParams({
      werkbon_id: String(werkbon_id),
      status: String(status),
    });

    const resp = await fetch("/monteur/ajax_update_status.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
      cache: "no-store",
      credentials: "same-origin",
    });

    const json = await resp.json().catch(() => null);
    if (!resp.ok || !json?.ok) {
      const msg = json?.msg || `HTTP ${resp.status}`;
      throw new Error(msg);
    }
    return json.status;
  }

  function buildRow(wb) {
    const start = fmtHM(wb.starttijd);
    const eind = fmtHM(wb.eindtijd);
    const klant = safe(wb.klantnaam) || "-";
    const adres = [wb.adres, wb.postcode, wb.plaats].map(safe).join(" ").trim();

    const plannerStatus = safe(wb.status) || "-";
    const monteurStatus = getMonteurStatus(wb);

    const werkbonId = Number(wb.werkbon_id) || 0;

    return `
      <div class="wb-list-item" data-wbid="${werkbonId}">
        <div class="wb-main">
          <div class="wb-title"><strong>Werkbon ${safe(wb.werkbonnummer) || "-"}</strong></div>
          <div class="wb-info">👤 ${klant}</div>
          <div class="wb-info">📍 ${adres || "-"}</div>
          <div class="wb-info">🕒 ${start} – ${eind}</div>
          <div class="wb-info">🏷️ ${safe(wb.type_naam) || "-"}</div>
          <div class="wb-info">📌 Status: <span class="wb-status-planner">${plannerStatus}</span></div>
          <div class="wb-info">👷 Status (monteur): <span class="wb-status-monteur">${monteurStatus}</span></div>
        </div>

        <div class="wb-actions">
          <a href="/monteur/werkbon_detail.php?id=${werkbonId}" class="btn-small btn-blue">Details</a>
          <button class="wb-dots" type="button" aria-label="Menu" data-wbid="${werkbonId}">
            <i class="fa-solid fa-ellipsis-vertical"></i>
          </button>
        </div>
      </div>
    `;
  }

  // -----------------------
  // Load planning + cache items
  // -----------------------
  async function loadPlanning() {
    currentDate = startOfDay(currentDate);
    if (isWeekend(currentDate)) currentDate = shiftWorkday(currentDate, +1);
    saveDate(currentDate);

    const datumNL = toNL(currentDate);

    // ✅ één regel in de UI
    dateLabel.textContent = dateOneLine(currentDate);
    if (dateSub) dateSub.textContent = "";

    planningDiv.innerHTML = "<p>⏳ Laden...</p>";

    try {
      const url = `/monteur/ajax_mijn_planning.php?datum=${encodeURIComponent(
        datumNL
      )}&_ts=${Date.now()}`;

      const resp = await fetch(url, {
        cache: "no-store",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

      const payload = await resp.json();
      const data = Array.isArray(payload?.items)
        ? payload.items
        : Array.isArray(payload)
        ? payload
        : [];

      window.__ABCB_PLANNING_ITEMS = Array.isArray(data) ? data : [];

      if (!Array.isArray(data) || data.length === 0) {
        planningDiv.innerHTML = "<p class='empty-msg'>Geen werkbonnen.</p>";
        return;
      }

      planningDiv.innerHTML = `<div class="wb-list">${data.map(buildRow).join("")}</div>`;
    } catch (e) {
      console.error(e);
      planningDiv.innerHTML = "<p class='empty-msg'>⚠ Fout bij laden</p>";
    }
  }

  // -----------------------
  // Navigation (skip weekends)
  // -----------------------
  prevBtn.addEventListener("click", () => {
    currentDate = shiftWorkday(currentDate, -1);
    closeMenu();
    loadPlanning();
  });

  nextBtn.addEventListener("click", () => {
    currentDate = shiftWorkday(currentDate, +1);
    closeMenu();
    loadPlanning();
  });

  // -----------------------
  // Dots + menu actions (delegation)
  // -----------------------
  document.addEventListener("click", async (e) => {
    const dotsBtn = e.target.closest("button.wb-dots");
    const menuItem = e.target.closest(".wb-menu-item");

    // click buiten menu -> sluit
    if (!dotsBtn && !menuItem && openMenuEl) {
      closeMenu();
      return;
    }

    // open menu
    if (dotsBtn) {
      e.preventDefault();
      e.stopPropagation();

      const wbId = Number(dotsBtn.getAttribute("data-wbid") || "0");
      const wb = window.__ABCB_PLANNING_ITEMS?.find((x) => Number(x.werkbon_id) === wbId);
      if (!wb) return;

      // toggle gedrag
      if (openMenuEl && openAnchorBtn === dotsBtn) {
        closeMenu();
        return;
      }

      closeMenu();

      const wrapper = document.createElement("div");
      wrapper.innerHTML = buildMenuHtml(wb);
      const menu = wrapper.firstElementChild;

      menu.setAttribute("data-wbid", String(wbId));
      document.body.appendChild(menu);

      positionMenuFixed(menu, dotsBtn);

      openMenuEl = menu;
      openAnchorBtn = dotsBtn;
      return;
    }

    // menu action
    if (menuItem && openMenuEl) {
      e.preventDefault();

      const act = menuItem.getAttribute("data-act");
      const wbId = Number(openMenuEl.getAttribute("data-wbid") || "0");
      const wb = window.__ABCB_PLANNING_ITEMS?.find((x) => Number(x.werkbon_id) === wbId);
      if (!wb) {
        closeMenu();
        return;
      }

      if (act === "objecten") {
        window.location.href = `/monteur/werkbon_view.php?id=${wbId}#objecten`;
        return;
      }

      if (act === "bellen") {
        const tel = normalizePhone(wb.telefoon);
        if (tel) window.location.href = `tel:${tel}`;
        closeMenu();
        return;
      }

      if (act === "nav") {
        window.open(mapsUrlFromWb(wb), "_blank", "noopener");
        closeMenu();
        return;
      }

      if (act === "setstatus") {
        const newStatus = menuItem.getAttribute("data-status") || "";
        try {
          await setMonteurStatus(wbId, newStatus);

          // update cache + UI tekst
          wb.monteur_status = newStatus;
          wb.werkzaamheden_status = newStatus;

          const row = document.querySelector(`.wb-list-item[data-wbid="${wbId}"]`);
          const lbl = row?.querySelector(".wb-status-monteur");
          if (lbl) lbl.textContent = newStatus;

          closeMenu();
        } catch (err) {
          alert("Status wijzigen mislukt: " + (err?.message || "Onbekend"));
          closeMenu();
        }
      }
    }
  });

  // menu herpositioneren bij scroll/resize + ESC sluit
  window.addEventListener("scroll", () => {
    if (openMenuEl && openAnchorBtn) positionMenuFixed(openMenuEl, openAnchorBtn);
  }, { passive: true });

  window.addEventListener("resize", () => {
    if (openMenuEl && openAnchorBtn) positionMenuFixed(openMenuEl, openAnchorBtn);
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeMenu();
  });

  // -----------------------
  // Inject CSS (met !important waar nodig)
  // -----------------------
  const style = document.createElement("style");
  style.textContent = `
    .planning-nav{
      display:inline-flex !important;
      align-items:center !important;
      justify-content:center !important;
      gap:10px !important;
      margin:12px auto !important;
      width:auto !important;
    }
    .planning-nav .nav-btn{
      all:unset;
      cursor:pointer;
      font-size:15px;
      line-height:1;
      padding:2px 4px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
    }
    .planning-nav .nav-btn:hover{ color:#2954cc; }
    #dateLabel{ font-weight:700; white-space:nowrap; }

    .wb-list-item{ display:flex; justify-content:space-between; gap:12px; }
    .wb-actions{ display:flex; align-items:flex-start; gap:8px; }
    .wb-dots{
      border:1px solid rgba(0,0,0,0.12);
      background:#fff;
      border-radius:10px;
      padding:8px 10px;
      cursor:pointer;
    }
    .wb-dots i{ font-size:16px; }

    /* 🔥 menu altijd boven alles */
    .wb-menu{
      min-width:260px;
      background:#fff;
      border:1px solid rgba(0,0,0,0.12);
      border-radius:12px;
      box-shadow:0 12px 28px rgba(0,0,0,0.16);
      padding:8px;

      position:fixed !important;
      z-index:2147483647 !important;
    }
    .wb-menu-item{
      width:100%;
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px;
      border:none;
      background:none;
      text-align:left;
      cursor:pointer;
      border-radius:10px;
      font-size:14px;
    }
    .wb-menu-item:hover{ background:rgba(0,0,0,0.05); }
    .wb-menu-item:disabled{ opacity:.55; cursor:not-allowed; }
    .wb-menu-sep{ height:1px; background:rgba(0,0,0,0.10); margin:6px 0; }
    .wb-menu-title{ font-size:12px; font-weight:700; padding:6px 10px; opacity:.85; }
    .wb-menu-muted{ font-size:12px; color:#6b7280; margin-left:auto; }
    .wb-menu-mutedline{ font-size:12px; color:#6b7280; padding:0 10px 6px 10px; }
  `;
  document.head.appendChild(style);

  // eerste load
  loadPlanning();
});
