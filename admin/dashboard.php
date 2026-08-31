<?php
/**
 * SIPUS - Dashboard Admin.
 * Ringkasan total statistik perpustakaan + daftar transaksi terbaru.
 */
declare(strict_types=1);

$pageTitle = 'Dashboard Admin — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya admin ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = db();

// ── Statistik ringkas (prepared statement) ──
$stmt = $pdo->prepare('SELECT
    (SELECT COUNT(*) FROM buku)              AS total_buku,
    (SELECT COUNT(*) FROM buku WHERE status = "tersedia")  AS buku_tersedia,
    (SELECT COUNT(*) FROM buku WHERE status = "dipinjam")  AS buku_dipinjam,
    (SELECT COUNT(*) FROM anggota)           AS total_anggota,
    (SELECT COUNT(*) FROM peminjaman WHERE status = "dipinjam")    AS pinjam_aktif,
    (SELECT COUNT(*) FROM peminjaman WHERE status = "dikembalikan") AS pinjam_selesai');
$stmt->execute();
$stat = $stmt->fetch();

// ── 5 transaksi terbaru ──
$stmtLatest = $pdo->prepare('SELECT p.id_peminjaman, a.nomor_anggota, a.nama, b.judul,
                                    p.tanggal_pinjam, p.tanggal_kembali, p.status
                             FROM peminjaman p
                             JOIN anggota a ON a.id_anggota = p.id_anggota
                             JOIN buku    b ON b.id_buku    = p.id_buku
                             ORDER BY p.id_peminjaman DESC
                             LIMIT 5');
$stmtLatest->execute();
$terbaru = $stmtLatest->fetchAll();

$cards = [
    ['label' => 'Total Buku',        'value' => $stat['total_buku'],      'icon' => '📚', 'color' => 'bg-indigo-600'],
    ['label' => 'Buku Tersedia',     'value' => $stat['buku_tersedia'],  'icon' => '✅', 'color' => 'bg-emerald-600'],
    ['label' => 'Buku Dipinjam',     'value' => $stat['buku_dipinjam'],  'icon' => '📕', 'color' => 'bg-amber-600'],
    ['label' => 'Total Anggota',     'value' => $stat['total_anggota'],  'icon' => '👥', 'color' => 'bg-sky-600'],
    ['label' => 'Pinjaman Aktif',    'value' => $stat['pinjam_aktif'],   'icon' => '🔄', 'color' => 'bg-rose-600'],
    ['label' => 'Pinjaman Selesai',  'value' => $stat['pinjam_selesai'], 'icon' => '🏁', 'color' => 'bg-slate-600'],
];
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold">Dashboard Admin</h1>
  <p class="text-sm text-slate-500">Ringkasan statistik perpustakaan digital sekolah.</p>
</div>

<!-- Kartu statistik -->
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
  <?php foreach ($cards as $card): ?>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <span class="mb-2 grid h-9 w-9 place-items-center rounded-lg <?= $card['color'] ?> text-lg"><?= $card['icon'] ?></span>
      <p class="text-2xl font-bold text-slate-800"><?= (int) $card['value'] ?></p>
      <p class="text-xs font-medium text-slate-500"><?= e($card['label']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Transaksi terbaru -->
<div class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold">Transaksi Terbaru</h2>
    <a href="peminjaman.php" class="text-sm font-medium text-indigo-600 hover:underline">Lihat semua &rarr;</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-5 py-3">Anggota</th>
          <th class="px-5 py-3">Buku</th>
          <th class="px-5 py-3">Tgl Pinjam</th>
          <th class="px-5 py-3">Tgl Kembali</th>
          <th class="px-5 py-3">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (count($terbaru) === 0): ?>
          <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Belum ada transaksi peminjaman.</td></tr>
        <?php endif; ?>
        <?php foreach ($terbaru as $row): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3">
              <p class="font-medium"><?= e($row['nama']) ?></p>
              <p class="text-xs text-slate-400"><?= e($row['nomor_anggota']) ?></p>
            </td>
            <td class="px-5 py-3"><?= e($row['judul']) ?></td>
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
