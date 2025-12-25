<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
checkRole(['Admin']); // alleen Admin

$pageTitle = "Rollen overzicht";
$res = $conn->query("SELECT * FROM rollen ORDER BY naam ASC");
$rollen = $res->fetch_all(MYSQLI_ASSOC);

ob_start();
?>
<h2>Rollen</h2>
<a href="rol_toevoegen.php" class="btn">➕ Nieuwe rol</a>

<div class="card">
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>Acties</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rollen as $rol): ?>
        <tr>
            <td><?= $rol['rol_id'] ?></td>
            <td><?= htmlspecialchars($rol['naam']) ?></td>
            <td>
                <a href="rol_bewerk.php?id=<?= $rol['rol_id'] ?>">✏️</a>
                <a href="rol_verwijder.php?id=<?= $rol['rol_id'] ?>" onclick="return confirm('Rol verwijderen?')">🗑</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$content = ob_get_clean();
include '../template/template.php';
