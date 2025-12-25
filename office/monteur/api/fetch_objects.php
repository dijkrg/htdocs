<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$res = $conn->query("
    SELECT o.object_id, o.code, o.omschrijving
    FROM werkbon_objecten wo
    JOIN objecten o ON o.object_id = wo.object_id
    WHERE wo.werkbon_id = $id
    ORDER BY o.code
");

$list = [];
while ($r = $res->fetch_assoc()) $list[] = $r;

echo json_encode($list);
