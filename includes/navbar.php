<?php
/**
 * SIPUS - Navbar dinamis.
 * Menu & nama user ditampilkan berdasarkan role session (RBAC).
 */
declare(strict_types=1);

// Guard: cegah direct access ke file partial ini
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$isAdmin = ($_SESSION['role'] === 'admin');

/*
 * Lokasi halaman:
 * /sipus/admin/...  -> prefix ../
 * /sipus/siswa/...  -> prefix ../
 */
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

if (
    str_contains($scriptName, '/admin/') ||
    str_contains($scriptName, '/siswa/')
) {
    $prefix = '../';
} else {
    $prefix = '';
}

// Item menu sesuai role
$menuItems = $isAdmin
    ? [
        [
            'url'   => $prefix . 'admin/dashboard.php',
            'label' => 'Dashboard',
            'icon'  => ''
        ],
        [
            'url'   => $prefix . 'admin/buku.php',
            'label' => 'Data Buku',
            'icon'  => ''
        ],
        [
            'url'   => $prefix . 'admin/anggota.php',
            'label' => 'Anggota',
            'icon'  => ''
        ],
        [
            'url'   => $prefix . 'admin/peminjaman.php',
            'label' => 'Peminjaman',
            'icon'  => ''
        ],
    ]
    : [
        [
            'url'   => $prefix . 'siswa/dashboard.php',
            'label' => 'Dashboard',
            'icon'  => ''
        ],
        [
            'url'   => $prefix . 'siswa/daftar_buku.php',
            'label' => 'Daftar Buku',
            'icon'  => ''
        ],
        [
            'url'   => $prefix . 'siswa/riwayat.php',
            'label' => 'Riwayat Pinjam',
            'icon'  => ''
        ],
    ];

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>

