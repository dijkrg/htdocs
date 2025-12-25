<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$res = $conn->query("
    SELECT wa.id, wa.aantal, a.omschrijving
    FROM werkbon_artikelen wa
    LEFT JOIN artikelen a ON a.artikel_id = wa.artikel_id
    WHERE wa.werkbon_id = $id
");

$list = [];
while ($r = $res->fetch_assoc()) $list[] = $r;

echo json_encode($list);
