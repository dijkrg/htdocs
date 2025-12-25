<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    setFlash("Werkbon niet gevonden.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Controle of werkbon van deze monteur is
$stmt = $conn->prepare("SELECT werkbonnummer FROM werkbonnen WHERE werkbon_id=? AND monteur_id=?");
$stmt->bind_param("ii", $werkbon_id, $monteur_id);
$stmt->execute();
$wb = $stmt->get_result()->fetch_assoc();

if (!$wb) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/monteur_werkbon.php");
    exit;
}

// Objecten laden
$objecten = $conn->query("
    SELECT object_id, code, omschrijving
    FROM objecten
    ORDER BY code ASC
");

// Verwerking formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $object_id = (int)$_POST['object_id'];

    if ($object_id <= 0) {
        setFlash("Geen object geselecteerd.", "error");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO werkbon_objecten (werkbon_id, object_id)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $werkbon_id, $object_id);

        if ($stmt->execute()) {
            setFlash("Object toegevoegd.", "success");
            header("Location: /monteur/werkbon_view.php?id={$werkbon_id}");
            exit;
        } else {
            setFlash("Fout bij opslaan.", "error");
        }
    }
}

$pageTitle = "Object toevoegen";
ob_start();
?>

<a href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>" class="back-btn">⬅ Terug</a>

<h2 class="page-title">Object toevoegen</h2>

<div class="wb-section">

    <form method="post" class="form-grid">

        <div class="full">
            <label>Object *</label>
            <select name="object_id" required>
                <option value="">-- Kies een object --</option>

                <?php while($o = $objecten->fetch_assoc()): ?>
                    <option value="<?= $o['object_id'] ?>">
                        <?= htmlspecialchars($o['code']) ?> — <?= htmlspecialchars($o['omschrijving']) ?>
                    </option>
                <?php endwhile; ?>

            </select>
        </div>

        <div class="full">
            <button class="btn-primary">Toevoegen</button>
        </div>

    </form>

</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . "/template/monteur_template.php";
