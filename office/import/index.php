<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Alleen Admin of Manager mag importeren
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin', 'Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

$pageTitle = "📥 Importeren van gegevens";
ob_start();
?>

<div class="page-header">
    <h2>📥 Gegevens importeren</h2>
    <p>Upload hieronder het juiste Excel-bestand (.xlsx) om gegevens toe te voegen aan het systeem.<br>
       Kies de juiste categorie: <strong>Klanten</strong>, <strong>Artikelen</strong> of <strong>Objecten</strong>.</p>
</div>

<div class="import-grid">

    <!-- 🟦 Klanten import -->
    <div class="card import-card">
        <h3>👤 Klanten importeren</h3>
        <p>Gebruik dit formulier om nieuwe klanten toe te voegen vanuit een Excel-bestand.<br>
           Vereiste kolommen: <code>debiteurnummer</code>, <code>bedrijfsnaam</code>, <code>contactpersoon</code>, <code>email</code>, 
           <code>telefoon</code>, <code>adres</code>, <code>postcode</code>, <code>plaats</code>.</p>
        <form action="klanten_import.php" method="post" enctype="multipart/form-data" class="import-form">
            <input type="file" name="excel_file" accept=".xlsx" required>
            <button type="submit" class="btn btn-primary">📤 Importeer Klanten</button>
        </form>
        <p class="hint">📘 <a href="/uploads/templates/klanten_import.xlsx" download>Voorbeeldbestand downloaden</a></p>
    </div>

    <!-- 🟩 Artikelen import -->
    <div class="card import-card">
        <h3>📦 Artikelen importeren</h3>
        <p>Gebruik dit formulier om artikelgegevens te importeren vanuit een Excel-bestand.<br>
           Vereiste kolommen: <code>artikelnummer</code>, <code>omschrijving</code>, <code>eenheid</code>, 
           <code>inkoopprijs</code>, <code>verkoopprijs</code>.</p>
        <form action="artikelen_import.php" method="post" enctype="multipart/form-data" class="import-form">
            <input type="file" name="excel_file" accept=".xlsx" required>
            <button type="submit" class="btn btn-primary">📤 Importeer Artikelen</button>
        </form>
        <p class="hint">📘 <a href="/uploads/templates/artikelen_import.xlsx" download>Voorbeeldbestand downloaden</a></p>
    </div>

    <!-- 🟧 Objecten import -->
    <div class="card import-card">
        <h3>🧯 Objecten importeren</h3>
        <p>Gebruik dit formulier om objecten (blusmiddelen, brandslangen, etc.) toe te voegen vanuit een Excel-bestand.<br>
           Vereiste kolommen: 
           <code>code</code>, <code>klant_debiteurnummer</code>, <code>omschrijving</code>, <code>merk</code>, 
           <code>rijkstypekeur</code>, <code>fabricagejaar</code>, <code>beproeving NEN 671-3</code>, 
           <code>installatiedatum</code>, <code>onderhoudsdatum</code>, <code>revisiejaar</code>, <code>resultaat</code>.</p>
        <form action="objecten_import.php" method="post" enctype="multipart/form-data" class="import-form">
            <input type="file" name="excel_file" accept=".xlsx" required>
            <button type="submit" class="btn btn-primary">📤 Importeer Objecten</button>
        </form>
        <p class="hint">📘 <a href="/uploads/templates/objecten_import.xlsx" download>Voorbeeldbestand downloaden</a></p>
    </div>

</div>

<style>
.import-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.import-card {
    padding: 20px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.import-card h3 {
    margin-top: 0;
    color: #333;
}

.import-card p {
    font-size: 14px;
    color: #555;
}

.import-form {
    margin-top: 10px;
}

.import-form input[type="file"] {
    display: block;
    margin-bottom: 10px;
}

.hint {
    margin-top: 8px;
    font-size: 13px;
}

.hint a {
    color: #2954cc;
    text-decoration: none;
}

.hint a:hover {
    text-decoration: underline;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
?>
