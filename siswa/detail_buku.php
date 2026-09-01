<?php
/**
 * SIPUS - Detail Buku (Siswa).
 * Menampilkan informasi lengkap buku + form/aksi pinjam.
 * Ditambahkan: informasi stok buku.
 */
declare(strict_types=1);

$pageTitle = 'Detail Buku — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya siswa ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Location: ../login.php');
    exit;
}

$pdo = db();

// Validasi parameter GET: harus integer positif
$idBuku = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($idBuku === false || $idBuku <= 0) {
    http_response_code(404);
    echo '<div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">'
       . '<p class="text-lg font-semibold">Buku tidak ditemukan</p>'
       . '<a href="daftar_buku.php" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">&larr; Kembali ke daftar buku</a>'
       . '</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Ambil data buku via prepared statement ──
$stmt = $pdo->prepare('SELECT * FROM buku WHERE id_buku = :ib LIMIT 1');
$stmt->execute([':ib' => $idBuku]);
$buku = $stmt->fetch();

if (!$buku) {
    http_response_code(404);
    echo '<div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">'
       . '<p class="text-lg font-semibold">Buku tidak ditemukan</p>'
       . '<a href="daftar_buku.php" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">&larr; Kembali ke daftar buku</a>'
       . '</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Ambil id_anggota siswa yang login ──
$stmt = $pdo->prepare('SELECT id_anggota FROM anggota WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $_SESSION['username']]);
$idAnggota = $stmt->fetchColumn();
if ($idAnggota === false) {
    header('Location: ../logout.php');
    exit;
}
$idAnggota = (int) $idAnggota;

// ── Cek apakah siswa sudah meminjam buku ini (belum dikembalikan) ──
$stmt = $pdo->prepare('SELECT COUNT(*) FROM peminjaman
                       WHERE id_anggota = :ia AND id_buku = :ib AND status = "dipinjam"');
$stmt->execute([':ia' => $idAnggota, ':ib' => $idBuku]);
$sudahPinjam = ((int) $stmt->fetchColumn()) > 0;

// ── Statistik peminjaman buku ini ──
$stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM peminjaman WHERE id_buku = :ib');
$stmt->execute([':ib' => $idBuku]);
$totalDipinjam = (int) $stmt->fetch()['total'];

$errors = [];
$notice = '';

// ══════════════════ AKSI PINJAM (POST) ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'pinjam') {
    $postedId = filter_var($_POST['id_buku'] ?? '', FILTER_VALIDATE_INT);

    if ($postedId === false || $postedId !== (int) $buku['id_buku']) {
        $errors[] = 'Data buku tidak valid.';
    } elseif ($buku['status'] !== 'tersedia') {
        $errors[] = 'Maaf, buku ini sedang dipinjam siswa lain.';
    } elseif ($sudahPinjam) {
        $errors[] = 'Kamu sedang meminjam buku ini.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kunci baris buku & verifikasi ulang status (cegah race condition)
            $stmt = $pdo->prepare('SELECT status FROM buku WHERE id_buku = :ib FOR UPDATE');
            $stmt->execute([':ib' => $idBuku]);
            if ($stmt->fetchColumn() !== 'tersedia') {
                throw new RuntimeException('Maaf, buku ini baru saja dipinjam siswa lain.');
            }

            // 1. Insert transaksi
            $stmt = $pdo->prepare('INSERT INTO peminjaman (id_anggota, id_buku, tanggal_pinjam, status)
                                   VALUES (:ia, :ib, CURDATE(), "dipinjam")');
            $stmt->execute([':ia' => $idAnggota, ':ib' => $idBuku]);

            // 2. Update status buku
            $stmt = $pdo->prepare('UPDATE buku SET status = "dipinjam" WHERE id_buku = :ib');
            $stmt->execute([':ib' => $idBuku]);

            $pdo->commit();

            // Refresh state halaman
            $buku['status'] = 'dipinjam';
            $sudahPinjam    = true;
            $totalDipinjam++;
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
?>

<div class="mb-6">
  <a href="daftar_buku.php" class="text-sm font-medium text-indigo-600 hover:underline">&larr; Kembali ke daftar buku</a>
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

<div class="grid gap-6 md:grid-cols-3">
  <!-- Panel info buku -->
  <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:col-span-2">
    <div class="mb-4 flex items-start justify-between gap-3">
      <div>
        <p class="font-mono text-xs text-slate-400"><?= e($buku['nomor_buku']) ?></p>
        <h1 class="mt-1 text-2xl font-bold text-slate-800"><?= e($buku['judul']) ?></h1>
      </div>
      <?php if ($buku['status'] === 'tersedia'): ?>
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>
      <?php else: ?>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
      <?php endif; ?>
    </div>

    <dl class="divide-y divide-slate-100 text-sm">
      <div class="flex justify-between py-3"><dt class="text-slate-500">Penulis</dt><dd class="font-medium"><?= e($buku['penulis']) ?></dd></div>
      <div class="flex justify-between py-3"><dt class="text-slate-500">Penerbit</dt><dd class="font-medium"><?= e($buku['penerbit'] ?? '—') ?></dd></div>
      <div class="flex justify-between py-3"><dt class="text-slate-500">Tahun Terbit</dt><dd class="font-medium"><?= e($buku['tahun_terbit'] ?? '—') ?></dd></div>
      <div class="flex justify-between py-3"><dt class="text-slate-500">Stok</dt><dd class="font-medium"><?= e((string) ($buku['stok'] ?? 0)) ?></dd></div>
      <div class="flex justify-between py-3"><dt class="text-slate-500">Total Dipinjam</dt><dd class="font-medium"><?= $totalDipinjam ?>&times;</dd></div>
    </dl>
  </div>

  <!-- Panel aksi pinjam -->
  <div class="h-fit rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="mb-3 font-semibold">Peminjaman</h2>

    <?php if ($sudahPinjam): ?>
      <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
        Kamu sedang meminjam buku ini. Kembalikan ke petugas perpustakaan bila sudah selesai membaca.
      </div>
    <?php elseif ($buku['status'] !== 'tersedia'): ?>
      <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        Buku ini sedang dipinjam siswa lain. Cek kembali nanti ya.
      </div>
    <?php else: ?>
      <p class="mb-4 text-sm text-slate-500">Buku ini tersedia dan siap dipinjam hari ini.</p>
      <form method="post" action="detail_buku.php?id=<?= (int) $buku['id_buku'] ?>">
        <input type="hidden" name="aksi" value="pinjam">
        <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
        <button class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
          📖 Pinjam Buku Ini
        </button>
      </form>
    <?php endif; ?>

    <a href="riwayat.php" class="mt-3 block text-center text-sm font-medium text-indigo-600 hover:underline">Lihat riwayat pinjaman &rarr;</a>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>