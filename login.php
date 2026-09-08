<?php
/**
 * SIPUS - Halaman Login.
 * - password_verify() untuk cek hash Bcrypt
 * - session_regenerate_id(true) setelah login sukses (anti Session Fixation)
 * - Prepared statement PDO (anti SQL Injection)
 */
declare(strict_types=1);

// Mulai session jika belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. JALANKAN LOGIKA PHP & REDIRECT TERLEBIH DAHULU ──

// Sudah login? langsung arahkan ke dashboard sesuai role.
if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'siswa/dashboard.php'));
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil & sanitasi input
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Validasi server-side
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
        $error = 'Format username tidak valid (3-50 karakter, huruf/angka/titik/garis bawah).';
    } else {
        try {
            $stmt = db()->prepare('SELECT id_user, username, password, role FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Session hardening
                session_regenerate_id(true);

                $_SESSION['id_user']  = (int) $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

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

            $error = 'Username atau password salah.';

        } catch (PDOException $e) {
            error_log('[SIPUS] Login error: ' . $e->getMessage());
            $error = 'Terjadi gangguan pada server. Silakan coba lagi.';
        }
    }
}

// ── 2. PANGGIL HEADER HTML HANYA SETELAH TIDAK ADA LAGI PROSES REDIRECT ──
$hideNavbar = true;
$pageTitle  = 'Login — SIPUS';
require __DIR__ . '/includes/header.php';
?>

<style>
  /* Background gradasi biru soft menyatu dari atas sampai footer */
  html {
    height: 100%;
  }
  body {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #eef2ff 0%, #dbeafe 45%, #bfdbfe 100%);
  }

  body > main,
  body > .flex-1,
  body > div:not(footer):not(nav) {
    flex: 1 0 auto;
  }

  footer {
    flex-shrink: 0;
    margin-top: auto;
    background: linear-gradient(180deg, #ffff 0%, #ffff 100%) !important;
    color: #1e3a8a;
  }
  footer a {
    color: #1e40af;
  }

  .password-field-wrapper {
    position: relative;
  }
  .password-field-wrapper input {
    padding-right: 2.75rem;
  }
  .password-toggle-btn {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #64748b;
    border-radius: 0.375rem;
  }
  .password-toggle-btn:hover {
    color: #334155;
    background: #f1f5f9;
  }
  .password-toggle-btn:focus-visible {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
  }
  .password-toggle-btn svg {
    width: 1.15rem;
    height: 1.15rem;
    pointer-events: none;
  }
  .password-toggle-btn .icon-eye {
    display: none;
  }
  .password-toggle-btn[aria-pressed="true"] .icon-eye-off {
    display: none;
  }
  .password-toggle-btn[aria-pressed="true"] .icon-eye {
    display: block;
  }
</style>

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
      <div class="password-field-wrapper">
        <input type="password" id="password" name="password" required maxlength="100"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
               placeholder="Masukkan password">
        <button type="button"
                id="togglePassword"
                class="password-toggle-btn"
                aria-controls="password"
                aria-pressed="false"
                aria-label="Tampilkan password">
          <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
          <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.4 20.4 0 0 1 5.06-6.06M9.9 4.24A10.6 10.6 0 0 1 12 4c7 0 11 8 11 8a20.5 20.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <path d="M1 1l22 22"></path>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit"
            class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300">
      Masuk
    </button>
  </form>
</div>

<script>
  (function () {
    var toggleBtn = document.getElementById('togglePassword');
    var passwordInput = document.getElementById('password');

    if (!toggleBtn || !passwordInput) return;

    toggleBtn.addEventListener('click', function () {
      var isHidden = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isHidden ? 'text' : 'password');
      toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      toggleBtn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');

      passwordInput.focus();
      var val = passwordInput.value;
      passwordInput.setSelectionRange(val.length, val.length);
    });
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>