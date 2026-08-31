<?php
/**
 * SIPUS - Manajemen Peminjaman (Admin).
 * - Daftar seluruh transaksi + pencarian & filter
 * - Tombol "Kembalikan" -> update peminjaman & status buku dalam TRANSAKSI PDO
 */
declare(strict_types=1);

$pageTitle = 'Peminjaman — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya admin ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo    = db();
$errors = [];
$notice = '';

// ══════════════════ PROSES PENGEMBALIAN ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kembalikan') {
    $idPeminjaman = filter_var($_POST['id_peminjaman'] ?? '', FILTER_VALIDATE_INT);

    if ($idPeminjaman === false || $idPeminjaman <= 0) {
        $errors[] = 'ID transaksi tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kunci baris transaksi & pastikan masih berstatus dipinjam
            $stmt = $pdo->prepare('SELECT p.id_buku FROM peminjaman p
                                   WHERE p.id_peminjaman = :id AND p.status = "dipinjam"
                                   FOR UPDATE');
            $stmt->execute([':id' => $idPeminjaman]);
            $idBuku = $stmt->fetchColumn();

            if ($idBuku === false) {
                throw new RuntimeException('Transaksi tidak ditemukan atau buku sudah dikembalikan.');
            }

            // 1. Update transaksi: tanggal_kembali + status
            $stmt = $pdo->prepare('UPDATE peminjaman
                                   SET tanggal_kembali = CURDATE(), status = "dikembalikan"
                                   WHERE id_peminjaman = :id');
            $stmt->execute([':id' => $idPeminjaman]);

            // 2. Update status buku menjadi tersedia kembali
            $stmt = $pdo->prepare('UPDATE buku SET status = "tersedia" WHERE id_buku = :ib');
            $stmt->execute([':ib' => (int) $idBuku]);

            $pdo->commit();
            $notice = 'Buku berhasil dikembalikan dan statusnya kini tersedia.';

        } catch (PDOException|RuntimeException $e) {
            $pdo->rollBack();
            error_log('[SIPUS] Pengembalian error: ' . $e->getMessage());
            $errors[] = ($e instanceof RuntimeException)
                ? $e->getMessage()
                : 'Gagal memproses pengembalian buku.';
        }
    }
}

// ─── Query daftar transaksi (dengan filter status & pencarian) ───
$filterStatus = $_GET['status'] ?? 'semua';
if (!in_array($filterStatus, ['semua', 'dipinjam', 'dikembalikan'], true)) {
    $filterStatus = 'semua';
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$where   = [];
$params  = [];

if ($filterStatus !== 'semua') {
    $where[]  = 'p.status = :status';
    $params[':status'] = $filterStatus;
}
if ($keyword !== '') {
    $where[] = '(a.nama LIKE :kw OR a.nomor_anggota LIKE :kw OR b.judul LIKE :kw OR b.nomor_buku LIKE :kw)';
    $params[':kw'] = '%' . addcslashes($keyword, '%_\\') . '%';
}

$sql = 'SELECT p.id_peminjaman, a.nomor_anggota, a.nama, a.kelas,
               b.nomor_buku, b.judul,
               p.tanggal_pinjam, p.tanggal_kembali, p.status
        FROM peminjaman p
        JOIN anggota a ON a.id_anggota = p.id_anggota
        JOIN buku    b ON b.id_buku    = p.id_buku';

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.id_peminjaman DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transaksi = $stmt->fetchAll();
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold">Transaksi Peminjaman</h1>
  <p class="text-sm text-slate-500">Pantau peminjaman dan proses pengembalian buku.</p>
</div>

<?php if ($notice !== ''): ?>
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($notice) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    <ul class="list-inside list-disc">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Filter & pencarian -->
<form method="get" action="peminjaman.php" class="mb-4 flex flex-wrap items-center gap-2">
  <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="Cari nama / buku / nomor..."
         class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  <select name="status"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
    <option value="semua"        <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
    <option value="dipinjam"     <?= $filterStatus === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
    <option value="dikembalikan" <?= $filterStatus === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
  </select>
  <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Terapkan</button>
  <?php if ($keyword !== '' || $filterStatus !== 'semua'): ?>
    <a href="peminjaman.php" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Reset</a>
  <?php endif; ?>
</form>

<!-- Tabel transaksi -->
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold">Daftar Transaksi <span class="text-sm font-normal text-slate-400">(<?= count($transaksi) ?>)</span></h2>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Anggota</th>
          <th class="px-4 py-3">Buku</th>
          <th class="px-4 py-3">Tgl Pinjam</th>
          <th class="px-4 py-3">Tgl Kembali</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (count($transaksi) === 0): ?>
          <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada transaksi.</td></tr>
        <?php endif; ?>
        <?php foreach ($transaksi as $trx): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-400"><?= (int) $trx['id_peminjaman'] ?></td>
            <td class="px-4 py-3">
              <p class="font-medium"><?= e($trx['nama']) ?></p>
              <p class="text-xs text-slate-400"><?= e($trx['nomor_anggota']) ?> &middot; <?= e($trx['kelas']) ?></p>
            </td>
            <td class="px-4 py-3">
              <p class="font-medium"><?= e($trx['judul']) ?></p>
              <p class="font-mono text-xs text-slate-400"><?= e($trx['nomor_buku']) ?></p>
            </td>
            <td class="px-4 py-3"><?= e($trx['tanggal_pinjam']) ?></td>
            <td class="px-4 py-3"><?= e($trx['tanggal_kembali'] ?? '—') ?></td>
            <td class="px-4 py-3">
              <?php if ($trx['status'] === 'dipinjam'): ?>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
              <?php else: ?>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dikembalikan</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right">
              <?php if ($trx['status'] === 'dipinjam'): ?>
                <form method="post" action="peminjaman.php" onsubmit="return confirm('Konfirmasi pengembalian buku ini?');">
                  <input type="hidden" name="aksi" value="kembalikan">
                  <input type="hidden" name="id_peminjaman" value="<?= (int) $trx['id_peminjaman'] ?>">
                  <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                    ✓ Kembalikan
                  </button>
                </form>
              <?php else: ?>
                <span class="text-xs text-slate-400">Selesai</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
