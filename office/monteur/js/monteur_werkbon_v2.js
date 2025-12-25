document.addEventListener("DOMContentLoaded", () => {
    loadWerkbonnen();

    // Zoekfunctie
    document.getElementById("wbSearch").addEventListener("input", filterWerkbonnen);
});

let werkbonData = [];

/* -----------------------------
   📦 Werkbonnen laden via API
------------------------------ */
function loadWerkbonnen() {
    fetch("/monteur/api/fetch_monteur_workorders.php")
        .then(r => r.json())
        .then(data => {
            werkbonData = data;
            renderWerkbonnen(data);
        });
}

/* -----------------------------
   📋 Lijst renderen
------------------------------ */
function renderWerkbonnen(list) {
    const box = document.getElementById("werkbonList");

    if (list.length === 0) {
        box.innerHTML = `<div class="no-items">Geen werkbonnen gevonden.</div>`;
        return;
    }

    box.innerHTML = list
        .map(wb => `
            <div class="wb-item" onclick="openWB(${wb.werkbon_id})">

                <div class="wb-title">${wb.werkbonnummer}</div>
                <div class="wb-sub">${wb.omschrijving}</div>

                <div class="wb-meta">
                    <span>${wb.uitvoerdatum}</span>
                    <span>${wb.starttijd} - ${wb.eindtijd}</span>
                    <span class="status-bdg status-${wb.status}">
                        ${wb.status.replace('_',' ')}
                    </span>
                </div>

            </div>
        `)
        .join("");
}

/* -----------------------------
   🔍 Zoeken in lijst
------------------------------ */
function filterWerkbonnen() {
    const q = document.getElementById("wbSearch").value.toLowerCase();

    const filtered = werkbonData.filter(w =>
        w.werkbonnummer.toLowerCase().includes(q) ||
        w.omschrijving.toLowerCase().includes(q)
    );

    renderWerkbonnen(filtered);
}

function openWB(id) {
    window.location.href = "/monteur/werkbon_view.php?id=" + id;
}