<style>
  /* ══════════════════ Popup konfirmasi logout (custom) ══════════════════ */
  .logout-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
    animation: logoutFadeIn .18s ease-out;
  }
  .logout-overlay.is-open { display: flex; }

  .logout-card {
    width: 100%;
    max-width: 380px;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 20px 45px -10px rgba(30, 41, 59, 0.35);
    overflow: hidden;
    animation: logoutPopIn .22s cubic-bezier(.34,1.56,.64,1);
  }

  .logout-card__banner {
    background: linear-gradient(135deg, #fb7185 0%, #be123c 100%);
    padding: 1.75rem 1.5rem 1.5rem;
    text-align: center;
    color: #fff;
    position: relative;
  }
  .logout-card__banner::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: -1px;
    height: 18px;
    background: #fff;
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
  }
  .logout-card__icon {
    width: 56px;
    height: 56px;
    margin: 0 auto .75rem;
    border-radius: 9999px;
    background: rgba(255,255,255,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .logout-card__icon svg { width: 28px; height: 28px; }

  .logout-card__title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
  }

  .logout-card__body {
    padding: 1.25rem 1.5rem 1.5rem;
    text-align: center;
  }
  .logout-card__judul {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    margin-bottom: .35rem;
  }
  .logout-card__sub {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1.4rem;
  }

  .logout-card__actions {
    display: flex;
    gap: .6rem;
  }
  .logout-card__actions button,
  .logout-card__actions a {
    flex: 1;
    border-radius: 12px;
    padding: .65rem 1rem;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
    border: none;
    display: inline-block;
    text-align: center;
    text-decoration: none;
  }
  .logout-btn-batal {
    background: #f1f5f9;
    color: #475569;
  }
  .logout-btn-batal:hover { background: #e2e8f0; }

  .logout-btn-ok {
    background: linear-gradient(135deg, #fb7185 0%, #be123c 100%);
    color: #fff;
    box-shadow: 0 8px 16px -4px rgba(190, 18, 60, .55);
  }
  .logout-btn-ok:hover { transform: translateY(-1px); box-shadow: 0 10px 20px -4px rgba(190, 18, 60, .65); color: #fff; }
  .logout-btn-ok:active { transform: translateY(0); }

  @keyframes logoutFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  @keyframes logoutPopIn {
    from { opacity: 0; transform: scale(.92) translateY(6px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
</style>

<nav class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">

    <!-- Logo -->
    <a href="<?= e($prefix . ($isAdmin ? 'admin/dashboard.php' : 'siswa/dashboard.php')) ?>"
       class="flex items-center gap-2 text-lg font-bold text-indigo-700">

      <span class="grid h-9 w-9 place-items-center rounded-lg bg-indigo-600 text-white">
        S
      </span>

      SIPUS
    </a>

    <!-- Menu desktop -->
    <div class="hidden items-center gap-1 md:flex">

      <?php foreach ($menuItems as $item): ?>

        <a href="<?= e($item['url']) ?>"
           class="rounded-lg px-3 py-2 text-sm font-medium transition
           <?= ($currentPage === basename($item['url']))
               ? 'bg-indigo-600 text-white'
               : 'text-slate-600 hover:bg-slate-100 hover:text-indigo-700' ?>">

          <span class="mr-1"><?= $item['icon'] ?></span>
          <?= e($item['label']) ?>

        </a>

      <?php endforeach; ?>

    </div>

    <!-- User info + logout -->
    <div class="flex items-center gap-3">

      <div class="text-right leading-tight">

        <p class="text-sm font-semibold">
          <?= e($_SESSION['nama'] ?? $_SESSION['username'] ?? 'User') ?>
        </p>

        <p class="text-xs uppercase tracking-wide text-indigo-600">
          <?= e($_SESSION['role']) ?>
        </p>

      </div>

      <!-- LOGOUT -->
      <a href="<?= e($prefix . 'logout.php') ?>"
         id="btnBukaLogout"
         class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">

        Keluar

      </a>

    </div>

  </div>

  <!-- Menu mobile -->
  <div class="flex gap-1 overflow-x-auto border-t border-slate-100 px-4 py-2 md:hidden">

    <?php foreach ($menuItems as $item): ?>

      <a href="<?= e($item['url']) ?>"
         class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition
         <?= ($currentPage === basename($item['url']))
             ? 'bg-indigo-600 text-white'
             : 'text-slate-600 hover:bg-slate-100' ?>">

        <?= e($item['label']) ?>

      </a>

    <?php endforeach; ?>

  </div>

</nav>

<!-- ══════════════════ Popup konfirmasi logout (custom, menggantikan confirm() bawaan) ══════════════════ -->
<div id="logoutOverlay" class="logout-overlay" role="dialog" aria-modal="true" aria-labelledby="logoutJudul">
  <div class="logout-card">
    <div class="logout-card__banner">
      <div class="logout-card__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </div>
      <div class="logout-card__title">Konfirmasi Keluar</div>
    </div>
    <div class="logout-card__body">
      <div id="logoutJudul" class="logout-card__judul">Apakah kamu yakin ingin keluar?</div>
      <div class="logout-card__sub">Kamu perlu login lagi untuk mengakses akunmu.</div>
      <div class="logout-card__actions">
        <button type="button" class="logout-btn-batal" id="logoutBatal">Batal</button>
        <a href="#" id="logoutOk" class="logout-btn-ok">Ya, Keluar</a>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var btnBuka  = document.getElementById('btnBukaLogout');
    var overlay  = document.getElementById('logoutOverlay');
    var btnOk    = document.getElementById('logoutOk');
    var btnBatal = document.getElementById('logoutBatal');

    if (!btnBuka) return;

    var tujuanLogout = btnBuka.getAttribute('href');
    btnOk.setAttribute('href', tujuanLogout);

    function bukaPopup(e) {
      e.preventDefault();
      overlay.classList.add('is-open');
    }
    function tutupPopup() {
      overlay.classList.remove('is-open');
    }

    btnBuka.addEventListener('click', bukaPopup);
    btnBatal.addEventListener('click', tutupPopup);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) tutupPopup();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) tutupPopup();
    });
  })();
</script>