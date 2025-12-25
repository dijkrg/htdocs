<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// Basispad bepalen (voor submappen zoals /mail/, /systeembeheer/, /import/, /planboard/, /magazijn/, /leveranciers/)
$basePath = rtrim(str_replace(
    ['/mail', '/systeembeheer', '/import', '/planboard', '/magazijn', '/leveranciers'],
    '',
    dirname($_SERVER['SCRIPT_NAME'])
), '/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Office ABCB') ?></title>
    <link rel="stylesheet" href="<?= $basePath ?>/template/style.css">
    <link rel="icon" href="/template/favicon.ico">
    <link rel="apple-touch-icon" href="/template/ABCBFAV.png">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="manifest" href="/manifest.json">
<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/service-worker.js");
}
</script>

    <style>
        /* 🔹 Uitklapbaar submenu (Instellingen) */
        .submenu {
            margin-top: 10px;
            border-top: 1px solid #e5e7eb;
        }

        .submenu-toggle {
            width: 100%;
            background: none;
            border: none;
            font-size: 15px;
            color: var(--sidebar-link, #333);
            padding: 12px 20px;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }
        .submenu-toggle:hover {
            background: var(--sidebar-hover, #e2e8f0);
        }
        .toggle-icon {
            font-weight: bold;
            font-size: 16px;
        }

        .submenu-list {
            list-style: none;
            padding-left: 20px;
            margin: 0;
            display: none; /* standaard dicht */
        }
        .submenu-list li a {
            display: block;
            padding: 8px 15px;
            color: var(--sidebar-link, #333);
            font-size: 14px;
            border-radius: 4px;
        }
        .submenu-list li a:hover {
            background: var(--sidebar-hover, #e2e8f0);
        }

        /* Als submenu open is */
        .submenu.open .submenu-list {
            display: block;
        }

html, body {
    overflow-x: hidden;
    width: 100%;
    max-width: 100%;
}
body.login-body {
    touch-action: manipulation;
    overscroll-behavior: contain;
}
.login-card {
    max-width: 380px;
    margin: auto;
}
    </style>
</head>

<body>

<!-- 🔹 Topbar -->
<div class="topbar">
    <div class="topbar-left">
        <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fa-solid fa-bars"></i>
        </button>
        <img src="<?= $basePath ?>/template/ABCBFAV.png" alt="Logo" class="logo-header">
    </div>
    <div class="topbar-right">
        <?php if (!empty($_SESSION['user'])): ?>
            <span>👤 <?= htmlspecialchars($_SESSION['user']['voornaam'] ?? '') ?> <?= htmlspecialchars($_SESSION['user']['achternaam'] ?? '') ?></span>
            <button id="darkModeToggle" class="dark-toggle"><i class="fa-solid fa-moon"></i></button>
            <a href="<?= $basePath ?>/profiel.php"><i class="fa-solid fa-user"></i> Mijn Profiel</a>
            <a href="<?= $basePath ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Uitloggen</a>
        <?php else: ?>
            <button id="darkModeToggle" class="dark-toggle"><i class="fa-solid fa-moon"></i></button>
            <a href="<?= $basePath ?>/login.php"><i class="fa-solid fa-right-to-bracket"></i> Inloggen</a>
        <?php endif; ?>
    </div>
</div>

<div class="layout">
    <!-- 🔹 Sidebar -->
    <aside class="sidebar" id="sidebar">
        <nav>
            <a href="<?= $basePath ?>/index.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a href="<?= $basePath ?>/planboard/planboard.php"><i class="fa-solid fa-calendar-days"></i> <span>Planboard</span></a>
            <a href="<?= $basePath ?>/klanten.php"><i class="fa-solid fa-users"></i> <span>Klanten</span></a>
            <a href="<?= $basePath ?>/werkbonnen.php"><i class="fa-solid fa-file-lines"></i> <span>Werkbonnen</span></a>
            <a href="<?= $basePath ?>/artikelen.php"><i class="fa-solid fa-box"></i> <span>Artikelen</span></a>
            <a href="<?= $basePath ?>/magazijn/index.php"><i class="fa-solid fa-warehouse"></i> <span>Magazijn</span></a>
            <a href="<?= $basePath ?>/contracten.php"><i class="fa-solid fa-file-contract"></i> <span>Contracten</span></a>
            <a href="<?= $basePath ?>/objecten.php"><i class="fa-solid fa-cubes"></i> <span>Objecten</span></a>

            <?php if (!empty($_SESSION['user']) && in_array($_SESSION['user']['rol'], ['Admin','Manager'])): ?>
            <!-- ⚙️ Instellingen submenu -->
            <div class="submenu">
                <button class="submenu-toggle" onclick="toggleSubmenu(this)">
                    <i class="fa-solid fa-gear"></i> Instellingen
                    <span class="toggle-icon">+</span>
                </button>
                <ul class="submenu-list">
                    <li><a href="<?= $basePath ?>/systeembeheer/bedrijfsgegevens.php"><i class="fa-solid fa-building"></i> Bedrijfsgegevens</a></li>
                    <li><a href="<?= $basePath ?>/medewerkers.php"><i class="fa-solid fa-id-card"></i> Medewerkers</a></li>
                    <li><a href="<?= $basePath ?>/systeemrechten.php"><i class="fa-solid fa-shield-halved"></i> Systeemrechten</a></li>
                    <li><a href="<?= $basePath ?>/systeembeheer/uursoorten.php"><i class="fa-solid fa-clock"></i> Uursoorten</a></li>
                    <li><a href="<?= $basePath ?>/systeembeheer/type_werkzaamheden.php"><i class="fa-solid fa-file-import"></i> Type werkzaamheden</a></li>
                    <li><a href="<?= $basePath ?>/systeembeheer/categorieen.php"><i class="fa-solid fa-tags"></i> Categorieën</a></li>
		    <li><a href="<?= $basePath ?>/systeembeheer/object_status.php"><i class="fa-solid fa-traffic-light"></i> Objectstatus</a></li>
                    <li><a href="<?= $basePath ?>/magazijn/magazijnen.php"><i class="fa-solid fa-warehouse"></i> Magazijnbeheer</a></li>
                    <li><a href="<?= $basePath ?>/systeembeheer/pdf_instellingen.php"><i class="fa-solid fa-file-pdf"></i> PDF-instellingen</a></li>
                    <li><a href="<?= $basePath ?>/mail/mail_log.php"><i class="fa-solid fa-envelope"></i> Mail log</a></li>
                    <li><a href="<?= $basePath ?>/import/index.php"><i class="fa-solid fa-file-import"></i> Importeren</a></li>
                    <li class="submenu-section-title">Contractenbeheer</li>
                    <li><a href="<?= $basePath ?>/systeembeheer/contract_types.php">
                    <i class="fa-solid fa-file-contract"></i> Contracttypen
                    </a></li>

                    <li><a href="<?= $basePath ?>/systeembeheer/contract_onderdeel_types.php">
                    <i class="fa-solid fa-check-square"></i> Onderhoudsonderdelen
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <button onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
                <i class="fa-solid fa-angles-left"></i>
            </button>
        </div>
    </aside>

    <!-- 🔹 Content -->
    <main class="content">
        <?php showFlash(); ?>
        <?= $content ?? '' ?>
    </main>
</div>

<!-- 🔹 Scripts -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 🌙 Dark mode toggle
    const body = document.body;
    const toggle = document.getElementById("darkModeToggle");

    if (localStorage.getItem("darkMode") === "enabled") {
        body.classList.add("dark-mode");
        toggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
    }

    toggle.addEventListener("click", () => {
        body.classList.toggle("dark-mode");
        if (body.classList.contains("dark-mode")) {
            localStorage.setItem("darkMode", "enabled");
            toggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
        } else {
            localStorage.setItem("darkMode", "disabled");
            toggle.innerHTML = '<i class="fa-solid fa-moon"></i>';
        }
    });

    // 🔔 Flash-meldingen automatisch laten verdwijnen
    document.querySelectorAll(".flash").forEach(flash => {
        const timeout = flash.classList.contains("flash-error") ? 7000 : 4000;
        setTimeout(() => {
            flash.style.transition = "opacity 0.5s ease";
            flash.style.opacity = "0";
            setTimeout(() => flash.remove(), 600);
        }, timeout);
    });

    // 🔍 Artikel zoekfunctionaliteit (alleen als aanwezig)
    const input = document.getElementById('artikel_zoek');
    const hidden = document.getElementById('artikel_id');
    const resultaten = document.getElementById('zoek_resultaten');
    let timeout = null;

    if (input && resultaten) {
        input.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(timeout);

            if (query.length < 2) {
                resultaten.style.display = 'none';
                return;
            }

            timeout = setTimeout(() => {
                fetch('/magazijn/artikel_search.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        resultaten.innerHTML = '';
                        if (data.length === 0) {
                            resultaten.innerHTML = '<div>Geen resultaten</div>';
                        } else {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.textContent = item.text;
                                div.dataset.id = item.id;
                                resultaten.appendChild(div);
                            });
                        }
                        resultaten.style.display = 'block';
                    })
                    .catch(() => {
                        resultaten.innerHTML = '<div>Fout bij zoeken</div>';
                        resultaten.style.display = 'block';
                    });
            }, 300);
        });

        resultaten.addEventListener('click', e => {
            if (e.target && e.target.dataset.id) {
                hidden.value = e.target.dataset.id;
                input.value = e.target.textContent;
                resultaten.style.display = 'none';
            }
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('.artikel-zoek-container')) {
                resultaten.style.display = 'none';
            }
        });
    }
});

// ⚙️ Uitklapbaar submenu (Instellingen)
function toggleSubmenu(button) {
    const submenu = button.closest('.submenu');
    const icon = button.querySelector('.toggle-icon');
    const isOpen = submenu.classList.toggle('open');
    icon.textContent = isOpen ? '−' : '+';
}
</script>

</body>
</html>
