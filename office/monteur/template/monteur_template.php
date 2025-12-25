<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';

if (empty($_SESSION['user']) || ($_SESSION['user']['rol'] ?? '') !== 'Monteur') {
    header("Location: /login.php");
    exit;
}

$pageTitle = $pageTitle ?? "Monteur | ABCB";
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string)$pageTitle) ?></title>

    <!-- ░░░ PWA CONFIG ░░░ -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2954cc">

    <!-- iOS PWA Support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <!-- Icons -->
    <link rel="icon" href="/icons/icon-96.png">

    <!-- CSS -->
    <link rel="stylesheet" href="/template/style.css">
    <link rel="stylesheet" href="/monteur/template/monteur_base.css">
    <link rel="stylesheet" href="/monteur/template/monteur_mobile.css">
    <link rel="stylesheet" href="/monteur/template/monteur.css?v=<?= @filemtime(__DIR__ . '/monteur.css') ?: time() ?>">

    <!-- FontAwesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SERVICE WORKER – Alleen voor /monteur/ -->
    <script>
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/monteur/service-worker.js", { scope: "/monteur/" })
            .catch(err => console.error("SW fout:", err));
    }
    </script>

    <style>
    /* =========================================================
       HAMBURGER DRAWER LAYOUT – MONTEUR (STANDARD TOPBAR COLOR)
       ========================================================= */
    :root{
        --topbar-h: 60px;
        --sb-w: 260px;
        --sb-bg: #1f2937; /* menu mag donker blijven */
    }

    /* --- Topbar: NIET meer kleur forceren, laat style.css bepalen --- */
    .topbar{
        position: fixed !important;
        top: 0; left: 0;
        width: 100% !important;
        height: var(--topbar-h) !important;
        z-index: 1200 !important;

        background: inherit !important; /* pakt standaard uit /template/style.css */
        color: inherit !important;
    }

    /* --- Content full width --- */
    .content, .monteur-content{
        margin-left: 0 !important;
        margin-top: var(--topbar-h) !important;
        padding: 20px !important;
    }

    /* --- Hamburger: force zichtbaar + donker op lichte header --- */
    .topbar .hamburger{
        display: inline-flex !important; /* ook als base: display:none !important */
        align-items: center !important;
        justify-content: center !important;

        width: 40px !important;
        height: 40px !important;
        border-radius: 10px !important;
        cursor: pointer !important;

        background: rgba(0,0,0,0.06) !important;
        border: 1px solid rgba(0,0,0,0.12) !important;
        color: #111 !important;

        padding: 0 !important;
    }
    .topbar .hamburger i{
        color:#111 !important;
        font-size: 18px !important;
    }
    .topbar .hamburger:hover{
        background: rgba(0,0,0,0.10) !important;
    }

    /* --- Sidebar drawer --- */
    .sidebar{
        position: fixed !important;
        top: var(--topbar-h) !important;
        left: 0 !important;
        bottom: 0 !important;
        width: var(--sb-w) !important;

        background: var(--sb-bg) !important;
        border-right: 1px solid #222 !important;
        z-index: 1100 !important;

        transform: translateX(-100%) !important;
        transition: transform .18s ease !important;

        overflow-y: auto !important;
        padding: 10px 0 !important;
    }
    body.sb-open .sidebar{
        transform: translateX(0) !important;
    }

    .sidebar nav a{
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 12px 16px !important;

        color: #e5e7eb !important;
        text-decoration: none !important;
        font-size: 14px !important;
    }
    .sidebar nav a i{
        width: 26px !important;
        text-align: center !important;
        font-size: 18px !important;
        color: #fff !important;
    }
    .sidebar nav a:hover{
        background: rgba(255,255,255,0.08) !important;
    }

    /* Tooltip uit (van oude collapsed variant) */
    .sidebar a::after,
    .sidebar a::before{
        display: none !important;
    }

    /* --- Backdrop --- */
    .sb-backdrop{
        position: fixed !important;
        inset: 0 !important;
        top: var(--topbar-h) !important;

        background: rgba(0,0,0,0.35) !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transition: opacity .18s ease !important;

        z-index: 1050 !important;
    }
    body.sb-open .sb-backdrop{
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    /* Mobile */
    @media(max-width:480px){
        :root{ --sb-w: 240px; }
        .content, .monteur-content{ padding: 15px !important; }
    }
    </style>
</head>

<body>

<!-- ======================================================================= -->
<!-- TOPBAR                                                                  -->
<!-- ======================================================================= -->
<div class="topbar">
    <div class="topbar-left" style="display:flex;align-items:center;gap:10px;">
        <button class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <img src="/template/ABCBFAV.png" class="logo-header" alt="ABCB">
    </div>

    <div class="topbar-right">
        <span>👷 <?= htmlspecialchars((string)($_SESSION['user']['voornaam'] ?? 'Monteur')) ?></span>

        <button id="darkModeToggle" class="dark-toggle" type="button">
            <i class="fa-solid fa-moon"></i>
        </button>

        <a href="/profiel.php" title="Profiel"><i class="fa-solid fa-user"></i></a>
        <a href="/logout.php" title="Uitloggen"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<!-- ======================================================================= -->
<!-- LAYOUT                                                                  -->
<!-- ======================================================================= -->
<div class="layout">

    <!-- Backdrop -->
    <div class="sb-backdrop" onclick="closeSidebar()"></div>

    <!-- SIDEBAR (3 knoppen) -->
    <aside class="sidebar" id="sidebar">
        <nav>
            <a href="/monteur/monteur_dashboard.php">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </a>

            <a href="/monteur/mijn_planning.php">
                <i class="fa-solid fa-calendar-days"></i> <span>Mijn planning</span>
            </a>

            <a href="/monteur/mijn_uren.php">
                <i class="fa-solid fa-clock"></i> <span>Mijn uren</span>
            </a>
        </nav>
    </aside>

    <!-- CONTENT -->
    <main class="content monteur-content">
        <?php if (function_exists('showFlash')) { showFlash(); } ?>
        <?= $content ?? '' ?>
    </main>

</div>

<!-- ======================================================================= -->
<!-- SIDEBAR JS                                                              -->
<!-- ======================================================================= -->
<script>
function openSidebar(){ document.body.classList.add('sb-open'); }
function closeSidebar(){ document.body.classList.remove('sb-open'); }
function toggleSidebar(){ document.body.classList.toggle('sb-open'); }

// ESC sluit
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeSidebar();
});

// Klik op link sluit (handig mobiel)
document.addEventListener('click', (e) => {
  const a = e.target.closest('.sidebar a');
  if (a) closeSidebar();
});
</script>

<!-- ======================================================================= -->
<!-- DARK MODE                                                               -->
<!-- ======================================================================= -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const body = document.body;
    const toggle = document.getElementById("darkModeToggle");

    if (toggle && localStorage.getItem("darkMode") === "enabled") {
        body.classList.add("dark-mode");
        toggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
    }

    toggle?.addEventListener("click", () => {
        body.classList.toggle("dark-mode");
        const enabled = body.classList.contains("dark-mode");
        localStorage.setItem("darkMode", enabled ? "enabled" : "disabled");
        toggle.innerHTML = enabled
            ? '<i class="fa-solid fa-sun"></i>'
            : '<i class="fa-solid fa-moon"></i>';
    });
});
</script>

</body>
</html>
