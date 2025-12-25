<?php
// ==========================
// 🔐 Authenticatiecontrole
// ==========================

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Sessieduur verlengen (24 uur)
    ini_set('session.gc_maxlifetime', '86400');
    ini_set('session.cookie_lifetime', '86400');

    // LET OP: domain op HTTP_HOST kan subdomain issues geven.
    // Voor nu laten we 'm veilig: geen domain meegeven = werkt automatisch per host.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        // 'domain' => $_SERVER['HTTP_HOST'], // ❌ liever uit laten
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

/**
 * ✅ Bestanden veilig includen (flash/db) met fallback paden
 */
function requireFileWithFallback(array $candidates): void {
    foreach ($candidates as $file) {
        if ($file && is_file($file)) {
            require_once $file;
            return;
        }
    }
    http_response_code(500);
    echo "Fout: vereist bestand niet gevonden.<br><pre>" . htmlspecialchars(implode("\n", $candidates)) . "</pre>";
    exit;
}

$dir = __DIR__;
$parent = dirname(__DIR__);

// db.php
requireFileWithFallback([
    $dir . '/db.php',
    $dir . '/includes/db.php',
    $parent . '/db.php',
    $parent . '/includes/db.php',
]);

// flash.php
requireFileWithFallback([
    $dir . '/flash.php',
    $dir . '/includes/flash.php',
    $parent . '/flash.php',
    $parent . '/includes/flash.php',
]);

/**
 * ✅ Kleine helper om cookies veilig te wissen
 */
function deleteRememberCookies(): void {
    setcookie("remember_me", "", time() - 3600, "/");
    setcookie("remember_email", "", time() - 3600, "/");
}

/**
 * ✅ Sessie of Remember-me: gebruiker ophalen
 */
if (empty($_SESSION['user']) && !empty($_COOKIE['remember_me'])) {
    $rawToken = (string)$_COOKIE['remember_me'];

    // Hash de token zodat gestolen cookie niet 1-op-1 werkt in DB
    $tokenHash = hash('sha256', $rawToken);

    // Check token + expiry
    $stmt = $conn->prepare("
        SELECT * FROM medewerkers
        WHERE remember_token = ?
          AND remember_expires > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Extra check: fingerprint op IP + User-Agent om cookie misbruik te beperken
        $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if (!empty($user['remember_fingerprint']) && $user['remember_fingerprint'] !== $fingerprint) {
            deleteRememberCookies();
            setFlash("Je sessie is ongeldig geworden. Log opnieuw in.", "error");
            header("Location: /login.php");
            exit;
        }

        // 📅 Laatst gebruikt bijwerken
        $update = $conn->prepare("
            UPDATE medewerkers
            SET remember_last_used = NOW()
            WHERE medewerker_id = ?
        ");
        $mid = (int)$user['medewerker_id'];
        $update->bind_param("i", $mid);
        $update->execute();
        $update->close();

        // ✅ Automatisch inloggen
        $_SESSION['user'] = [
            'id'         => (int)$user['medewerker_id'],
            'voornaam'   => (string)($user['voornaam'] ?? ''),
            'achternaam' => (string)($user['achternaam'] ?? ''),
            'rol'        => (string)($user['rol'] ?? ''),
            'email'      => (string)($user['email'] ?? ''),
            'naam'       => trim((string)($user['voornaam'] ?? '') . ' ' . (string)($user['achternaam'] ?? '')),
        ];
    } else {
        deleteRememberCookies();
    }
}

/**
 * ✅ Geen sessie → redirect naar login
 */
if (empty($_SESSION['user'])) {
    if (!empty($_COOKIE['remember_me'])) {
        setFlash("Je sessie is verlopen, log opnieuw in.", "error");
    }
    header("Location: /login.php");
    exit;
}

/**
 * 🔒 Functie om toegang te beperken tot rollen
 */
function checkRole($rollen): void {
    $rollen = (array)$rollen;
    $rol = (string)($_SESSION['user']['rol'] ?? '');

    if ($rol === '' || !in_array($rol, $rollen, true)) {
        http_response_code(403);
        $pageTitle = "Toegang geweigerd";
        ob_start();
        ?>
        <div class="error-page">
            <h1>⛔ 403 - Toegang geweigerd</h1>
            <p>Je hebt geen rechten om deze pagina te bekijken.</p>
            <a href="/index.php">⬅ Terug naar dashboard</a>
        </div>
        <?php
        $content = ob_get_clean();

        // template fallback (zelfde map / parent)
        $tpl1 = __DIR__ . "/template/template.php";
        $tpl2 = dirname(__DIR__) . "/template/template.php";
        if (is_file($tpl1)) {
            include $tpl1;
        } elseif (is_file($tpl2)) {
            include $tpl2;
        } else {
            echo $content;
        }
        exit;
    }
}
