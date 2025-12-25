document.addEventListener("DOMContentLoaded", () => {
    loadUren();

    document.getElementById("filterBtn").addEventListener("click", loadUren);
});

/* ---------------------------
   🟦 API laden
---------------------------- */
function loadUren() {
    const w = document.getElementById("filterWeek").value;
    const j = document.getElementById("filterJaar").value;

    fetch(`/monteur/api/fetch_uren_week.php?week=${w}&jaar=${j}`)
        .then(r => r.json())
        .then(data => {
            renderSummary(data);
            renderUren(data.uren);
        })
        .catch(err => {
            document.getElementById("urenList").innerHTML =
                "<div class='error-box'>Kan uren niet laden.</div>";
        });
}

/* ---------------------------
   🟦 Week-info en totaal
---------------------------- */
function renderSummary(data) {
    document.getElementById("weekSummary").innerHTML = `
        <h3 class="card-title">Week ${data.week}</h3>
        <p>${data.week_start} t/m ${data.week_end}</p>
        <p><strong>Totaal: ${data.total} uur</strong></p>
    `;
}

/* ---------------------------
   🟦 Uren lijst renderen
---------------------------- */
function renderUren(list) {
    const box = document.getElementById("urenList");

    if (!list.length) {
        box.innerHTML = `<div class="empty">Geen uren deze week.</div>`;
        return;
    }

    box.innerHTML = list
        .map(u => `
            <div class="uren-card">

                <div class="uren-header">
                    <strong>${u.datum}</strong>
                    <span>${u.code} – ${u.omschrijving}</span>
                </div>

                <div class="uren-times">
                    ${u.starttijd.substring(0,5)} → ${u.eindtijd.substring(0,5)}
                    <span class="duur">${formatDuur(u.duur_minuten)}</span>
                </div>

                <div class="uren-actions">
                    <a href="/monteur/uur_bewerken.php?id=${u.uur_id}" class="btn-icon">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="/monteur/uur_verwijderen.php?id=${u.uur_id}"
                       onclick="return confirm('Uur verwijderen?')"
                       class="btn-icon text-danger">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </div>
            </div>
        `)
        .join("");
}

/* Format duur (minuten → 1u 30m) */
function formatDuur(min) {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${h}u ${m}m`;
}
