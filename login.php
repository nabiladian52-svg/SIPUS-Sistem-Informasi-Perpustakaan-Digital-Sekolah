<?php
/**
 * SIPUS - Halaman Login
 */

declare(strict_types=1);

// Aktifkan output buffering SEBELUM ada output apa pun
ob_start();

// Mulai session sebelum include file lain
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi database
require_once __DIR__ . '/includes/database.php';

// Kalau sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: siswa/dashboard.php');
    }
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Validasi input
    if ($username === '' || $password === '') {

        $error = 'Username dan password wajib diisi.';

    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {

        $error = 'Format username tidak valid (3-50 karakter, huruf/angka/titik/garis bawah).';

    } else {

        try {

            // Cari user berdasarkan username
            $stmt = db()->prepare(
                'SELECT id_user, username, password, role
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );

            $stmt->execute([
                ':username' => $username
            ]);

            $user = $stmt->fetch();

            // Cek username dan password
            if ($user && password_verify($password, $user['password'])) {

                // Regenerate session untuk keamanan
                session_regenerate_id(true);

                // Simpan data user ke session
                $_SESSION['id_user'] = (int) $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Jika siswa, ambil nama dari tabel anggota
                if ($user['role'] === 'siswa') {

                    $stmtA = db()->prepare(
                        'SELECT nama
                         FROM anggota
                         WHERE username = :username
                         LIMIT 1'
                    );

                    $stmtA->execute([
                        ':username' => $user['username']
                    ]);

                    $anggota = $stmtA->fetch();

                    $_SESSION['nama'] =
                        $anggota['nama'] ?? $user['username'];

                } else {

                    // Nama untuk admin
                    $_SESSION['nama'] = 'Administrator';
                }

                // Redirect berdasarkan role
                if ($user['role'] === 'admin') {

                    header('Location: admin/dashboard.php');

                } else {

                    header('Location: siswa/dashboard.php');
                }

                exit;
            }

            // Login gagal
            $error = 'Username atau password salah.';

        } catch (PDOException $e) {

            // Simpan error ke log, jangan tampilkan detail database ke user
            error_log('[SIPUS] Login error: ' . $e->getMessage());

            $error = 'Terjadi gangguan pada server. Silakan coba lagi.';
        }
    }
}

// Pengaturan halaman
$hideNavbar = true;
$pageTitle = 'Login — SIPUS';

// Header halaman
require __DIR__ . '/includes/header.php';
?>

<div class="flex min-h-[70vh] items-center justify-center">

    <div class="login-card">

        <div class="login-header">
            <h1>SIPUS</h1>
            <p>Sistem Informasi Perpustakaan Digital Sekolah</p>
        </div>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                Login
            </button>

        </form>

    </div>

</div>

<?php
// Footer halaman
require __DIR__ . '/includes/footer.php';

// Akhiri output buffering
ob_end_flush();
?>