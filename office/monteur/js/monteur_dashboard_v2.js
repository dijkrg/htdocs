document.addEventListener("DOMContentLoaded", loadMonteurDashboard);

/* ================================
   1. MAIN LOADER
=================================== */
function loadMonteurDashboard() {
    fetch("/monteur/api/fetch_dashboard_stats.php")
        .then(r => r.json())
        .then(data => {
            buildDashboardCards(data);
            buildTodayList(data.todayList);
            buildTomorrowList(data.tomorrowList);
            buildWeekHours(data);
            buildTodayTomorrowWidget(data);
        })
        .catch(() => {
            document.getElementById("dashboardCards").innerHTML =
                "<div class='error-box'>Kon dashboard niet laden.</div>";
        });
}

/* ================================
   2. DASHBOARD KAARTEN
=================================== */
function buildDashboardCards(d) {
    const el = document.getElementById("dashboardCards");

    el.innerHTML = `
        <div class="dashboard-card">
            <i class="fa-solid fa-calendar-day"></i>
            <h3>${d.today}</h3>
            <p>Vandaag</p>
        </div>
        <div class="dashboard-card">
            <i class="fa-solid fa-calendar-week"></i>
            <h3>${d.week}</h3>
            <p>Deze week</p>
        </div>
        <div class="dashboard-card">
            <i class="fa-solid fa-business-time"></i>
            <h3>${d.hours}u</h3>
            <p>Gewerkte uren</p>
        </div>
        <div class="dashboard-card">
            <i class="fa-solid fa-calendar-plus"></i>
            <h3>${d.tomorrow}</h3>
            <p>Morgen</p>
        </div>
    `;
}

/* ================================
   3. TODAY LIST
=================================== */
function buildTodayList(list) {
    const box = document.getElementById("todayList");

    if (!list.length) {
        box.innerHTML = `<div class='no-items'>Geen werkbonnen</div>`;
        return;
    }

    box.innerHTML = list
        .map(w => `
            <div class="wb-item" onclick="openWB(${w.werkbon_id})">
                <strong>${w.werkbonnummer}</strong>
                <span>${w.omschrijving}</span>
                <span>${w.starttijd} - ${w.eindtijd}</span>
            </div>
        `)
        .join("");
}

/* ================================
   4. TOMORROW LIST
=================================== */
function buildTomorrowList(list) {
    const box = document.getElementById("tomorrowList");

    if (!list.length) {
        box.innerHTML = `<div class='no-items'>Geen werkbonnen</div>`;
        return;
    }

    box.innerHTML = list
        .map(w => `
            <div class="wb-item" onclick="openWB(${w.werkbon_id})">
                <strong>${w.werkbonnummer}</strong>
                <span>${w.omschrijving}</span>
                <span>${w.starttijd} - ${w.eindtijd}</span>
            </div>
        `)
        .join("");
}

/* ================================
   5. WEEK HOURS WIDGET
=================================== */
function buildWeekHours(d) {
    document.getElementById("dWorked").innerText = d.hours + "u";
    document.getElementById("dPlanned").innerText = d.planned_hours + "u";
    document.getElementById("dTotal").innerText = d.total_hours + "u";
}

/* ================================
   6. TODAY / TOMORROW MINI-WIDGET
=================================== */
function buildTodayTomorrowWidget(d) {
    const ttToday = document.getElementById("ttToday");
    const ttTomorrow = document.getElementById("ttTomorrow");

    if (!d.todayList.length) {
        ttToday.innerHTML = "<div class='no-items'>Geen werk vandaag.</div>";
    } else {
        ttToday.innerHTML = d.todayList
            .map(e => `
                <div class="tt-item">
                    <strong>${e.werkbonnummer}</strong>
                    ${e.omschrijving}<br>
                    ${e.starttijd} - ${e.eindtijd}
                </div>
            `)
            .join("");
    }

    if (!d.tomorrowList.length) {
        ttTomorrow.innerHTML = "<div class='no-items'>Geen werk morgen.</div>";
    } else {
        ttTomorrow.innerHTML = d.tomorrowList
            .map(e => `
                <div class="tt-item">
                    <strong>${e.werkbonnummer}</strong>
                    ${e.omschrijving}<br>
                    ${e.starttijd} - ${e.eindtijd}
                </div>
            `)
            .join("");
    }
}

/* ================================
   7. OPEN WERKBON
=================================== */
function openWB(id) {
    window.location.href = `/monteur/werkbon_view.php?id=${id}`;
}
