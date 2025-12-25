<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "<pre style='font-size:14px;'>";
echo "🟢 Script gestart...\n";

if (!isset($_SESSION['user'])) {
    exit("🚫 Geen sessie actief — log in.\n");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "ℹ️ Geen upload ontvangen. Gebruik het formulier hieronder.\n";
    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="bestand" required>
        <button type="submit">Upload</button>
    </form>
    <?php
    exit;
}

if (!isset($_FILES['bestand']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
    exit("❌ Uploadfout of geen bestand ontvangen.\n");
}

$filePath = $_FILES['bestand']['tmp_name'];
echo "📄 Bestand ontvangen: " . htmlspecialchars($_FILES['bestand']['name']) . "\n";
echo "📦 Grootte: " . $_FILES['bestand']['size'] . " bytes\n";

if (!file_exists($filePath)) {
    exit("❌ Bestand niet gevonden op server.\n");
}

try {
    echo "🔍 Bestand inlezen met PhpSpreadsheet...\n";
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    echo "✅ Gelezen rijen: " . count($rows) . "\n";

    // Toon eerste 3 rijen ruwe data
    echo "\n--- Ruwe data voorbeeld ---\n";
    print_r(array_slice($rows, 0, 3, true));

    // Zoek eerste niet-lege rij = header
    $headerRow = null;
    foreach ($rows as $index => $row) {
        if (!empty(array_filter($row))) { $headerRow = $index; break; }
    }

    if (!$headerRow) {
        exit("❌ Geen niet-lege header rij gevonden.\n");
    }

    echo "🧾 Header gevonden op rij $headerRow\n";
    $header = array_map('strtolower', array_map('trim', $rows[$headerRow] ?? []));
    unset($rows[$headerRow]);

    echo "🔠 Herkende kolommen:\n";
    print_r($header);

    $requiredCols = ['debiteurnummer','bedrijfsnaam','contactpersoon','telefoon','email','adres','postcode','plaats'];
    $missing = array_diff($requiredCols, $header);
    if (!empty($missing)) {
        echo "⚠️ Ontbrekende kolommen: " . implode(', ', $missing) . "\n";
    }

    // Start import
    $importCount = 0;
    $stmt = $conn->prepare("
        INSERT INTO klanten 
        (bedrijfsnaam, debiteurnummer, contactpersoon, telefoon, email, adres, postcode, plaats)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    echo "🚀 Start import...\n";
    foreach ($rows as $i => $r) {
        $row = @array_combine($header, $r);
        if (!$row) continue;

        $bedrijfsnaam = trim($row['bedrijfsnaam'] ?? '');
        $debiteurnummer = trim($row['debiteurnummer'] ?? '');
        if ($bedrijfsnaam === '') continue;

        $stmt->bind_param(
            "ssssssss",
            $bedrijfsnaam, $debiteurnummer,
            $row['contactpersoon'] ?? '', $row['telefoon'] ?? '',
            $row['email'] ?? '', $row['adres'] ?? '',
            $row['postcode'] ?? '', $row['plaats'] ?? ''
        );

        if ($stmt->execute()) {
            $importCount++;
            echo "✅ Rij $i toegevoegd: $bedrijfsnaam\n";
        } else {
            echo "❌ Fout bij rij $i ($bedrijfsnaam): " . $stmt->error . "\n";
        }
    }

    echo "\n--- Import gereed ---\n";
    echo "✅ Totaal toegevoegd: $importCount klant(en)\n";

} catch (Throwable $e) {
    echo "\n❌ FATALE FOUT: " . $e->getMessage() . "\n";
}
echo "</pre>";
