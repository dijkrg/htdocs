<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Altijd 100% error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check omgeving
$host = $_SERVER['HTTP_HOST'] ?? '';

// Standaard database-gegevens
$dbname = "abcbrand_officeadmin";

// Bepaal of we lokaal of online draaien
$isLocal = ($host === 'localhost' || $host === '127.0.0.1' || $host === '');

if ($isLocal && getenv('USE_LOCAL_DB') === 'true') {
    // 🔹 Lokaal (XAMPP) - alleen als expliciet ingesteld
    $servername = "localhost";
    $username = "root";
    $password = "";
} else {
    // 🔹 Online server (standaard)
    $servername = "185.104.29.236";
    $username = "abcbrand_officeadmin";
    $password = "Admin@7819";
}

// Verbinding maken
$conn = new mysqli($servername, $username, $password, $dbname);

// Controleren op fouten
if ($conn->connect_error) {
    die("❌ Database connectie mislukt: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
