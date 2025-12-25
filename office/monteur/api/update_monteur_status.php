<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$wb = (int)($_POST['werkbon_id'] ?? 0);
$state = $_POST['status'] ?? '';

if (!$wb || !$state) {
    echo json_encode(['ok'=>false,'msg'=>'Ongeldige aanvraag.']);
    exit;
}

$stmt = $conn->prepare("UPDATE werkbonnen SET status=? WHERE werkbon_id=?");
$stmt->bind_param("si", $state, $wb);
$stmt->execute();

echo json_encode(['ok'=>true,'msg'=>'Status bijgewerkt naar: '.$state]);
