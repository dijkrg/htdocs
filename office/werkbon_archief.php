<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE werkbonnen SET gearchiveerd=1 WHERE werkbon_id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash("📦 Werkbon is naar het archief verplaatst.", "success");
    } else {
        setFlash("❌ Fout bij archiveren: " . $stmt->error, "error");
    }
    $stmt->close();
}

header("Location: werkbon_detail.php?id=" . $id);
exit;
