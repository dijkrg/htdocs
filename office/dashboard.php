<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// 📅 Huidige datum info
$weekStart = date("Y-m-d", strtotime("monday this week"));
$weekEnd   = date("Y-m-d", strtotime("sunday this week"));
$lastWeekStart = date("Y-m-d", strtotime("monday last week"));
$lastWeekEnd   = date("Y-m-d", strtotime("sunday last week"));
$monthStart = date("Y-m-01");
$monthEnd   = date("Y-m-t");
$lastMonthStart = date("Y-m-01", strtotime("first day of last month"));
$lastMonthEnd   = date("Y-m-t", strtotime("last day of last month"));

// 📊 Functie om werkbonnen te tellen
function countWerkbonnen($conn, $from, $to) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS totaal FROM werkbonnen WHERE uitvoerdatum BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['totaal'] ?? 0;
}

$thisWeek   = countWerkbonnen($conn, $weekStart, $weekEnd);
$thisMonth  = countWerkbonnen($conn, $monthStart, $monthEnd);
$lastWeek   = countWerkbonnen($conn, $lastWeekStart, $lastWeekEnd);
$lastMonth  = countWerkbonnen($conn, $lastMonthStart, $lastMonthEnd);

// 📊 Status grafiek data
$statuses = ["Ingepland", "Klaargezet", "Compleet", "Afgehandeld"];
$statusCounts = [];
foreach ($statuses as $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS totaal FROM werkbonnen WHERE status=?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $statusCounts[$status] = $res['totaal'] ?? 0;
}

ob_start();
?>
<h2>Dashboard</h2>

<!-- 📌 Bovenste rij: KPI Tegels -->
<div class="dashboard-grid">
    <div class="dashboard-card card-blue">
        <div class="icon">📅</div>
        <h3>Deze week</h3>
        <p><?= $thisWeek ?> werkbonnen</p>
    </div>
    <div class="dashboard-card card-green">
        <div class="icon">🗓️</div>
        <h3>Deze maand</h3>
        <p><?= $thisMonth ?> werkbonnen</p>
    </div>
    <div class="dashboard-card card-orange">
        <div class="icon">🔙</div>
        <h3>Vorige week</h3>
        <p><?= $lastWeek ?> werkbonnen</p>
    </div>
    <div class="dashboard-card card-gray">
        <div class="icon">⏪</div>
        <h3>Vorige maand</h3>
        <p><?= $lastMonth ?> werkbonnen</p>
    </div>
</div>

<!-- 📌 Tweede rij: Grafiek + Taken -->
<div class="dashboard-grid secondary-row">
    <!-- 📊 Grafiek tegel -->
    <div class="chart-tile card">
        <h3>Werkbonnen per status</h3>
        <div class="chart-container">
            <canvas id="statusChart" width="200" height="100"></canvas>
        </div>
    </div>

    <!-- 📝 Taken / Nog te doen -->
    <div class="todo-tile card">
        <h3>Nog te doen</h3>
        <ul class="todo-list">
            <li>📌 Controleer werkbon #1023</li>
            <li>🧾 Factuur klant Jansen</li>
            <li>🚨 Herplanning onderhoud locatie X</li>
            <li>✍️ Rapport brandoefening aanvullen</li>
        </ul>
        <a href="#" class="btn btn-secondary" style="margin-top:10px;">Naar taken</a>
    </div>
</div>

<style>
/* 📌 KPI Tegels */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.dashboard-card {
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    color: #fff;
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
    transition: transform 0.2s ease;
}
.dashboard-card:hover {
    transform: translateY(-5px);
}
.dashboard-card .icon {
    font-size: 32px;
    margin-bottom: 10px;
}
.card-blue { background: #2954cc; }
.card-green { background: #28a745; }
.card-orange { background: #ff8a2b; }
.card-gray { background: #787678; }

/* 📊 Tweede rij */
.secondary-row {
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    margin-top: 30px;
}

/* 📊 Chart tegel */
.chart-tile {
    text-align: center;
}
.chart-tile h3 {
    font-size: 14px;
    margin-bottom: 10px;
}
.chart-container {
    width: 200px;
    height: 100px;
    margin: 0 auto;
}
.chart-container canvas {
    width: 200px !important;
    height: 100px !important;
    display: block;
}

/* 📝 Taken tegel */
.todo-tile h3 {
    font-size: 14px;
    margin-bottom: 10px;
}
.todo-list {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
    font-size: 14px;
}
.todo-list li {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}
.todo-list li:last-child {
    border-bottom: none;
}
</style>

<!-- ✅ Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js?ver=<?= time() ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    Chart.defaults.devicePixelRatio = 1;

    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($statusCounts)) ?>,
            datasets: [{
                label: 'Aantal',
                data: <?= json_encode(array_values($statusCounts)) ?>,
                backgroundColor: ['#2954cc','#8feefc','#ff8a2b','#787678']
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 9 } } },
                x: { ticks: { font: { size: 9 } } }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Dashboard";
include __DIR__ . "/template/template.php";
