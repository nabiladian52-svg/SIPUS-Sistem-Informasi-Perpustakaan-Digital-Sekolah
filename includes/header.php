<?php
/**
 * SIPUS - Header global (Tailwind CDN + Meta Tag).
 * Variabel opsional sebelum include:
 *   $pageTitle  = judul tab browser
 *   $hideNavbar = true  (untuk halaman login)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Konfigurasi session yang aman
    session_set_cookie_params([
        'httponly' => true,  // cookie tidak bisa dibaca JavaScript (mitigasi XSS pencurian session)
        'samesite' => 'Lax', // mitigasi CSRF dasar
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle  = $pageTitle ?? 'SIPUS — Sistem Informasi Perpustakaan Digital Sekolah';
$hideNavbar = $hideNavbar ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="SIPUS — Sistem Informasi Perpustakaan Digital Sekolah">
  <title><?= e($pageTitle) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<?php if (!$hideNavbar): ?>
  <?php require __DIR__ . '/navbar.php'; ?>
  <main class="mx-auto w-full max-w-6xl px-4 py-8">
<?php else: ?>
  <main class="mx-auto w-full max-w-md px-4 py-16">
<?php endif; ?>
