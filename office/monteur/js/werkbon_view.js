/* =========================================================
   WERKBON VIEW — V2 OFFICIËLE EDITIE
   ABC Brand Beveiliging
========================================================= */

/* -------------------------
   Werkbon laden
------------------------- */
function loadWerkbonDetail(id) {
    document.getElementById("wbDetail").innerHTML =
        `<div class='loading'>⏳ Werkbon wordt geladen...</div>`;

    fetch(`/monteur/api/fetch_werkbon_detail.php?id=${id}`)
        .then(r => r.json())
        .then(data => renderWerkbon(data))
        .catch(() => {
            document.getElementById("wbDetail").innerHTML =
                "<div class='error-box'>❌ Werkbon kon niet geladen worden.</div>";
        });
}

/* -------------------------
   Hoofd render
------------------------- */
function renderWerkbon(wb) {
    if (wb.error) {
        document.getElementById("wbDetail").innerHTML =
            "<div class='error-box'>❌ Werkbon niet gevonden.</div>";
        return;
    }

    document.getElementById("wbDetail").innerHTML = `
        <h2 class="section-title">Werkbon ${wb.werkbonnummer}</h2>

        ${renderInfoCard(wb)}
        ${renderStatusCard(wb)}
        ${renderObjecten(wb)}
        ${renderArtikelen(wb)}
        ${renderUren(wb)}
    `;
}

/* =========================================================
   Info Card
========================================================= */
function renderInfoCard(wb) {
    return `
        <div class="card">
            <h3 class="card-title">Details</h3>
            <div class="info-row">Omschrijving:<span>${wb.omschrijving}</span></div>
            <div class="info-row">Datum:<span>${wb.uitvoerdatum}</span></div>
            <div class="info-row">Tijd:<span>${wb.starttijd} - ${wb.eindtijd}</span></div>
        </div>
    `;
}

/* =========================================================
   Status
========================================================= */
function renderStatusCard(wb) {
    return `
        <div class="card">
            <h3 class="card-title">Status</h3>

            <div class="status-buttons">
                ${statusBtn("onderweg", wb.status, wb.werkbon_id)}
                ${statusBtn("op_locatie", wb.status, wb.werkbon_id)}
                ${statusBtn("gereed", wb.status, wb.werkbon_id)}
            </div>

            <label class="toggle-switch">
                <input type="checkbox" id="toggleGereed" ${wb.werk_gereed ? "checked" : ""}>
                <span class="toggle-slider"></span>
            </label>
        </div>
    `;
}

function statusBtn(type, current, id) {
    const active = type === current ? "active" : "";
    const label = type.replace("_", " ");
    return `
        <button class="status-btn ${active}" onclick="changeStatus('${type}', ${id})">
            ${label}
        </button>
    `;
}

function changeStatus(status, id) {
    fetch(`/monteur/api/update_monteur_status.php`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${id}&status=${status}`
    })
        .then(r => r.json())
        .then(() => loadWerkbonDetail(id));
}

/* =========================================================
   Objecten
========================================================= */
function renderObjecten(wb) {
    if (wb.objecten.length === 0)
        return `<div class="card"><h3 class="card-title">Objecten</h3><p class="empty">Geen objecten gekoppeld.</p></div>`;

    return `
        <div class="card">
            <h3 class="card-title">Objecten</h3>
            <ul class="item-list">
                ${wb.objecten
                    .map(o => `
                        <li class="item-line">
                            <div><strong>${o.code}</strong><br>${o.omschrijving}</div>
                        </li>
                    `)
                    .join("")}
            </ul>
        </div>
    `;
}

/* =========================================================
   Artikelen
========================================================= */
function renderArtikelen(wb) {
    if (wb.artikelen.length === 0)
        return `<div class="card"><h3 class="card-title">Artikelen</h3><p class="empty">Geen artikelen.</p></div>`;

    return `
        <div class="card">
            <h3 class="card-title">Artikelen</h3>
            <ul class="item-list">
                ${wb.artikelen
                    .map(a => `
                        <li class="item-line">
                            <div>${a.aantal} × ${a.omschrijving}</div>
                        </li>
                    `)
                    .join("")}
            </ul>
        </div>
    `;
}

/* =========================================================
   Uren
========================================================= */
function renderUren(wb) {
    return `
        <div class="card">
            <h3 class="card-title">Uren</h3>

            <a href="/monteur/uur_toevoegen.php?werkbon_id=${wb.werkbon_id}"
               class="btn-primary btn-block">
                <i class="fa-solid fa-plus"></i> Uur toevoegen
            </a>

            <ul class="item-list">
                ${
                    wb.uren.length === 0
                        ? `<p class="empty">Geen uren geregistreerd.</p>`
                        : wb.uren
                              .map(
                                  u => `
                        <li class="item-line">
                            <div>
                                <strong>${u.datum}</strong><br>
                                ${u.begintijd} → ${u.eindtijd}
                            </div>
                        </li>
                    `
                              )
                              .join("")
                }
            </ul>
        </div>
    `;
}
