<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

header("Location: /monteur/mijn_planning.php", true, 302);
exit;
