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
            'icon'  => '📊'
        ],
        [
            'url'   => $prefix . 'admin/buku.php',
            'label' => 'Data Buku',
            'icon'  => '📚'
        ],
        [
            'url'   => $prefix . 'admin/anggota.php',
            'label' => 'Anggota',
            'icon'  => '👥'
        ],
        [
            'url'   => $prefix . 'admin/peminjaman.php',
            'label' => 'Peminjaman',
            'icon'  => '🔄'
        ],
    ]
    : [
        [
            'url'   => $prefix . 'siswa/dashboard.php',
            'label' => 'Dashboard',
            'icon'  => '📊'
        ],
        [
            'url'   => $prefix . 'siswa/daftar_buku.php',
            'label' => 'Daftar Buku',
            'icon'  => '📚'
        ],
        [
            'url'   => $prefix . 'siswa/riwayat.php',
            'label' => 'Riwayat Pinjam',
            'icon'  => '🕘'
        ],
    ];

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>

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
         class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"
         onclick="return confirm('Apakah kamu yakin ingin keluar?');">

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