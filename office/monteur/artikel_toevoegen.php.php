<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';
requireLogin();
requireRole(['Monteur']);

$monteur_id = (int)$_SESSION['user']['id'];
$werkbon_id = (int)($_GET['werkbon_id'] ?? 0);

if ($werkbon_id <= 0) {
    setFlash("Ongeldige werkbon.", "error");
    header("Location: /monteur/index.php");
    exit;
}

/* ------------------------------------------------------------
   CHECK OF WERKBON BIJ MONTEUR HOORT
------------------------------------------------------------ */
$chk = $conn->prepare("
    SELECT werkbon_id 
    FROM werkbonnen 
    WHERE werkbon_id=? AND monteur_id=?
");
$chk->bind_param("ii", $werkbon_id, $monteur_id);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    setFlash("Geen toegang tot deze werkbon.", "error");
    header("Location: /monteur/index.php");
    exit;
}

/* ------------------------------------------------------------
   OPSLAAN FORMULIER
------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $artikel_id = (int)$_POST['artikel_id'];
    $aantal     = (float)$_POST['aantal'];

    if ($artikel_id <= 0 || $aantal <= 0) {
        setFlash("Vul alle velden correct in.", "error");
    } else {

        // prijs ophalen
        $stm = $conn->prepare("SELECT verkoopprijs FROM artikelen WHERE artikel_id=?");
        $stm->bind_param("i", $artikel_id);
        $stm->execute();
        $prijs = $stm->get_result()->fetch_assoc()['verkoopprijs'] ?? 0;

        // artikel koppelen
        $ins = $conn->prepare("
            INSERT INTO werkbon_artikelen (werkbon_id, artikel_id, aantal, verkoopprijs)
            VALUES (?, ?, ?, ?)
        ");
        $ins->bind_param("iidd", $werkbon_id, $artikel_id, $aantal, $prijs);
        $ins->execute();

        setFlash("Artikel toegevoegd aan werkbon.", "success");
        header("Location: /monteur/werkbon_view.php?id=" . $werkbon_id . "#artikelen");
        exit;
    }
}

$pageTitle = "Artikel toevoegen";
ob_start();
?>

<style>
.search-box { position:relative; }
.search-results {
    position:absolute;
    top:40px;
    left:0;
    right:0;
    background:#fff;
    border:1px solid #ddd;
    border-radius:6px;
    max-height:250px;
    overflow:auto;
    z-index:50;
    display:none;
}
.search-item {
    padding:8px 10px;
    border-bottom:1px solid #eee;
}
.search-item:hover {
    background:#f0f0f0;
    cursor:pointer;
}
</style>

<h2>➕ Artikel toevoegen</h2>

<div class="card">
<form method="post">

    <label>Zoek artikel *</label>
    <div class="search-box">
        <input type="text" id="artikelSearch" placeholder="Zoek op naam of nummer...">
        <div id="searchResults" class="search-results"></div>
    </div>

    <label>Geselecteerd artikel</label>
    <input type="text" id="artDisplay" readonly>

    <input type="hidden" name="artikel_id" id="artikel_id">

    <label>Aantal *</label>
    <input type="number" step="0.01" name="aantal" required>

    <button class="btn-primary" style="margin-top:15px;width:100%;">✔ Opslaan</button>

</form>
</div>

<a href="/monteur/werkbon_view.php?id=<?= $werkbon_id ?>" class="btn btn-secondary" style="width:100%;">⬅ Terug</a>

<script>
let searchBox = document.getElementById("artikelSearch");
let results   = document.getElementById("searchResults");

searchBox.addEventListener("input", function(){
    let q = this.value.trim();
    if (q.length < 2){
        results.style.display = "none";
        return;
    }

    fetch("/monteur/api/artikel_search.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            results.innerHTML = "";
            results.style.display = "block";

            data.forEach(a => {
                let div = document.createElement("div");
                div.className = "search-item";
                div.textContent = a.artikelnummer + " — " + a.naam;
                div.onclick = function(){
                    document.getElementById("artikel_id").value = a.artikel_id;
                    document.getElementById("artDisplay").value = a.artikelnummer + " — " + a.naam;
                    results.style.display = "none";
                };
                results.appendChild(div);
            });
        });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/template/monteur_template.php';
?>
