<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flash.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE werkbonnen SET gearchiveerd = 0 WHERE werkbon_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        setFlash("✅ Werkbon succesvol teruggezet naar overzicht.", "success");
    } else {
        setFlash("❌ Fout bij terugzetten werkbon: " . $stmt->error, "error");
    }
    $stmt->close();
}

header("Location: werkbonnen_archief.php");
exit;
