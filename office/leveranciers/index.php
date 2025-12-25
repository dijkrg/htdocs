<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

$pageTitle = "Leveranciers & Bestellingen";

ob_start();
?>
<div class="page-header">
    <h2>🏢 Leveranciersbeheer</h2>
</div>

<div class="card">
    <p>Beheer hier leveranciers en hun bestellingen.</p>
    <div class="magazijn-buttons" style="display:flex; flex-wrap:wrap; gap:15px;">
        <a href="leveranciers.php" class="btn">🏢 Leveranciers</a>
        <a href="bestellingen.php" class="btn">📝 Bestellingen</a>
        <a href="leverancier_toevoegen.php" class="btn btn-primary">➕ Nieuwe leverancier</a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../template/template.php';
