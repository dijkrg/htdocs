<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$res = $conn->query("
    SELECT werkbon_uur_id, 
           DATE_FORMAT(datum,'%d-%m-%Y') AS datum,
           begintijd,
           eindtijd
    FROM werkbon_uren
    WHERE werkbon_id = $id
    ORDER BY datum, begintijd
");

$list = [];
while ($r = $res->fetch_assoc()) $list[] = $r;

echo json_encode($list);
