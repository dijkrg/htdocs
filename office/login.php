<?php
require_once __DIR__ . '/includes/init.php';

// Als al ingelogd → direct doorsturen
if (isLoggedIn()) {
    if ($_SESSION['user']['rol'] === 'Monteur') {
        header("Location: /monteur/index.php");
    } else {
        header("Location: /index.php");
    }
    exit;
}

$emailPrefill = $_COOKIE['remember_email'] ?? '';
$rememberChecked = isset($_COOKIE['remember_email']) ? 'checked' : '';

$error = "";

// ----------------------------------------
// FORM HANDLING
// ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $wachtwoord = $_POST['wachtwoord'];
    $remember = !empty($_POST['remember']);

    // Gebruiker ophalen
    $stmt = $conn->prepare("SELECT * FROM medewerkers WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($wachtwoord, $user['wachtwoord'])) {
        $error = "Ongeldige logingegevens.";
    } else {

        // Session vullen
        $_SESSION['user'] = [
            'id'         => (int)$user['medewerker_id'],
            'voornaam'   => $user['voornaam'],
            'achternaam' => $user['achternaam'],
            'naam'       => trim($user['voornaam']." ".$user['achternaam']),
            'rol'        => $user['rol'],
            'email'      => $user['email']
        ];

        // ✔ Remember-email voor login veld
        if ($remember) {
            setcookie("remember_email", $email, time() + 86400*30, "/", "", false, false);
        } else {
            setcookie("remember_email", "", time() - 3600, "/", "", false, false);
        }

        // ✔ Remember-me token voor auto-login
        if ($remember) {
            $rawToken = bin2hex(random_bytes(32)); 
            $tokenHash = hash('sha256', $rawToken);
            $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

            $stmt = $conn->prepare("UPDATE medewerkers SET remember_token=?, remember_expires=DATE_ADD(NOW(), INTERVAL 30 DAY), remember_fingerprint=? WHERE medewerker_id=?");
            $stmt->bind_param("ssi", $tokenHash, $fingerprint, $user['medewerker_id']);
            $stmt->execute();
            $stmt->close();

            setcookie("remember_me", $rawToken, time()+86400*30, "/", "", false, true);
        } else {
            setcookie("remember_me", "", time()-3600, "/", "", false, true);
        }

        // Doorsturen op basis van rol
        if ($user['rol'] === 'Monteur') {
            header("Location: /monteur/index.php");
        } else {
            header("Location: /index.php");
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>

<link rel="icon" type="image/x-icon" href="/template/favicon.ico">
<link rel="shortcut icon" href="/template/favicon.ico">

<meta charset="UTF-8">
<title>Inloggen | ABC Brand Beveiliging</title>
<link rel="stylesheet" href="/template/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="manifest" href="/manifest.json">

<style>
/* --------------------------------------------- */
/*        ABCB LOGIN DESIGN V3.0                 */
/* --------------------------------------------- */

body.login-body {
    background:#f3f4f6;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    margin:0;
}

.login-card {
    background:white;
    padding:40px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
    max-width:400px;
    width:100%;
    text-align:center;
}

.login-card img.logo-login {
    max-width:240px;
    margin-bottom:20px;
}

.login-card h2 {
    color:#2954cc;
    margin-bottom:25px;
}

.login-card input {
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:1px solid #ddd;
    border-radius:8px;
    font-size:15px;
}

.password-wrap {
    position: relative;
}

.toggle-password {
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    font-size:16px;
    cursor:pointer;
    opacity:0.6;
}

.toggle-password:hover { opacity:1; }

.login-card button {
    width:100%;
    padding:12px;
    background:#2954cc;
    color:white;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
    font-size:15px;
}
.login-card button:hover { background:#1e3a8a; }

.remember-row input[type=checkbox] { margin:0; }

.login-links {
    margin-top:15px;
    font-size:14px;
}
.login-links a {
    color:#2954cc;
    text-decoration:none;
}
.login-links a:hover { text-decoration:underline; }

.error-box {
    background:#dc3545;
    color:#fff;
    padding:10px;
    border-radius:6px;
    margin-bottom:15px;
    font-size:14px;
}
/* ✔ FIX — Onthoud-mij perfect uitgelijnd */
.remember-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 8px !important;
    justify-content: flex-start !important;
    margin: 10px 0 18px 0 !important;
    padding: 0 !important;
}

.remember-row input[type="checkbox"] {
    margin: 0 !important;
    width: 18px !important;
    height: 18px !important;
}

.remember-row label {
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1 !important;
    cursor: pointer;
    font-size: 14px;
}

</style>

</head>

<body class="login-body">

<div class="login-card">

    <img src="template/logo_abc.png" alt="Logo" class="logo-login">

    <h2>Inloggen</h2>

    <?php if (!empty($error)): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">

        <input type="email"
               name="email"
               placeholder="E-mailadres"
               value="<?= htmlspecialchars($emailPrefill) ?>"
               required>

        <div class="password-wrap">
            <input type="password"
                   name="wachtwoord"
                   id="passwordField"
                   placeholder="Wachtwoord"
                   required>

            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword()"></i>
        </div>

        <div class="remember-row">
            <input type="checkbox"
                   name="remember"
                   id="remember"
                   <?= $rememberChecked ?>>
            <label for="remember">Onthoud mij</label>
        </div>

        <button type="submit">Inloggen</button>
    </form>

    <div class="login-links">
        <a href="wachtwoord_vergeten.php">Wachtwoord vergeten?</a>
    </div>

</div>

<script>
// 👁 Toggle wachtwoord zichtbaar/onzichtbaar
function togglePassword() {
    const field = document.getElementById("passwordField");
    field.type = field.type === "password" ? "text" : "password";
}
</script>

</body>
</html>
