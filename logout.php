<?php
/**
 * SIPUS - Logout.
 * Hapus seluruh data session + cookie session dengan aman.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Kosongkan seluruh variabel session
$_SESSION = [];

// 2. Hapus cookie session di browser (jika ada)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}

// 3. Destroy session di server
session_destroy();

// 4. Arahkan ke halaman login
header('Location: login.php');
exit;
