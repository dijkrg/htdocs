<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();

// 📅 Datum periodes
$weekStart      = date("Y-m-d", strtotime("monday this week"));
$weekEnd        = date("Y-m-d", strtotime("sunday this week"));

$monthStart     = date("Y-m-01");
$monthEnd       = date("Y-m-t");

// 📅 Vorig kwartaal berekenen
$currQuarter = ceil(date('n') / 3);
$prevQuarter = $currQuarter - 1;
$year = date('Y');

if ($prevQuarter === 0) {
    $prevQuarter = 4;
    $year -= 1;
}

$quarterStart = date('Y-m-d', strtotime("$year-" . (($prevQuarter - 1) * 3 + 1) . "-01"));
$quarterEnd   = date('Y-m-t', strtotime("+2 months", strtotime($quarterStart)));

// 📅 Totaal dit jaar
$yearStart = date("Y-01-01");
$yearEnd   = date("Y-m-d");

// 📊 Functie
function countWerkbonnen($conn, $from, $to) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS totaal FROM werkbonnen WHERE uitvoerdatum BETWEEN ? AND ?");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['totaal'] ?? 0;
}

// 📈 Data ophalen
$thisWeek   = countWerkbonnen($conn, $weekStart, $weekEnd);
$thisMonth  = countWerkbonnen($conn, $monthStart, $monthEnd);
$prevQuarterCount = countWerkbonnen($conn, $quarterStart, $quarterEnd);
$thisYearCount    = countWerkbonnen($conn, $yearStart, $yearEnd);

// 📊 Status teller
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
<?php showFlash(); ?>

<div class="dashboard-grid">
    <div class="dashboard-card card-blue"><div class="icon">📅</div><h3>Deze week</h3><p><?= $thisWeek ?> werkbonnen</p></div>
    <div class="dashboard-card card-green"><div class="icon">🗓️</div><h3>Deze maand</h3><p><?= $thisMonth ?> werkbonnen</p></div>
    <div class="dashboard-card card-orange"><div class="icon">📉</div><h3>Vorig kwartaal</h3><p><?= $prevQuarterCount ?> werkbonnen</p></div>
    <div class="dashboard-card card-gray"><div class="icon">📊</div><h3>Totaal dit jaar</h3><p><?= $thisYearCount ?> werkbonnen</p></div>
</div>

<div class="dashboard-two-cols">
    <div class="dashboard-card card-white">
        <h3>Werkbonnen per status</h3>
        <div class="status-chart-container"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="dashboard-card card-white">
        <h3>Nog te doen</h3>
        <p style="text-align:center; color:#777;">(In ontwikkeling)</p>
    </div>
</div>

<style>
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-top:20px;}
.dashboard-two-cols{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;}
@media(max-width:800px){.dashboard-two-cols{grid-template-columns:1fr;}}
.dashboard-card{border-radius:12px;padding:20px;text-align:center;color:#fff;box-shadow:0 3px 6px rgba(0,0,0,.15);transition:transform .2s;}
.dashboard-card:hover{transform:translateY(-5px);}
.dashboard-card .icon{font-size:32px;margin-bottom:10px;}
.card-blue{background:#2954cc}
.card-green{background:#28a745}
.card-orange{background:#ff8a2b}
.card-gray{background:#787678}
.card-white{background:#fff;color:#333;text-align:left}
.status-chart-container{width:100%;height:280px;margin-top:10px;}
.status-chart-container canvas{width:100%!important;height:100%!important;}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded",function(){
    const ctx=document.getElementById('statusChart').getContext('2d');
    new Chart(ctx,{
        type:'bar',
        data:{
            labels:<?=json_encode(array_keys($statusCounts))?>,
            datasets:[{
                label:'Aantal',
                data:<?=json_encode(array_values($statusCounts))?>,
                backgroundColor:['#2954cc','#8feefc','#ff8a2b','#787678']
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{
                y:{beginAtZero:true},
                x:{ticks:{font:{size:12}}}
            }
        }
    });
});
</script>
<?php
$content = ob_get_clean();
$pageTitle = "Dashboard";
include __DIR__ . "/template/template.php";
?>
