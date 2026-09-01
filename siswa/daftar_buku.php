<?php
/**
 * SIPUS - Daftar Buku (Siswa).
 * List buku + pencarian + tombol pinjam (langsung) via transaksi PDO.
 * Ditambahkan: informasi stok per buku.
 */
declare(strict_types=1);

$pageTitle = 'Daftar Buku — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya siswa ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Location: ../login.php');
    exit;
}

$pdo    = db();
$errors = [];
$notice = '';

// Ambil id_anggota milik siswa yang login
$stmt = $pdo->prepare('SELECT id_anggota FROM anggota WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $_SESSION['username']]);
$idAnggota = $stmt->fetchColumn();
if ($idAnggota === false) {
    header('Location: ../logout.php');
    exit;
}
$idAnggota = (int) $idAnggota;

// ══════════════════ AKSI PINJAM (POST) ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'pinjam') {
    $idBuku = filter_var($_POST['id_buku'] ?? '', FILTER_VALIDATE_INT);

    if ($idBuku === false || $idBuku <= 0) {
        $errors[] = 'ID buku tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kunci baris buku & pastikan masih tersedia (cegah race condition double-pinjam)
            $stmt = $pdo->prepare('SELECT status FROM buku WHERE id_buku = :ib FOR UPDATE');
            $stmt->execute([':ib' => $idBuku]);
            $statusBuku = $stmt->fetchColumn();

            if ($statusBuku === false) {
                throw new RuntimeException('Buku tidak ditemukan.');
            }
            if ($statusBuku !== 'tersedia') {
                throw new RuntimeException('Maaf, buku ini sedang dipinjam siswa lain.');
            }

            // 1. Insert transaksi peminjaman
            $stmt = $pdo->prepare('INSERT INTO peminjaman (id_anggota, id_buku, tanggal_pinjam, status)
                                   VALUES (:ia, :ib, CURDATE(), "dipinjam")');
            $stmt->execute([':ia' => $idAnggota, ':ib' => $idBuku]);

            // 2. Update status buku menjadi dipinjam
            $stmt = $pdo->prepare('UPDATE buku SET status = "dipinjam" WHERE id_buku = :ib');
            $stmt->execute([':ib' => $idBuku]);

            $pdo->commit();
            $notice = 'Yeay! Buku berhasil dipinjam. Jangan lupa dikembalikan ya.';

        } catch (PDOException|RuntimeException $e) {
            $pdo->rollBack();
            error_log('[SIPUS] Pinjam buku error: ' . $e->getMessage());
            $errors[] = ($e instanceof RuntimeException)
                ? $e->getMessage()
                : 'Gagal memproses peminjaman. Silakan coba lagi.';
        }
    }
}

// ─── Query daftar buku + pencarian ───
$keyword = trim((string) ($_GET['q'] ?? ''));
if ($keyword !== '') {
    $safeKeyword = addcslashes($keyword, '%_\\');
    $stmt = $pdo->prepare('SELECT * FROM buku
                           WHERE judul LIKE :kw1 OR penulis LIKE :kw2 OR penerbit LIKE :kw3 OR nomor_buku LIKE :kw4
                           ORDER BY status = "tersedia" DESC, judul ASC
                           LIMIT 100');
    $stmt->execute([
        ':kw1' => '%' . $safeKeyword . '%',
        ':kw2' => '%' . $safeKeyword . '%',
        ':kw3' => '%' . $safeKeyword . '%',
        ':kw4' => '%' . $safeKeyword . '%',
    ]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM buku ORDER BY status = "tersedia" DESC, judul ASC LIMIT 100');
    $stmt->execute();
}
$daftarBuku = $stmt->fetchAll();
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold">Daftar Buku</h1>
    <p class="text-sm text-slate-500">Jelajahi koleksi dan pinjam buku favoritmu.</p>
  </div>
  <form method="get" action="daftar_buku.php" class="flex gap-2">
    <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="Cari judul / penulis..."
           class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Cari</button>
    <?php if ($keyword !== ''): ?>
      <a href="daftar_buku.php" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Reset</a>
    <?php endif; ?>
  </form>
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

<!-- Grid buku -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <?php if (count($daftarBuku) === 0): ?>
    <p class="col-span-full rounded-xl bg-white p-8 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">
      Tidak ada buku yang cocok dengan pencarian.
    </p>
  <?php endif; ?>

  <?php foreach ($daftarBuku as $buku): ?>
    <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
      <div class="mb-3 flex items-start justify-between gap-2">
        <span class="font-mono text-xs text-slate-400"><?= e($buku['nomor_buku']) ?></span>
        <?php if ($buku['status'] === 'tersedia'): ?>
          <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>
        <?php else: ?>
          <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
        <?php endif; ?>
      </div>

      <h2 class="font-semibold leading-snug text-slate-800"><?= e($buku['judul']) ?></h2>
      <p class="mt-1 text-sm text-slate-500"><?= e($buku['penulis']) ?></p>
      <p class="mt-0.5 text-xs text-slate-400">
        <?= e($buku['penerbit'] ?? '—') ?><?= $buku['tahun_terbit'] ? ' &middot; ' . e($buku['tahun_terbit']) : '' ?>
      </p>
      <p class="mt-0.5 text-xs text-slate-400">Stok: <?= e((string) ($buku['stok'] ?? 0)) ?></p>

      <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
        <a href="detail_buku.php?id=<?= (int) $buku['id_buku'] ?>"
           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50">Detail</a>

        <?php if ($buku['status'] === 'tersedia'): ?>
          <form method="post" action="daftar_buku.php" onsubmit="return confirm('Pinjam buku <?= e($buku['judul']) ?>?');">
            <input type="hidden" name="aksi" value="pinjam">
            <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Pinjam</button>
          </form>
        <?php else: ?>
          <button disabled class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">Pinjam</button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>