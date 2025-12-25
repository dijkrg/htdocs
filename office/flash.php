<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function setFlash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = [
        'message' => $message,
        'type'    => $type
    ];
}

function showFlash(): void {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    $type  = $flash['type'] ?? 'info';

    if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
        $type = 'info';
    }

    // ✅ HTML toestaan
    $msg = $flash['message'] ?? '';

    // Kleurfallback (optioneel, wordt meestal via CSS gedaan)
    $bg = match ($type) {
        'success' => '#28a745',
        'error'   => '#dc3545',
        'warning' => '#ffc107',
        'info'    => '#17a2b8',
        default   => '#17a2b8'
    };

    echo '<div class="flash flash-' . $type . '" role="status" style="background:' . $bg . '">'
        . $msg .
        '</div>';

    unset($_SESSION['flash']);
}
