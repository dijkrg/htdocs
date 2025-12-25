<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

// -----------------------------------------------------
// 🔐 Beveiliging
// -----------------------------------------------------
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['Admin','Manager'])) {
    setFlash("Geen toegang.", "error");
    header("Location: ../index.php");
    exit;
}

// -----------------------------------------------------
// 📌 Stap 2 — Conflictscherm bevestigen
// -----------------------------------------------------
if (isset($_POST['confirm_conflicts'])) {

    $tmpFile = __DIR__ . '/object_import_tmp.json';
    if (!file_exists($tmpFile)) {
        setFlash("Importdata niet gevonden.", "error");
        header("Location: import.php");
        exit;
    }

    $data = json_decode(file_get_contents($tmpFile), true);
    unlink($tmpFile);

    $keuzes = $_POST['actie'] ?? [];

    // --------------------------------------------
    // SQL statements voorbereiden
    // --------------------------------------------
    $stmtUpdate = $conn->prepare("
        UPDATE objecten SET
            debiteurnummer_id = ?,
            klant_id = ?,
            werkadres_id = ?,
            omschrijving = ?,
            merk = ?,
            rijkstypekeur = ?,
            fabricagejaar = ?,
            beproeving_nen671_3 = ?,
            datum_installatie = ?,
            datum_onderhoud = ?,
            revisiejaar = ?,
            resultaat = ?
        WHERE code = ?
    ");

    $stmtInsert = $conn->prepare("
        INSERT INTO objecten
        (
            debiteurnummer_id, klant_id, werkadres_id,
            code, omschrijving, merk, rijkstypekeur,
            fabricagejaar, beproeving_nen671_3,
            datum_installatie, datum_onderhoud,
            revisiejaar, resultaat
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $updated = 0;
    $inserted = 0;
    $skipped = 0;

    foreach ($data as $code => $row) {

        $actie = $keuzes[$code] ?? 'skip';
        if ($actie === 'skip') {
            $skipped++;
            continue;
        }

        // BIND volgorde (UPDATE):
        // s i i s s s i i s s i s s

        if ($actie === 'overwrite') {

            $stmtUpdate->bind_param(
                "siisssiississs",
                $row['debiteurnummer_id'],
                $row['klant_id'],
                $row['werkadres_id'],
                $row['omschrijving'],
                $row['merk'],
                $row['rijkstypekeur'],
                $row['fabricagejaar'],
                $row['beproeving_nen671_3'],
                $row['datum_installatie'],
                $row['datum_onderhoud'],
                $row['revisiejaar'],
                $row['resultaat'],
                $row['code']
            );

            $stmtUpdate->execute();
            $updated++;
        }

        if ($actie === 'insert') {

            $stmtInsert->bind_param(
                "siisssiississs",
                $row['debiteurnummer_id'],
                $row['klant_id'],
                $row['werkadres_id'],
                $row['code'],
                $row['omschrijving'],
                $row['merk'],
                $row['rijkstypekeur'],
                $row['fabricagejaar'],
                $row['beproeving_nen671_3'],
                $row['datum_installatie'],
                $row['datum_onderhoud'],
                $row['revisiejaar'],
                $row['resultaat']
            );

            $stmtInsert->execute();
            $inserted++;
        }
    }

    setFlash("✅ Import voltooid: $inserted toegevoegd, $updated bijgewerkt, $skipped overgeslagen.", "success");
    header("Location: import.php");
    exit;
}


// -----------------------------------------------------
// 📌 Stap 1 — Upload & Analyse Excel
// -----------------------------------------------------
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    setFlash("Bestand uploaden mislukt.", "error");
    header('Location: import.php');
    exit;
}

$file = $_FILES['excel_file']['tmp_name'];

function parseDateCell($c)
{
    if ($c === null || $c === '') return null;

    if (is_numeric($c)) {
        try {
            $dt = XlsDate::excelToDateTimeObject($c);
            return $dt ? $dt->format("Y-m-d") : null;
        } catch (Exception $e) {}
    }

    $c = str_replace("/", "-", trim($c));
    foreach (["d-m-Y","Y-m-d","m-d-Y"] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $c);
        if ($dt) return $dt->format("Y-m-d");
    }

    return null;
}


// -----------------------------------------------------
// 📄 Excel inlezen
// -----------------------------------------------------
$sheet = IOFactory::load($file)->getActiveSheet();
$lastRow = $sheet->getHighestDataRow();
$lastCol = $sheet->getHighestColumn();
$header = $sheet->rangeToArray("A1:$lastCol"."1", null, true, true, true)[1];

// Header normaliseren
$map = [];
foreach ($header as $col => $val) {
    $key = strtolower(trim($val));
    $key = str_replace([" ", "/", "\\"], "_", $key);
    $map[$key] = $col;
}

// Vereiste kolommen
$require = ['code','debiteurnummer','werkadres_nr'];
foreach ($require as $req) {
    $found = false;
    foreach ($map as $k => $col) {
        if ($k === $req) $found = true;
    }
    if (!$found) {
        setFlash("Kolom ontbreekt in Excel: $req", "error");
        header("Location: import.php");
        exit;
    }
}


// -----------------------------------------------------
// 🔍 Opbouw data voor test + conflict detectie
// -----------------------------------------------------
$stmtFindKlant = $conn->prepare("SELECT klant_id FROM klanten WHERE debiteurnummer=?");
$stmtFindWA = $conn->prepare("SELECT werkadres_id FROM werkadressen WHERE werkadresnummer=? AND klant_id=?");
$stmtCheckCode = $conn->prepare("SELECT object_id FROM objecten WHERE code=?");

$importData = [];
$conflicts = [];

for ($r = 2; $r <= $lastRow; $r++) {

    $row = $sheet->rangeToArray("A$r:$lastCol$r", null, true, true, true)[$r];

    // Helper om Excel values op te halen
    $get = function($name) use ($map, $row) {
        $name = strtolower($name);
        return isset($map[$name]) ? trim((string)$row[$map[$name]]) : null;
    };

    $code = $get('code');
    $debnr = $get('debiteurnummer');
    $werkadres_nr = $get('werkadres_nr');

    if (!$code || !$debnr) continue;

    // klant_id ophalen
    $stmtFindKlant->bind_param("s", $debnr);
    $stmtFindKlant->execute();
    $kl = $stmtFindKlant->get_result()->fetch_assoc();
    if (!$kl) continue;

    $klant_id = $kl['klant_id'];

    // werkadres koppelen
    $stmtFindWA->bind_param("si", $werkadres_nr, $klant_id);
    $stmtFindWA->execute();
    $wa = $stmtFindWA->get_result()->fetch_assoc();
    $werkadres_id = $wa['werkadres_id'] ?? null;

    // conflict check
    $stmtCheckCode->bind_param("s", $code);
    $stmtCheckCode->execute();
    $exists = $stmtCheckCode->get_result()->fetch_assoc();

    // Data vullen
    $data = [
        'code' => $code,
        'debiteurnummer_id' => $debnr,
        'klant_id' => $klant_id,
        'werkadres_id' => $werkadres_id,

        'omschrijving' => $get('omschrijving'),
        'merk' => $get('merk'),
        'rijkstypekeur' => $get('rijkstypekeur'),

        'fabricagejaar' => ($get('fabricagejaar') ?: null),
        'beproeving_nen671_3' => ($get('beproeving_nen_671_3') ?: null),
        'revisiejaar' => ($get('revisiejaar') ?: null),

        'datum_installatie' => parseDateCell($get('installatiedatum')),
        'datum_onderhoud' => parseDateCell($get('onderhoudsdatum')),

        'resultaat' => $get('resultaat')
    ];

    $importData[$code] = $data;

    if ($exists) {
        $conflicts[] = [
            'code' => $code,
            'omschrijving' => $data['omschrijving'],
            'rij' => $r
        ];
    }
}


// -----------------------------------------------------
// 📌 Conflictscherm tonen
// -----------------------------------------------------
if (!empty($conflicts)) {

    file_put_contents(__DIR__ . '/object_import_tmp.json', json_encode($importData));

    ?>
    <!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <title>Bevestig import</title>
        <link rel="stylesheet" href="../template/style.css">
        <style>
            table { border-collapse: collapse; width:100%; }
            th,td { padding:8px; border-bottom:1px solid #ddd; }
            th { background:#f8f9fa; }
        </style>
    </head>
    <body>
    <div class="card" style="max-width:900px;margin:30px auto;">
        <h2>⚠️ Bestaande objecten gevonden</h2>
        <p>Kies wat je per regel wilt doen:</p>

        <form method="post">
            <input type="hidden" name="confirm_conflicts" value="1">

            <table>
                <thead>
                <tr><th>Rij</th><th>Code</th><th>Omschrijving</th><th>Actie</th></tr>
                </thead>
                <tbody>
                <?php foreach ($conflicts as $c): ?>
                    <tr>
                        <td><?= $c['rij'] ?></td>
                        <td><?= htmlspecialchars($c['code']) ?></td>
                        <td><?= htmlspecialchars($c['omschrijving']) ?></td>
                        <td>
                            <select name="actie[<?= htmlspecialchars($c['code']) ?>]">
                                <option value="skip">⏭ Overslaan</option>
                                <option value="overwrite">✏️ Overschrijven</option>
                                <option value="insert">➕ Dubbel invoeren</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:15px;display:flex;justify-content:space-between;">
                <button class="btn">Bevestigen</button>
                <a href="import.php" class="btn btn-secondary">Annuleren</a>
            </div>
        </form>
    </div>
    </body>
    </html>
    <?php

    exit;
}


// -----------------------------------------------------
// 📌 Geen conflicten → direct invoeren
// -----------------------------------------------------
$stmtInsert = $conn->prepare("
    INSERT INTO objecten
    (
        debiteurnummer_id, klant_id, werkadres_id,
        code, omschrijving, merk, rijkstypekeur,
        fabricagejaar, beproeving_nen671_3,
        datum_installatie, datum_onderhoud,
        revisiejaar, resultaat
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");

foreach ($importData as $row) {

    $stmtInsert->bind_param(
        "siisssiississs",
        $row['debiteurnummer_id'],
        $row['klant_id'],
        $row['werkadres_id'],
        $row['code'],
        $row['omschrijving'],
        $row['merk'],
        $row['rijkstypekeur'],
        $row['fabricagejaar'],
        $row['beproeving_nen671_3'],
        $row['datum_installatie'],
        $row['datum_onderhoud'],
        $row['revisiejaar'],
        $row['resultaat']
    );

    $stmtInsert->execute();
}

setFlash("✅ " . count($importData) . " object(en) geïmporteerd.", "success");
header("Location: import.php");
exit;
