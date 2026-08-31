<?php
/**
 * SIPUS - Halaman Login.
 * - password_verify() untuk cek hash Bcrypt
 * - session_regenerate_id(true) setelah login sukses (anti Session Fixation)
 * - Prepared statement PDO (anti SQL Injection)
 */
declare(strict_types=1);

$hideNavbar = true;
$pageTitle  = 'Login — SIPUS';
require __DIR__ . '/includes/header.php';

// Sudah login? langsung arahkan ke dashboard sesuai role.
if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'siswa/dashboard.php'));
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── 1. Ambil & sanitasi input ──
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // ── 2. Validasi server-side: tidak boleh kosong ──
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
        $error = 'Format username tidak valid (3-50 karakter, huruf/angka/titik/garis bawah).';
    } else {
        // ── 3. Ambil user via prepared statement ──
        try {
            $stmt = db()->prepare('SELECT id_user, username, password, role FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // ── 4. Verifikasi password hash + anti user-enumeration ──
            if ($user && password_verify($password, $user['password'])) {
                // Session hardening: ID session baru (anti fixation)
                session_regenerate_id(true);

                $_SESSION['id_user']  = (int) $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                // Ambil nama anggota untuk ditampilkan di navbar (jika siswa)
                if ($user['role'] === 'siswa') {
                    $stmtA = db()->prepare('SELECT nama FROM anggota WHERE username = :u LIMIT 1');
                    $stmtA->execute([':u' => $user['username']]);
                    $anggota = $stmtA->fetch();
                    $_SESSION['nama'] = $anggota['nama'] ?? $user['username'];
                } else {
                    $_SESSION['nama'] = 'Administrator';
                }

                header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'siswa/dashboard.php'));
                exit;
            }

            // Pesan generik agar penyerang tidak tahu user mana yang ada
            $error = 'Username atau password salah.';

        } catch (PDOException $e) {
            error_log('[SIPUS] Login error: ' . $e->getMessage());
            $error = 'Terjadi gangguan pada server. Silakan coba lagi.';
        }
    }
}
?>

<div class="rounded-2xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
  <div class="mb-6 text-center">
    <span class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-indigo-600 text-2xl font-bold text-white">S</span>
    <h1 class="text-2xl font-bold text-slate-800">SIPUS</h1>
    <p class="mt-1 text-sm text-slate-500">Sistem Informasi Perpustakaan Digital Sekolah</p>
  </div>

  <?php if ($error !== ''): ?>
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="login.php" class="space-y-4" autocomplete="off">
    <div>
      <label for="username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
      <input type="text" id="username" name="username" required maxlength="50"
             value="<?= e($username) ?>"
             class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
             placeholder="Masukkan username">
    </div>

    <div>
      <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
      <input type="password" id="password" name="password" required maxlength="100"
             class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
             placeholder="Masukkan password">
    </div>

    <button type="submit"
            class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300">
      Masuk
    </button>
  </form>

  <div class="mt-6 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
    <p class="font-semibold text-slate-600">Akun demo:</p>
    <p>Admin: <code class="text-indigo-600">admin</code> / <code class="text-indigo-600">admin123</code></p>
    <p>Siswa: <code class="text-indigo-600">siswa01</code> / <code class="text-indigo-600">siswa123</code></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
