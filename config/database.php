<?php
/**
 * SIPUS - Konfigurasi & Koneksi Database (PDO)
 * PDO + ERRMODE_EXCEPTION agar error query selalu terdeteksi.
 */

declare(strict_types=1);

// ─── Konfigurasi koneksi (sesuaikan dengan environment lokal) ───
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'perpustakaan');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Mendapatkan koneksi PDO tunggal (singleton).
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lempar exception saat error SQL
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // hasil berupa array asosiatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement native (anti SQL Injection)
            ]);
        } catch (PDOException $e) {
            // Jangan bocorkan detail error ke layar user.
            error_log('[SIPUS] Koneksi database gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Maaf, terjadi gangguan pada server database. Silakan coba beberapa saat lagi.');
        }
    }

    return $pdo;
}

/**
 * Helper escape output (XSS Protection).
 * Semua variabel yang dicetak ke HTML wajib melewati fungsi ini.
 *
 * @param mixed $data
 * @return string
 */
function e($data): string
{
    return htmlspecialchars((string) $data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validasi: apakah string adalah YEAR valid (1901-2155)?
 *
 * @param mixed $year
 * @return bool
 */
function valid_tahun($year): bool
{
    if (!ctype_digit((string) $year)) {
        return false;
    }
    $y = (int) $year;
    return $y >= 1901 && $y <= 2155;
}
