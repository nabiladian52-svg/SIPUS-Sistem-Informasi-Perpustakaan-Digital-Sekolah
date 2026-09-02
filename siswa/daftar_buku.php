<?php
/**
 * SIPUS - Daftar Buku (Siswa).
 * List buku + pencarian + tombol pinjam (langsung) via transaksi PDO.
 * Ketersediaan buku ditentukan dari kolom stok (bukan status statis),
 * sehingga peminjaman tidak "menghabiskan" seluruh stok saat 1 buku dipinjam.
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
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // pastikan query gagal langsung ketahuan, tidak diam-diam gagal
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

            // Kunci baris buku & baca stok terkini (cegah race condition double-pinjam)
            $stmt = $pdo->prepare('SELECT stok FROM buku WHERE id_buku = :ib FOR UPDATE');
            $stmt->execute([':ib' => $idBuku]);
            $stokSaatIni = $stmt->fetchColumn();

            if ($stokSaatIni === false) {
                throw new RuntimeException('Buku tidak ditemukan.');
            }
            $stokSaatIni = (int) $stokSaatIni;

            if ($stokSaatIni <= 0) {
                throw new RuntimeException('Maaf, stok buku ini sedang habis.');
            }

            // Cegah siswa yang sama meminjam buku yang sama dua kali sebelum dikembalikan
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM peminjaman
                                   WHERE id_anggota = :ia AND id_buku = :ib AND status = "dipinjam"');
            $stmt->execute([':ia' => $idAnggota, ':ib' => $idBuku]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException('Kamu masih meminjam buku ini. Kembalikan dulu sebelum meminjam lagi.');
            }

            // 1. Insert transaksi peminjaman
            $stmt = $pdo->prepare('INSERT INTO peminjaman (id_anggota, id_buku, tanggal_pinjam, status)
                                   VALUES (:ia, :ib, CURDATE(), "dipinjam")');
            $stmt->execute([':ia' => $idAnggota, ':ib' => $idBuku]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Gagal mencatat transaksi peminjaman.');
            }

            // 2. Kurangi stok 1, dan set status "dipinjam" hanya jika stok jadi habis
            $stokBaru = $stokSaatIni - 1;
            $statusBaru = ($stokBaru > 0) ? 'tersedia' : 'dipinjam';
            $stmt = $pdo->prepare('UPDATE buku SET stok = :stok, status = :status WHERE id_buku = :ib');
            $stmt->execute([':stok' => $stokBaru, ':status' => $statusBaru, ':ib' => $idBuku]);
            if ($stmt->rowCount() < 1) {
                // rowCount() = 0 bisa berarti update gagal, ATAU nilai lama == nilai baru (MySQL tidak
                // menghitung baris yang "diupdate" tapi nilainya sama). Verifikasi ulang dari DB langsung
                // untuk membedakan kegagalan asli dari false-positive ini.
                $cek = $pdo->prepare('SELECT stok FROM buku WHERE id_buku = :ib');
                $cek->execute([':ib' => $idBuku]);
                $stokTerverifikasi = (int) $cek->fetchColumn();
                if ($stokTerverifikasi !== $stokBaru) {
                    throw new RuntimeException('Gagal memperbarui stok buku. Silakan coba lagi.');
                }
            }

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
                           ORDER BY stok > 0 DESC, judul ASC
                           LIMIT 100');
    $stmt->execute([
        ':kw1' => '%' . $safeKeyword . '%',
        ':kw2' => '%' . $safeKeyword . '%',
        ':kw3' => '%' . $safeKeyword . '%',
        ':kw4' => '%' . $safeKeyword . '%',
    ]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM buku ORDER BY stok > 0 DESC, judul ASC LIMIT 100');
    $stmt->execute();
}
$daftarBuku = $stmt->fetchAll();
?>

<style>
  /* Background gradasi biru — sama persis seperti halaman login & dashboard siswa */
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

  /* Footer — sama persis seperti halaman login & dashboard siswa */
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
    <?php $stokBuku = (int) ($buku['stok'] ?? 0); ?>
    <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md">
      <div class="mb-3 flex items-start justify-between gap-2">
        <span class="font-mono text-xs text-slate-400"><?= e($buku['nomor_buku']) ?></span>
        <?php if ($stokBuku > 0): ?>
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
      <p class="mt-0.5 text-xs text-slate-400">Stok: <?= e((string) $stokBuku) ?></p>

      <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
        <a href="detail_buku.php?id=<?= (int) $buku['id_buku'] ?>"
           class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-50">Detail</a>

        <?php if ($stokBuku > 0): ?>
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