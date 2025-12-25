<?php
require_once __DIR__ . '/../includes/init.php';
$basePath = dirname($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pageTitle ?? 'Mijn Planning') ?></title>
<link rel="stylesheet" href="<?= $basePath ?>/../template/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="monteur-body">
<main class="content">
    <?php showFlash(); ?>
    <?= $content ?? '' ?>
</main>
</body>
</html>
