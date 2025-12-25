<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
requireRole(['Admin','Manager']);

// Content
ob_start();
?>
<h2>Dashboard</h2>
<p>Welkom bij het dashboard. Hier kun je later overzichten en statistieken tonen.</p>
<?php
$content = ob_get_clean();
$pageTitle = "Dashboard";
include __DIR__ . "/template/template.php";
