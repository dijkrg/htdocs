<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

$res = $conn->query("SELECT * FROM mail_log ORDER BY verzonden_op DESC");

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<div class="page-header">
    <h2>Mail log</h2>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Afzender</th>
                <th>Ontvanger</th>
                <th>Onderwerp</th>
                <th>Verzonden op</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= e($row['id']) ?></td>
                    <td><?= e($row['afzender']) ?></td>
                    <td><?= e($row['ontvanger']) ?></td>
                    <td><?= e($row['onderwerp']) ?></td>
                    <td><?= e($row['verzonden_op']) ?></td>
                    <td>
                        <?php if (
                            stripos($row['status'], 'success') !== false ||
                            stripos($row['status'], 'verzonden') !== false
                        ): ?>
                            <span class="badge badge-success">Verzonden</span>
                        <?php else: ?>
                            <span class="badge badge-error">Fout</span>
                        <?php endif; ?>
                    </td>

                    <td class="actions">
                        <a href="mail_log_detail.php?id=<?= $row['id'] ?>" title="Details">📄</a>
                    </td>

                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7"><i>Geen mails gevonden.</i></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
$pageTitle = "Mail log";
include __DIR__ . '/../template/template.php';
