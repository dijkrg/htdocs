<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Inloggen - ABC Brand') ?></title>
    <link rel="stylesheet" href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/../template/style.css">
    <link rel="icon" type="image/png" href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/../template/ABCBFAV.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card">
        <img src="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/../template/ABCBFAV.png" alt="Logo" class="logo-login">
        <h2><?= htmlspecialchars($pageTitle ?? 'Inloggen') ?></h2>
        <?php showFlash(); ?>
        <?= $content ?? '' ?>
    </div>
</div>

</body>
</html>
