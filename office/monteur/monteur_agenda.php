<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$pageTitle = "Agenda";

ob_start();
?>

<div class="page-section">

    <h2 class="section-title">Mijn Agenda</h2>

    <!-- WEEK SELECTOR -->
    <div class="card week-filter">
        <div class="week-filter-grid">
            <div>
                <label>Week</label>
                <input type="number" id="filterWeek" min="1" max="53" value="<?= date('W') ?>">
            </div>

            <div>
                <label>Jaar</label>
                <input type="number" id="filterJaar" value="<?= date('Y') ?>">
            </div>

            <button id="filterBtn" class="btn-primary">Toon</button>
        </div>
    </div>

    <!-- AGENDA RESULT -->
    <div id="agendaList" class="agenda-list"></div>

</div>

<script src="/monteur/js/monteur_agenda.js"></script>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
?>
