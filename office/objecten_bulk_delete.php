<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash('Geen toegang.', 'error');
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $in  = implode(',', $ids);

    if ($in) {
        $sql = "DELETE FROM objecten WHERE object_id IN ($in)";
        $conn->query($sql);
        $aantal = $conn->affected_rows;
        setFlash("🗑 {$aantal} object(en) verwijderd.", "success");
    } else {
        setFlash("Geen geldige selectie.", "error");
    }
} else {
    setFlash("Geen objecten geselecteerd.", "error");
}

header('Location: objecten.php');
exit;
