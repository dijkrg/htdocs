<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

/* ⭐ STATUS → KLEUR */
function getStatusColor(string $status = '', int $gereed = 0, string $monteurStatus = ''): string
{
    if ($gereed === 1) return '#9ca3af'; // werk gereed → grijs

    // 🔥 Monteurstatus heeft voorrang op werkbon-status
    if ($monteurStatus === 'onderweg')   return '#0ea5e9'; // blauw
    if ($monteurStatus === 'op_locatie') return '#22c55e'; // groen
    if ($monteurStatus === 'gereed')     return '#7c3aed'; // paars

    return match($status) {
        'Klaargezet' => '#1e40af',
        'Ingepland'  => '#2563eb',
        'Compleet'   => '#9ca3af',
        'Afgehandeld'=> '#22c55e',
        'Contract'   => '#f59e0b',
        default      => '#2954cc'
    };
}

/* ✅ FullCalendar stuurt start/end mee (ISO) */
$startParam = $_GET['start'] ?? null; // bijv. 2025-12-15T00:00:00Z
$endParam   = $_GET['end'] ?? null;

$startDate = null;
$endDate   = null;

if ($startParam) $startDate = substr((string)$startParam, 0, 10); // YYYY-MM-DD
if ($endParam)   $endDate   = substr((string)$endParam, 0, 10);   // YYYY-MM-DD

/* ⭐ Werkbonnen ophalen */
$sql = "
    SELECT 
        w.werkbon_id AS id,
        w.werkbonnummer,
        w.uitvoerdatum,
        w.starttijd,
        w.eindtijd,
        w.status,
        w.werk_gereed,
        w.monteur_id,
        w.omschrijving,

        -- ✅ NIEUW: werkzaamheden status
        w.werkzaamheden_status,
        w.werkzaamheden_status_at,

        -- bestaande monteur status
        w.monteur_status,
        w.monteur_status_timestamp,

        k.bedrijfsnaam AS klantnaam,
        CONCAT_WS(', ', wa.adres, wa.postcode, wa.plaats) AS werkadres
    FROM werkbonnen w
    LEFT JOIN klanten k ON w.klant_id = k.klant_id
    LEFT JOIN werkadressen wa ON w.werkadres_id = wa.werkadres_id
    WHERE w.gearchiveerd = 0
      AND w.monteur_id IS NOT NULL
      AND w.uitvoerdatum IS NOT NULL
";

/* ✅ datum-filter toevoegen als FullCalendar dit meestuurt */
$params = [];
$types  = "";

if ($startDate && $endDate) {
    // FullCalendar geeft end exclusief; dit is prima voor BETWEEN start..(end-1)
    $sql .= " AND w.uitvoerdatum >= ? AND w.uitvoerdatum < ? ";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

$sql .= " ORDER BY w.uitvoerdatum ASC, w.starttijd ASC, w.werkbon_id ASC ";

$events = [];

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

while ($row = $res->fetch_assoc()) {
    $start = $row['uitvoerdatum'];
    $end   = date('Y-m-d', strtotime($start . ' +1 day'));

    $color = getStatusColor(
        (string)($row['status'] ?? ''),
        (int)($row['werk_gereed'] ?? 0),
        (string)($row['monteur_status'] ?? '')
    );

    $events[] = [
        'id'         => (string)$row['id'],
        'title'      => ($row['werkbonnummer'] ?? '') . ' | ' . ($row['klantnaam'] ?? ''),
        'start'      => $start,
        'end'        => $end,
        'resourceId' => (string)$row['monteur_id'],

        // extra velden -> komen in extendedProps
        'starttijd'  => $row['starttijd'],
        'eindtijd'   => $row['eindtijd'],
        'status'     => $row['status'],
        'werk_gereed'=> (int)$row['werk_gereed'],
        'omschrijving' => $row['omschrijving'],
        'klant'      => $row['klantnaam'],
        'werkadres'  => $row['werkadres'],

        // ✅ werkzaamheden status
        'werkzaamheden_status'    => $row['werkzaamheden_status'],
        'werkzaamheden_status_at' => $row['werkzaamheden_status_at'],

        // monteur status
        'monteur_status'    => $row['monteur_status'],
        'monteur_timestamp' => $row['monteur_status_timestamp'],

        // kleur
        'backgroundColor' => $color,
        'borderColor'     => $color
    ];
}

if (!empty($params)) {
    $stmt->close();
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
exit;
