<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM mail_log WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$mail = $res->fetch_assoc();
$stmt->close();

if (!$mail) {
    die("Mail log niet gevonden.");
}

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<div class="page-header">
    <h2>Mail log detail</h2>
    <a href="mail_log.php" class="btn btn-secondary">⬅ Terug</a>
</div>

<div class="card">
    <table class="detail-table">
        <tr><th>Afzender</th><td><?= e($mail['afzender']) ?></td></tr>
        <tr><th>Ontvanger</th><td><?= e($mail['ontvanger']) ?></td></tr>
        <tr><th>Onderwerp</th><td><?= e($mail['onderwerp']) ?></td></tr>

        <!-- ⭐ HTML mailbericht netjes tonen -->
        <tr>
            <th>Bericht</th>
            <td>
                <div class="email-preview">
                    <?= $mail['bericht'] ?>
                </div>
            </td>
        </tr>

        <tr><th>Verzonden op</th><td><?= e($mail['verzonden_op']) ?></td></tr>

        <tr>
            <th>Status</th>
            <td>
                <?php if (stripos($mail['status'], 'success') !== false || stripos($mail['status'], 'verzonden') !== false): ?>
                    <span class="badge badge-success">Verzonden</span>
                <?php else: ?>
                    <span class="badge badge-error">Fout</span><br>
                    <small><?= e($mail['status']) ?></small>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<style>
.email-preview {
    background: #fff;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    font-size: 14px;
    line-height: 1.5;
    white-space: normal;
}

.email-preview p {
    margin: 10px 0;
}

.email-preview a {
    color: #2954cc;
    font-weight: bold;
}

.detail-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px; /* EXTRA RUIMTE TUSSEN REGELS */
}

.detail-table th {
    width: 180px;
    text-align: left;      /* LINKS UITLIJNEN */
    padding: 10px 5px;
    font-weight: bold;
    vertical-align: top;
    color: #333;
}

.detail-table td {
    padding: 10px 10px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    vertical-align: top;
}

.detail-table tr {
    margin-bottom: 14px;
}
</style>

<?php
$content = ob_get_clean();
$pageTitle = "Mail log detail";
include __DIR__ . '/../template/template.php';
