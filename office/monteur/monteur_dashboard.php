<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id   = (int)$_SESSION['user']['id'];
$monteur_naam = htmlspecialchars(
    $_SESSION['user']['naam']
    ?? ($_SESSION['user']['voornaam'] . " " . $_SESSION['user']['achternaam'])
);

$pageTitle = "Dashboard";

// OUTPUT BUFFER STARTEN → alles in template plaatsen
ob_start();
?>

<h2>Welkom <?= $monteur_naam ?></h2>

<div id="calendar"></div>

<!-- FullCalendar CSS + JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<!-- MONTEUR DASHBOARD CSS -->
<link rel="stylesheet" href="/monteur/css/monteur_dashboard.css">

<script src="/monteur/js/monteur_dashboard.js"></script>

<?php
// TEMPLATE INLADEN
$content = ob_get_clean();
include __DIR__ . "/template/monteur_template.php";
?>
