<?php
// =====================================================
// INIT.PHP — ABCB CORE FRAMEWORK v4 (ULTRA CLEAN)
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// -----------------------------------------------------
// 🔌 DATABASE
// -----------------------------------------------------
$host = 'localhost';
$user = 'abcbrand';
$pass = 'Admin@7819';
$db   = 'abcbrand_officeadmin';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die("DB Fout: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

define('LOGIN_PATH', '/login.php');

// -----------------------------------------------------
// ⚡ HUIDIGE SCRIPT & PATH
// -----------------------------------------------------
$SCRIPT = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
$URI    = strtolower($_SERVER['REQUEST_URI'] ?? '');

// -----------------------------------------------------
// ⚠️ EXCEPTIES — deze bestanden mogen NIET geremd worden
// Anders ontstaat een redirect-loop (zoals bij logout)
// -----------------------------------------------------
$EXEMPT = ['login.php', 'logout.php'];

// -----------------------------------------------------
// ⚙️ FLASH
// -----------------------------------------------------
function setFlash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function showFlash(): void {
    if (empty($_SESSION['flash'])) return;

    foreach ($_SESSION['flash'] as $f) {
        $cls = match($f['type']) {
            'success' => 'flash-success',
            'error'   => 'flash-error',
            'warning' => 'flash-warning',
            default   => 'flash-info'
        };
        echo "<div class='flash {$cls}'>" . htmlspecialchars($f['msg']) . "</div>";
    }
    unset($_SESSION['flash']);
}

// -----------------------------------------------------
// 👤 LOGIN FUNCTIES
// -----------------------------------------------------
function isLoggedIn(): bool {
    return !empty($_SESSION['user']['id']);
}

function requireLogin(): void {
    global $SCRIPT;

    if (isLoggedIn()) return;

    // Alleen login.php mag zonder sessie
    if ($SCRIPT !== 'login.php') {
        header("Location: " . LOGIN_PATH);
        exit;
    }
}

function requireRole(array $roles): void {
    if (!isLoggedIn() || !in_array($_SESSION['user']['rol'], $roles, true)) {
        setFlash("Geen toegang voor jouw rol.", "error");
        header("Location: " . LOGIN_PATH);
        exit;
    }
}

function checkRole(array $roles): bool {
    return isLoggedIn() && in_array($_SESSION['user']['rol'], $roles, true);
}

// -----------------------------------------------------
// 🔐 REMEMBER-ME AUTOLOGIN — veilig & stabiel
// -----------------------------------------------------
if (!in_array($SCRIPT, $EXEMPT) && !isLoggedIn() && !empty($_COOKIE['remember_me'])) {

    $raw   = $_COOKIE['remember_me'];
    $hash  = hash('sha256', $raw);
    $fp    = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    $stmt = $conn->prepare("
        SELECT *
        FROM medewerkers
        WHERE remember_token = ?
        AND remember_expires > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($u && hash_equals((string)$u['remember_fingerprint'], $fp)) {

        // Herstel sessie
        $_SESSION['user'] = [
            'id'         => (int)$u['medewerker_id'],
            'voornaam'   => $u['voornaam'],
            'achternaam' => $u['achternaam'],
            'naam'       => trim($u['voornaam'] . " " . $u['achternaam']),
            'rol'        => $u['rol'],
            'email'      => $u['email']
        ];

    } else {
        // Fout → opruimen
        setcookie("remember_me", "", time()-3600, "/");
        setcookie("remember_email", "", time()-3600, "/");
    }
}

// -----------------------------------------------------
// 🚀 NA LOGIN: DIRECTE DOORSTUREN PER ROL
// (alleen op login.php)
// -----------------------------------------------------
if (isLoggedIn() && $SCRIPT === 'login.php') {

    $rol = strtolower($_SESSION['user']['rol']);

    if ($rol === 'monteur') {
        header("Location: /monteur/index.php");
        exit;
    }

    header("Location: /index.php");
    exit;
}

// -----------------------------------------------------
// 🔒 MONTEUR → MAG NIET IN KANTOOROMGEVING
// 🚨 MAAR logout.php MAG ALTIJD
// -----------------------------------------------------
if (isLoggedIn() && strtolower($_SESSION['user']['rol']) === 'monteur') {

    // Monteur moet ALTIJD uitloggen kunnen
    if ($SCRIPT === 'logout.php') return;

    // Monteur mag ALLEEN in /monteur/
    if (!str_starts_with($URI, '/monteur/')) {
        header("Location: /monteur/index.php");
        exit;
    }
}

// -----------------------------------------------------
// ✨ HTML ESCAPE
// -----------------------------------------------------
if (!function_exists('e')) {
    function e($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
}

?>
