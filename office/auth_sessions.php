<?php
require_once __DIR__ . '/includes/init.php';
checkRole(['Admin']); // Alleen Admin mag deze pagina gebruiken

// ✅ Token intrekken
if (isset($_POST['invalidate_id'])) {
    $id = intval($_POST['invalidate_id']);
    $stmt = $conn->prepare("
        UPDATE medewerkers 
        SET remember_token=NULL, remember_expires=NULL, remember_fingerprint=NULL 
        WHERE medewerker_id=?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    setFlash("Remember-me sessie ingetrokken voor gebruiker ID {$id}.", "success");
    header("Location: auth_sessions.php");
    exit;
}

// ✅ Alle actieve tokens ophalen
$result = $conn->query("
    SELECT medewerker_id, voornaam, achternaam, email, rol, remember_expires, remember_fingerprint 
    FROM medewerkers
    WHERE remember_token IS NOT NULL AND remember_expires > NOW()
    ORDER BY remember_expires DESC
");

$rows = $result->fetch_all(MYSQLI_ASSOC);

ob_start();
?>
<h2>Remember-me sessies beheren</h2>

<?php showFlash(); ?>

<?php if (empty($rows)): ?>
    <p>Er zijn momenteel geen actieve remember-me sessies.</p>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Geldig tot</th>
            <th>Fingerprint (hash)</th>
            <th>Actie</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['medewerker_id']) ?></td>
            <td><?= htmlspecialchars($r['voornaam'].' '.$r['achternaam']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['rol']) ?></td>
            <td><?= htmlspecialchars($r['remember_expires']) ?></td>
            <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars(substr($r['remember_fingerprint'], 0, 30)) ?>…</td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="invalidate_id" value="<?= $r['medewerker_id'] ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Token ongeldig maken voor deze gebruiker?')">❌ Intrekken</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.table th, .table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}
.table th {
    background: #f2f2f2;
}
.btn-danger {
    background: #d33;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
}
.btn-danger:hover {
    background: #a00;
}
</style>
<?php
$content = ob_get_clean();
$pageTitle = "Remember-me sessies";
include __DIR__ . "/template/template.php";
