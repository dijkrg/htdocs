<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager (optioneel)
if (!isset($_SESSION['user'])) {
    setFlash("Log eerst in.", "error");
    header("Location: ../login.php");
    exit;
}

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
