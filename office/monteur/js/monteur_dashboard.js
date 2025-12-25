document.addEventListener("DOMContentLoaded", () => {

    const monteurId = window.MONTEUR_ID ?? null;
    if (!monteurId) {
        console.error("MONTEUR_ID ontbreekt in de pagina.");
        return;
    }

    loadDashboard();
    loadToday();
    loadTomorrow();

});

/* -------------------------------
   DASHBOARD-CARDS INLADEN
-------------------------------- */
function loadDashboard() {
    fetch("/monteur/api/fetch_monteur_events.php?mode=stats")
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById("dashboard-cards");
            if (!box) return;

            box.innerHTML = `
                <div class="dash-card">
                    <div class="dash-value">${data.today ?? 0}</div>
                    <div class="dash-label">Werkbonnen vandaag</div>
                </div>
                <div class="dash-card">
                    <div class="dash-value">${data.tomorrow ?? 0}</div>
                    <div class="dash-label">Werkbonnen morgen</div>
                </div>
                <div class="dash-card">
                    <div class="dash-value">${data.open ?? 0}</div>
                    <div class="dash-label">Openstaande werkbonnen</div>
                </div>
            `;
        });
}

/* -------------------------------
   LIJST — VANDAAG
-------------------------------- */
function loadToday() {
    fetch("/monteur/api/fetch_monteur_events.php?mode=today")
        .then(r => r.json())
        .then(data => renderList("today-list", data));
}

/* -------------------------------
   LIJST — MORGEN
-------------------------------- */
function loadTomorrow() {
    fetch("/monteur/api/fetch_monteur_events.php?mode=tomorrow")
        .then(r => r.json())
        .then(data => renderList("tomorrow-list", data));
}

/* -------------------------------
   GENERIC RENDER FUNCTIE
-------------------------------- */
function renderList(targetId, items) {
    const box = document.getElementById(targetId);
    if (!box) return;

    if (items.length === 0) {
        box.innerHTML = `<p style="color:#888;">Geen werkbonnen</p>`;
        return;
    }

    let html = "";
    items.forEach(wb => {
        html += `
            <div class="wb-item" onclick="openWerkbon(${wb.id})">
                <div class="wb-line1">
                    <strong>${wb.werkbonnummer}</strong>
                    <span class="wb-status ${getStatusClass(wb.status, wb.monteur_status)}">
                        ${wb.monteur_status ? wb.monteur_status.replace('_',' ') : wb.status}
                    </span>
                </div>

                <div class="wb-line2">${wb.klant}</div>
                <div class="wb-line3">${wb.werkadres}</div>
                <div class="wb-line4">${wb.tijd ?? ''}</div>
            </div>
        `;
    });

    box.innerHTML = html;
}

/* -------------------------------
   STATUS KLEUR
-------------------------------- */
function getStatusClass(status, mstatus) {
    if (mstatus === "onderweg") return "st-blue";
    if (mstatus === "op_locatie") return "st-green";
    if (mstatus === "gereed") return "st-purple";

    switch (status) {
        case "Klaargezet": return "st-darkblue";
        case "Ingepland": return "st-blue";
        case "Compleet": return "st-gray";
        case "Afgehandeld": return "st-green";
        default: return "st-default";
    }
}

/* -------------------------------
   OPEN PAGINA
-------------------------------- */
function openWerkbon(id) {
    window.location.href = "/monteur/werkbon_detail.php?id=" + id;
}
