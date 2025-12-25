document.addEventListener("DOMContentLoaded", () => {
    loadAgenda();
});

/* ============================================
   📌 Agenda Laden
============================================ */
function loadAgenda() {
    fetch("/monteur/api/fetch_monteur_events.php")
        .then(res => res.json())
        .then(data => {
            if (!data.ok || !data.events) {
                showAgendaError();
                return;
            }
            renderAgenda(data.events);
        })
        .catch(() => showAgendaError());
}

function showAgendaError() {
    document.getElementById("agendaList").innerHTML = `
        <div class="card error">Agenda kon niet geladen worden.</div>
    `;
}

/* ============================================
   📌 Agenda Groeperen per Datum
============================================ */
function renderAgenda(events) {
    const container = document.getElementById("agendaList");
    container.innerHTML = "";

    if (events.length === 0) {
        container.innerHTML = `<div class="card">Geen afspraken gevonden.</div>`;
        return;
    }

    // Groeperen per datum
    const grouped = {};
    events.forEach(ev => {
        if (!grouped[ev.date]) grouped[ev.date] = [];
        grouped[ev.date].push(ev);
    });

    // Sorteren op datum
    const sortedDates = Object.keys(grouped).sort();

    sortedDates.forEach(date => {
        const dateDisplay = formatDate(date);

        container.innerHTML += `
            <div class="agenda-date-title">${dateDisplay}</div>
        `;

        grouped[date].forEach(ev => {
            container.innerHTML += renderAgendaItem(ev);
        });
    });
}

/* ============================================
   📌 Eén agenda-item renderen
============================================ */
function renderAgendaItem(ev) {
    return `
        <div class="card agenda-item">

            <div class="agenda-top">
                <strong>${ev.title}</strong>
                <span>${ev.starttijd?.substring(0,5)} - ${ev.eindtijd?.substring(0,5)}</span>
            </div>

            <div class="agenda-body">
                <div class="agenda-klant">${ev.klantnaam || "Onbekende klant"}</div>
                <div class="agenda-omschrijving">${ev.omschrijving || ""}</div>
            </div>

            <div class="agenda-actions">
                <a class="btn-primary btn-small" 
                   href="/monteur/werkbon_view.php?id=${ev.id}">
                    Open werkbon
                </a>
            </div>

        </div>
    `;
}

/* ============================================
   📅 Datum formatteren naar NL-indeling
============================================ */
function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString("nl-NL", {
        weekday: "long",
        day: "2-digit",
        month: "long",
        year: "numeric"
    });
}
