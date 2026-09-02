<?php
/**
 * SIPUS - Riwayat Peminjaman (Siswa).
 * Hanya menampilkan transaksi milik siswa yang sedang login.
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

// ── Query riwayat: selalu di-scope ke id_anggota milik user (anti IDOR) ──
$stmt = db()->prepare('SELECT p.id_peminjaman, b.nomor_buku, b.judul, b.penulis,
                              p.tanggal_pinjam, p.tanggal_kembali, p.status
                       FROM peminjaman p
                       JOIN buku b ON b.id_buku = p.id_buku
                       WHERE p.id_anggota = :id
                       ORDER BY p.id_peminjaman DESC');
$stmt->execute([':id' => $idAnggota]);
$riwayat = $stmt->fetchAll();

$totalAktif   = 0;
$totalSelesai = 0;
foreach ($riwayat as $r) {
    if ($r['status'] === 'dipinjam')     { $totalAktif++; }
    else                                  { $totalSelesai++; }
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

<div class="mb-6">
  <h1 class="text-2xl font-bold">Riwayat Peminjaman</h1>
  <p class="text-sm text-slate-500">Semua transaksi peminjaman milikmu.</p>
</div>

<!-- Ringkasan -->
<div class="mb-6 grid grid-cols-3 gap-4">
  <div class="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-2xl font-bold text-slate-800"><?= count($riwayat) ?></p>
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
            <td class="px-5 py-3 text-slate-400"><?= $i + 1 ?></td>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>