<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/init.php';

// Alleen Admin of Manager
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang tot medewerkers.", "error");
    header("Location: index.php");
    exit;
}

$pageTitle = "Medewerkers overzicht";

// Alle medewerkers ophalen
$result = $conn->query("SELECT * FROM medewerkers ORDER BY achternaam ASC");

ob_start();
?>
<div class="page-header">
    <h2>Medewerkers overzicht</h2>
    <a href="medewerker_toevoegen.php" class="btn">➕ Nieuwe medewerker</a>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Personeelsnummer</th>
                <th>Naam</th>
                <th>Email</th>
                <th>Telefoon</th>
                <th>Rol</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($medewerker = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($medewerker['personeelsnummer']) ?></td>
                <td><?= htmlspecialchars($medewerker['voornaam'] . ' ' . $medewerker['achternaam']) ?></td>
                <td><?= htmlspecialchars($medewerker['email']) ?></td>
                <td><?= htmlspecialchars($medewerker['telefoon']) ?></td>
                <td><?= htmlspecialchars($medewerker['rol']) ?></td>
                <td>
                    <a href="medewerker_bewerk.php?id=<?= $medewerker['medewerker_id'] ?>">✏️</a>
                    <a href="medewerker_verwijder.php?id=<?= $medewerker['medewerker_id'] ?>" 
                       onclick="return confirm('Weet je zeker dat je deze medewerker wilt verwijderen?')">🗑</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . "/template/template.php";
