<?php
/**
 * SIPUS - Entry point.
 * Redirect ke dashboard sesuai status & role session, atau ke login.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Belum login -> halaman login
if (!isset($_SESSION['role'])) {
    header('Location: index_landing.php');
    exit;
}

// Sudah login -> dashboard sesuai role
header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'siswa/dashboard.php'));
exit;
