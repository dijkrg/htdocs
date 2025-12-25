<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$pageTitle = "Mijn Werkbonnen";
$monteur_id = (int)$_SESSION['user']['id'];

ob_start();
?>

<div class="page-section">

    <h2 class="section-title">Mijn Werkbonnen</h2>

    <div class="card wb-filter">
        <div class="wb-filter-grid">
            <button class="btn-secondary" data-filter="today">Vandaag</button>
            <button class="btn-secondary" data-filter="week">Deze week</button>
            <button class="btn-secondary" data-filter="all">Alles</button>
        </div>
    </div>

    <div id="werkbonList" class="werkbon-list"></div>

</div>

<script src="/monteur/js/monteur_werkbonnen.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
