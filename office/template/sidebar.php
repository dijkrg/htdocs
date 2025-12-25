<?php
$rol = $_SESSION['user']['rol'] ?? '';
?>

<nav>

<?php if (in_array($rol, ['Admin', 'Manager', 'Planning'])): ?>
    <!-- ❇ ADMIN / MANAGER / PLANNING: volledige sidebar -->
    
    <a href="/planboard/planboard.php"><i class="fa-solid fa-calendar-days"></i> Planboard</a>
    <a href="/klanten.php"><i class="fa-solid fa-users"></i> Klanten</a>
    <a href="/werkbonnen.php"><i class="fa-solid fa-file-lines"></i> Werkbonnen</a>
    <a href="/artikelen.php"><i class="fa-solid fa-box"></i> Artikelen</a>
    <a href="/contracten.php"><i class="fa-solid fa-file-contract"></i> Contracten</a>
    <a href="/objecten.php"><i class="fa-solid fa-cubes"></i> Objecten</a>

    <!-- Instellingen submenu -->
    <div class="submenu">
        <button class="submenu-toggle" onclick="toggleSubmenu(this)">
            <i class="fa-solid fa-gear"></i> Instellingen
            <span class="toggle-icon">+</span>
        </button>
        <ul class="submenu-list">
            <li><a href="/systeembeheer/bedrijfsgegevens.php"><i class="fa-solid fa-building"></i> Bedrijfsgegevens</a></li>
            <li><a href="/systeembeheer/uursoorten.php"><i class="fa-solid fa-clock"></i> Uursoorten</a></li>
            <li><a href="/systeembeheer/type_werkzaamheden.php"><i class="fa-solid fa-file"></i> Werkzaamheden</a></li>
            <li><a href="/systeembeheer/categorieen.php"><i class="fa-solid fa-tags"></i> Categorieën</a></li>
            <li><a href="/systeembeheer/object_status.php"><i class="fa-solid fa-traffic-light"></i> Objectstatus</a></li>
            <li><a href="/systeembeheer/pdf_instellingen.php"><i class="fa-solid fa-file-pdf"></i> PDF Instellingen</a></li>
            <li><a href="/systeembeheer/systeemrechten.php"><i class="fa-solid fa-shield-halved"></i> Systeemrechten</a></li>
            <li><a href="/medewerkers.php"><i class="fa-solid fa-id-card"></i> Medewerkers</a></li>
            <li><a href="/mail/mail_log.php"><i class="fa-solid fa-envelope"></i> Mail Log</a></li>
        </ul>
    </div>

<?php elseif ($rol === 'Monteur'): ?>
    <!-- 🔧 MONTEUR SIDEBAR -->
    
    <a href="/monteur_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="/monteur_werkbon.php"><i class="fa-solid fa-file-lines"></i> Mijn Werkbonnen</a>

<?php else: ?>
    <!-- fallback (optioneel) -->
    <p style="color:#aaa; padding:10px;">Geen menu beschikbaar</p>
<?php endif; ?>

</nav>
