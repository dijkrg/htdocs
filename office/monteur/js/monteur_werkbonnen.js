document.addEventListener("DOMContentLoaded", () => {
    loadWerkbonnen("today");
    bindFilters();
});

/* ===========================
   FILTER BUTTONS
=========================== */
function bindFilters() {
    document.querySelectorAll(".wb-filter button").forEach(btn => {
        btn.addEventListener("click", () => {
            document.querySelectorAll(".wb-filter button")
                .forEach(b => b.classList.remove("active"));

            btn.classList.add("active");

            const f = btn.dataset.filter;
            loadWerkbonnen(f);
        });
    });
}

/* ===========================
   LOAD WERKBON DATA
=========================== */
function loadWerkbonnen(filter) {
    const list = document.getElementById("werkbonList");

    list.innerHTML = createSkeleton(4); // loading skeleton

    fetch(`/monteur/api/fetch_monteur_werkbonnen.php?filter=${filter}`)
        .then(res => res.json())
        .then(data => renderWerkbonnen(data))
        .catch(() => {
            list.innerHTML = `
                <div class="card error-card">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Fout bij laden van werkbonnen.
                </div>
            `;
        });
}

/* ===========================
   RENDER WERKBON KAARTEN
=========================== */
function renderWerkbonnen(list) {
    const box = document.getElementById("werkbonList");
    box.innerHTML = "";

    if (!list.length) {
        box.innerHTML = `
            <div class="card empty-card">
                <i class="fa-solid fa-circle-info"></i>
                Geen werkbonnen gevonden.
            </div>
        `;
        return;
    }

    list.forEach(wb => {
        const el = document.createElement("div");
        el.className = "card werkbon-item";
        el.onclick = () => openWB(wb.werkbon_id);

        const statusBadge = renderStatus(wb.status);

        el.innerHTML = `
            <div class="wb-top">
                <strong>Werkbon ${wb.werkbonnummer}</strong>
                <span class="wb-date">
                    <i class="fa-solid fa-calendar-day"></i> 
                    ${formatDate(wb.uitvoerdatum)}
                </span>
            </div>

            <div class="wb-body">
                <div class="wb-title">${wb.omschrijving}</div>
                <div class="wb-sub">
                    <i class="fa-solid fa-building"></i> ${wb.klantnaam}
                </div>
                <div class="wb-time">
                    <i class="fa-solid fa-clock"></i>
                    ${wb.starttijd} — ${wb.eindtijd}
                </div>
            </div>

            <div class="wb-status">
                ${statusBadge}
            </div>
        `;

        box.appendChild(el);
    });
}

/* ===========================
   STATUS BADGES
=========================== */
function renderStatus(status) {
    if (!status) return "";

    const map = {
        "onderweg": { color: "blue", label: "Onderweg" },
        "op_locatie": { color: "green", label: "Op locatie" },
        "gereed": { color: "orange", label: "Gereed" }
    };

    const s = map[status] || { color: "grey", label: status };

    return `
        <span class="status-badge ${s.color}">
            ${s.label}
        </span>
    `;
}

/* ===========================
   OPEN WERKBON-PAGINA
=========================== */
function openWB(id) {
    window.location.href = `/monteur/werkbon_view.php?id=${id}`;
}

/* ===========================
   LOADING SKELETON
=========================== */
function createSkeleton(count = 3) {
    let html = "";
    for (let i = 0; i < count; i++) {
        html += `
            <div class="card skeleton-card">
                <div class="sk-line w50"></div>
                <div class="sk-line w80"></div>
                <div class="sk-line w40"></div>
            </div>
        `;
    }
    return html;
}

/* ===========================
   FORMAT DATUM -> DD-MM-YYYY
=========================== */
function formatDate(date) {
    const d = new Date(date);
    return d.toLocaleDateString("nl-NL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    });
}
