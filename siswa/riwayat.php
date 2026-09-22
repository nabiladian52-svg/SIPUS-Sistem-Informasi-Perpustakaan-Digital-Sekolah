<?php
/**
 * SIPUS - Riwayat Peminjaman (Siswa).
 * Hanya menampilkan transaksi milik siswa yang sedang login.
 * Dilengkapi pagination (8 data per halaman) seperti halaman admin.
 */
declare(strict_types=1);

$pageTitle = 'Riwayat Peminjaman — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya siswa ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Location: ../login.php');
    exit;
}

// ── Ambil id_anggota milik user yang login ──
$stmt = db()->prepare('SELECT id_anggota FROM anggota WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $_SESSION['username']]);
$idAnggota = $stmt->fetchColumn();
if ($idAnggota === false) {
    header('Location: ../logout.php');
    exit;
}
$idAnggota = (int) $idAnggota;

// ── Ringkasan (dihitung dari SEMUA data, bukan hanya halaman aktif) ──
$stmt = db()->prepare("SELECT COUNT(*) AS total,
                              SUM(status = 'dipinjam') AS aktif
                       FROM peminjaman
                       WHERE id_anggota = :id");
$stmt->execute([':id' => $idAnggota]);
$sum          = $stmt->fetch();
$totalData    = (int) ($sum['total'] ?? 0);
$totalAktif   = (int) ($sum['aktif'] ?? 0);
$totalSelesai = $totalData - $totalAktif;

// ── Pagination ──
$perPage    = 8;
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page       = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page       = max(1, min($page, $totalPages));
$offset     = ($page - 1) * $perPage;

// ── Query riwayat: selalu di-scope ke id_anggota milik user (anti IDOR) ──
$stmt = db()->prepare('SELECT p.id_peminjaman, b.nomor_buku, b.judul, b.penulis,
                              p.tanggal_pinjam, p.tanggal_kembali, p.status
                       FROM peminjaman p
                       JOIN buku b ON b.id_buku = p.id_buku
                       WHERE p.id_anggota = :id
                       ORDER BY p.id_peminjaman DESC
                       LIMIT :limit OFFSET :offset');
$stmt->bindValue(':id', $idAnggota, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$riwayat = $stmt->fetchAll();

$dari   = $totalData === 0 ? 0 : $offset + 1;
$sampai = $offset + count($riwayat);

// ── Daftar nomor halaman dengan ellipsis: 1 2 … 4 ──
$pageList = [];
if ($totalPages > 1) {
    $set = array_unique(array_filter(
        [1, $page - 1, $page, $page + 1, $totalPages],
        fn ($p) => $p >= 1 && $p <= $totalPages
    ));
    sort($set);
    $prev = 0;
    foreach ($set as $p) {
        if ($p - $prev > 1) { $pageList[] = '...'; }
        $pageList[] = $p;
        $prev = $p;
    }
}
?>

<style>
  /* Background gradasi biru — sama persis seperti halaman login */
  html {
    height: 100%;
    background: linear-gradient(180deg, #eef2ff 0%, #dbeafe 45%, #bfdbfe 100%) !important;
  }
  body {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #eef2ff 0%, #dbeafe 45%, #bfdbfe 100%) !important;
  }

  /* Navbar semi-transparan agar menyatu dengan gradasi */
  body > nav,
  nav.bg-white,
  header nav {
    background: rgba(255, 255, 255, 0.55) !important;
    background-image: none !important;
    backdrop-filter: blur(6px);
  }

  /* Konten utama mengisi ruang kosong agar footer terdorong ke bawah viewport */
  body > main,
  body > .flex-1,
  body > div:not(footer):not(nav) {
    flex: 1 0 auto;
    background: transparent !important;
  }

  /* Footer — sama persis seperti halaman login */
  footer,
  body > footer {
    flex-shrink: 0;
    margin-top: auto;
    background: linear-gradient(180deg, #bfdbfe 0%, #93c5fd 100%) !important;
    background-image: linear-gradient(180deg, #ffff 0%, #ffff 100%) !important;
    color: #1e3a8a !important;
  }
  footer a {
    color: #1e40af !important;
  }
</style>

<style>
  /* ══════════════════ Tambahan: override background agar sama seperti dashboard siswa ══════════════════ */
  html, body {
    background: #bfdbfe !important;
    background-image: none !important;
  }
  footer,
  body > footer {
    background: #ffff !important;
    background-image: none !important;
  }
</style>

<div class="mb-6">
  <h1 class="text-2xl font-bold">Riwayat Peminjaman</h1>
  <p class="text-sm text-slate-500">Semua transaksi peminjaman milikmu.</p>
</div>

<!-- Ringkasan -->
<div class="mb-6 grid grid-cols-3 gap-4">
  <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-2xl font-bold text-slate-800"><?= $totalData ?></p>
    <p class="text-xs font-medium text-slate-500">Total Transaksi</p>
  </div>
  <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-2xl font-bold text-amber-600"><?= $totalAktif ?></p>
    <p class="text-xs font-medium text-slate-500">Sedang Dipinjam</p>
  </div>
  <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-2xl font-bold text-emerald-600"><?= $totalSelesai ?></p>
    <p class="text-xs font-medium text-slate-500">Sudah Dikembalikan</p>
  </div>
</div>

<!-- Tabel riwayat -->
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-5 py-3">#</th>
          <th class="px-5 py-3">Buku</th>
          <th class="px-5 py-3">Penulis</th>
          <th class="px-5 py-3">Tgl Pinjam</th>
          <th class="px-5 py-3">Tgl Kembali</th>
          <th class="px-5 py-3">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (count($riwayat) === 0): ?>
          <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Belum ada riwayat peminjaman.</td></tr>
        <?php endif; ?>
        <?php foreach ($riwayat as $i => $row): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 text-slate-400"><?= $offset + $i + 1 ?></td>
            <td class="px-5 py-3">
              <p class="font-medium"><?= e($row['judul']) ?></p>
              <p class="font-mono text-xs text-slate-400"><?= e($row['nomor_buku']) ?></p>
            </td>
            <td class="px-5 py-3 text-slate-500"><?= e($row['penulis']) ?></td>
            <td class="px-5 py-3"><?= e($row['tanggal_pinjam']) ?></td>
            <td class="px-5 py-3"><?= e($row['tanggal_kembali'] ?? '—') ?></td>
            <td class="px-5 py-3">
              <?php if ($row['status'] === 'dipinjam'): ?>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
              <?php else: ?>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dikembalikan</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="mt-6 flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>"
         class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
        « Sebelumnya
      </a>
    <?php else: ?>
      <span class="cursor-not-allowed rounded-lg bg-white/60 px-3 py-2 text-sm font-medium text-slate-300 ring-1 ring-slate-200">
        « Sebelumnya
      </span>
    <?php endif; ?>

    <?php foreach ($pageList as $p): ?>
      <?php if ($p === '...'): ?>
        <span class="px-2 text-slate-400">…</span>
      <?php elseif ($p === $page): ?>
        <span class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm">
          <?= $p ?>
        </span>
      <?php else: ?>
        <a href="?page=<?= $p ?>"
           class="rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
          <?= $p ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>"
         class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
        Berikutnya »
      </a>
    <?php else: ?>
      <span class="cursor-not-allowed rounded-lg bg-white/60 px-3 py-2 text-sm font-medium text-slate-300 ring-1 ring-slate-200">
        Berikutnya »
      </span>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php if ($totalData > 0): ?>
  <p class="mt-3 text-center text-xs text-slate-500">
    Menampilkan <?= $dari ?>–<?= $sampai ?> dari <?= $totalData ?> transaksi
  </p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>