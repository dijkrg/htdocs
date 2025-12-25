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

$id = (int)$_GET['id'];

// Check of leverancier nog artikelen heeft
$check = $conn->prepare("SELECT COUNT(*) AS aantal FROM artikelen WHERE leverancier_id = ?");
$check->bind_param("i", $id);
$check->execute();
$res = $check->get_result()->fetch_assoc();

if ($res['aantal'] > 0) {
    setFlash("Kan leverancier niet verwijderen: er zijn {$res['aantal']} artikelen gekoppeld.", "error");
    header("Location: leveranciers.php");
    exit;
}

// Verwijderen
$stmt = $conn->prepare("DELETE FROM leveranciers WHERE leverancier_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

setFlash("Leverancier succesvol verwijderd.", "success");
header("Location: leveranciers.php");
exit;

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
