<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$monteur_naam = htmlspecialchars($_SESSION['user']['naam'] ?? ($_SESSION['user']['voornaam']." ".$_SESSION['user']['achternaam']));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Mijn Agenda</title>

<link rel="stylesheet" href="/template/style.css">
<link rel="icon" href="/template/favicon.ico">
<link rel="apple-touch-icon" href="/template/ABCBFAV.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- FullCalendar Vertical Resource View -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.10/index.global.min.js"></script>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body { background:#f3f4f6; margin:0; }

/* Topbar */
.monteur-header {
    background:#2954cc;
    padding:15px;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.monteur-header h2 { margin:0; }

/* Agenda container */
#calendar {
    max-width:100%;
    margin:10px;
}

.fc-event {
    color:white;
    font-size:13px;
    padding:4px;
    border-radius:4px;
    cursor:pointer;
}

.fc-timegrid-slot { height:45px !important; }

/* Mobiel */
@media (max-width:600px) {
    .fc-toolbar-title { font-size:16px !important; }
    .fc-timegrid-slot { height:38px !important; }
}
</style>
</head>

<body>

<div class="monteur-header">
    <h2>Agenda – <?= $monteur_naam ?></h2>
    <a class="logout-btn" href="/logout.php" style="color:white;">Uitloggen</a>
</div>

<div id="calendar"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'timeGridDay',
        locale: 'nl',
        slotMinTime: "06:00:00",
        slotMaxTime: "20:00:00",
        nowIndicator: true,
        allDaySlot: false,
        editable: true,
        droppable: false,
        selectable: true,

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },

        events: '/planboard/api/fetch_events.php',

        eventDidMount: function(info) {
            // Verberg alle events die niet bij deze monteur horen
            const mid = info.event.extendedProps.resourceId;
            if (mid != <?= $monteur_id ?>) {
                info.el.style.display = "none";
            }
        },

        eventClick: function(info) {
            const wbId = info.event.id;
            window.location.href = "/monteur/werkbon_view.php?id="+wbId;
        },

        eventDrop: function(info) {
            saveTime(info);
        },

        eventResize: function(info) {
            saveTime(info);
        }
    });

    calendar.render();

    // Tijd opslaan
    function saveTime(info) {
        const id = info.event.id;
        const start = info.event.start.toISOString().substr(11,5);
        const end = info.event.end ? info.event.end.toISOString().substr(11,5) : null;

        fetch("/monteur/api/update_monteur_status.php", {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:"id="+id+"&status=op_locatie&note=Automatische+tijd+update"
        });

        // Tijd doorgeven aan planner
        fetch("/planboard/api/update_event_time.php", {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                id:id,
                starttijd:start,
                eindtijd:end
            })
        });
    }

});
</script>

</body>
</html>
