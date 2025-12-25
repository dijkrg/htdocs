<?php
require __DIR__ . '/db.php';

// Bepaal omgeving
$host = $_SERVER['HTTP_HOST'] ?? 'cli';
if ($host === 'localhost' || $host === '127.0.0.1') {
    $omgeving = "🌍 Verbonden met: <strong>LOKALE database (XAMPP)</strong>";
} else {
    $omgeving = "☁️ Verbonden met: <strong>ONLINE database (server)</strong>";
}

// Query voor laatste 5 artikelen
$artikelen = [];
try {
    $res = $conn->query("SELECT artikelnummer, omschrijving, prijs, categorie 
                         FROM artikelen ORDER BY artikel_id DESC LIMIT 5");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $artikelen[] = $row;
        }
    }
} catch (Throwable $e) {
    $artikelen = [];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Excel Import Test</title>
<style>
    body { font-family: Arial, sans-serif; background:#f5f5f5; padding:40px; }
    h1 { margin-bottom:10px; }
    p.env { font-size:14px; color:#555; margin-bottom:30px; }
    .btn {
        display:inline-block;
        padding:12px 20px;
        margin:10px;
        font-size:16px;
        text-decoration:none;
        background:#0073e6;
        color:#fff;
        border-radius:6px;
        transition: background 0.2s;
    }
    .btn:hover { background:#005bb5; }
    .container { max-width:800px; margin:auto; text-align:center; }
    table { border-collapse:collapse; margin:20px auto; background:#fff; }
    th, td { padding:8px 12px; border:1px solid #ddd; }
    th { background:#eee; }
</style>
</head>
<body>
<div class="container">
    <h1>🔽 Excel Import Test</h1>
    <p class="env"><?php echo $omgeving; ?></p>
    <p>Kies welk type gegevens je wilt importeren:</p>
    <a href="test_import_klanten.php" class="btn">👤 Klanten importeren</a>
    <a href="test_import_artikelen.php" class="btn">📦 Artikelen importeren</a>
    <a href="test_import_objecten.php" class="btn">🏢 Objecten importeren</a>

    <h2>📦 Laatste 5 artikelen</h2>
    <?php if ($artikelen): ?>
        <table>
            <tr>
                <th>Artikelnummer</th>
                <th>Omschrijving</th>
                <th>Prijs</th>
                <th>Categorie</th>
            </tr>
            <?php foreach ($artikelen as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($a['omschrijving']) ?></td>
                <td>€ <?= htmlspecialchars(number_format($a['prijs'], 2, ',', '.')) ?></td>
                <td><?= htmlspecialchars($a['categorie']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p><em>Geen artikelen gevonden.</em></p>
    <?php endif; ?>
</div>
</body>
</html>
