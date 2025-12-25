<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/init.php';

// Alleen Admin
if (empty($_SESSION['user']) || $_SESSION['user']['rol'] !== 'Admin') {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

// AJAX: naam wijzigen
if (isset($_POST['action']) && $_POST['action'] === 'saveName') {
    $id = intval($_POST['id']);
    $naam = trim($_POST['naam']);

    $stmt = $conn->prepare("UPDATE contract_onderdeel_types SET naam=? WHERE type_id=?");
    $stmt->bind_param("si", $naam, $id);
    $stmt->execute();
    exit("OK");
}

// AJAX: actief toggle
if (isset($_POST['action']) && $_POST['action'] === 'toggleActive') {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE contract_onderdeel_types SET actief = NOT actief WHERE type_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    exit("OK");
}

// AJAX: sort-order updaten
if (isset($_POST['action']) && $_POST['action'] === 'saveSort') {
    $order = $_POST['order'] ?? [];
    foreach ($order as $pos => $id) {
        $id = intval($id);
        $pos = intval($pos + 1);
        $stmt = $conn->prepare("UPDATE contract_onderdeel_types SET sort_order=? WHERE type_id=?");
        $stmt->bind_param("ii", $pos, $id);
        $stmt->execute();
    }
    exit("OK");
}

// Toevoegen onderdeel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nieuw_onderdeel'])) {
    $naam = trim($_POST['naam']);

    if ($naam === '') {
        setFlash("Naam is verplicht.", "error");
    } else {
        $stmt = $conn->prepare("INSERT INTO contract_onderdeel_types (naam, actief, sort_order) VALUES (?, 1, 999)");
        $stmt->bind_param("s", $naam);
        $stmt->execute();
        setFlash("Onderdeel toegevoegd.", "success");
    }

    header("Location: contract_onderdeel_types.php");
    exit;
}

// Ophalen onderdelen
$result = $conn->query("
    SELECT * FROM contract_onderdeel_types
    ORDER BY sort_order ASC, naam ASC
");

$pageTitle = "Contract onderdelen beheer";
ob_start();
?>

<div class="page-header">
    <h2>⚙️ Contract onderdelen beheren</h2>
</div>

<div class="card">
    <h3>Nieuw onderdeel toevoegen</h3>

    <form method="post" style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="naam" placeholder="Naam onderdeel..." style="flex:2;">
        <button type="submit" name="nieuw_onderdeel" class="btn">➕ Toevoegen</button>
    </form>
</div>

<div class="card" style="margin-top:20px;">
    <h3>Onderhoudsonderdelen</h3>

    <ul id="onderdelenList" style="list-style:none;padding:0;margin:0;">
        <?php while ($r = $result->fetch_assoc()): ?>
            <li class="onderdeel-row" data-id="<?= $r['type_id'] ?>"
                style="padding:10px;border:1px solid #ddd;border-radius:6px;margin-bottom:6px;
                       display:flex;justify-content:space-between;align-items:center;background:#fafafa;cursor:grab;">

                <div style="display:flex;gap:10px;align-items:center;flex:1;">

                    <span class="drag-handle" style="cursor:grab;">☰</span>

                    <input type="text" class="inline-edit"
                           data-id="<?= $r['type_id'] ?>"
                           value="<?= htmlspecialchars($r['naam']) ?>"
                           style="flex:1;padding:6px;">
                </div>

                <button class="btn toggleActiveBtn"
                        data-id="<?= $r['type_id'] ?>"
                        style="min-width:120px;background:<?= $r['actief'] ? '#2e7d32' : '#c62828' ?>;">
                    <?= $r['actief'] ? "Actief" : "Inactief" ?>
                </button>

            </li>
        <?php endwhile; ?>
    </ul>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>

<script>
// ===== Inline naam opslaan =====
$(".inline-edit").on("change", function () {
    $.post("contract_onderdeel_types.php", {
        action: "saveName",
        id: $(this).data("id"),
        naam: $(this).val()
    });
});

// ===== Actief toggle =====
$(".toggleActiveBtn").on("click", function () {
    let btn = $(this);
    $.post("contract_onderdeel_types.php", {
        action: "toggleActive",
        id: btn.data("id")
    }, function () {
        if (btn.text() === "Actief") {
            btn.text("Inactief").css("background", "#c62828");
        } else {
            btn.text("Actief").css("background", "#2e7d32");
        }
    });
});

// ===== Drag & Drop Sort =====
$("#onderdelenList").sortable({
    handle: ".drag-handle",
    update: function () {
        let order = [];
        $(".onderdeel-row").each(function () {
            order.push($(this).data("id"));
        });

        $.post("contract_onderdeel_types.php", {
            action: "saveSort",
            order: order
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
