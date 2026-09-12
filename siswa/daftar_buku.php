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
  /* Background solid biru muda — sama persis seperti halaman dashboard siswa */
  html {
    height: 100%;
    background: #bfdbfe !important;
  }
  body {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: #bfdbfe !important;
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

  /* ══════════════════ Popup konfirmasi pinjam (custom) ══════════════════ */
  .pinjam-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
    animation: pinjamFadeIn .18s ease-out;
  }
  .pinjam-overlay.is-open { display: flex; }

  .pinjam-card {
    width: 100%;
    max-width: 380px;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 20px 45px -10px rgba(30, 41, 59, 0.35);
    overflow: hidden;
    animation: pinjamPopIn .22s cubic-bezier(.34,1.56,.64,1);
  }

  .pinjam-card__banner {
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    padding: 1.75rem 1.5rem 1.5rem;
    text-align: center;
    color: #fff;
    position: relative;
  }
  .pinjam-card__banner::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: -1px;
    height: 18px;
    background: #fff;
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
  }
  .pinjam-card__icon {
    width: 56px;
    height: 56px;
    margin: 0 auto .75rem;
    border-radius: 9999px;
    background: rgba(255,255,255,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pinjam-card__icon svg { width: 28px; height: 28px; }

  .pinjam-card__body {
    padding: 1.25rem 1.5rem 1.5rem;
    text-align: center;
  }
  .pinjam-card__title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #818cf8;
    margin-bottom: .35rem;
  }
  .pinjam-card__judul {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    margin-bottom: .35rem;
  }
  .pinjam-card__sub {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1.4rem;
  }

  .pinjam-card__actions {
    display: flex;
    gap: .6rem;
  }
  .pinjam-card__actions button {
    flex: 1;
    border-radius: 12px;
    padding: .65rem 1rem;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
    border: none;
  }
  .pinjam-btn-batal {
    background: #f1f5f9;
    color: #475569;
  }
  .pinjam-btn-batal:hover { background: #e2e8f0; }

  .pinjam-btn-ok {
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    color: #fff;
    box-shadow: 0 8px 16px -4px rgba(79, 70, 229, .55);
  }
  .pinjam-btn-ok:hover { transform: translateY(-1px); box-shadow: 0 10px 20px -4px rgba(79, 70, 229, .65); }
  .pinjam-btn-ok:active { transform: translateY(0); }

  @keyframes pinjamFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  @keyframes pinjamPopIn {
    from { opacity: 0; transform: scale(.92) translateY(6px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }

  /* ══════════════════ Alert box (notice sukses & error) ══════════════════ */
  .alert-box {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    border-radius: 14px;
    padding: .9rem 1.1rem;
    margin-bottom: 1rem;
    box-shadow: 0 8px 20px -8px rgba(15, 23, 42, .15);
    animation: alertSlideIn .25s cubic-bezier(.34,1.2,.64,1);
  }
  .alert-box__icon {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .alert-box__icon svg { width: 18px; height: 18px; }
  .alert-box__content {
    flex: 1;
    padding-top: .2rem;
    font-size: .875rem;
    line-height: 1.5;
  }
  .alert-box__content ul { margin: 0; padding-left: 1.1rem; }
  .alert-box__content li + li { margin-top: .2rem; }

  .alert-box--success {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
  }
  .alert-box--success .alert-box__icon { background: #10b981; }
  .alert-box--success .alert-box__content { color: #065f46; font-weight: 500; }

  .alert-box--error {
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border: 1px solid #fecdd3;
  }
  .alert-box--error .alert-box__icon { background: #f43f5e; }
  .alert-box--error .alert-box__content { color: #9f1239; }

  @keyframes alertSlideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
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
  <div class="alert-box alert-box--success">
    <div class="alert-box__icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>
    <div class="alert-box__content"><?= e($notice) ?></div>
  </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="alert-box alert-box--error">
    <div class="alert-box__icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"></circle>
        <line x1="12" y1="8" x2="12" y2="12.5"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
    </div>
    <div class="alert-box__content">
      <ul class="list-disc">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
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
          <form method="post" action="daftar_buku.php" class="form-pinjam" data-judul="<?= e($buku['judul']) ?>">
            <input type="hidden" name="aksi" value="pinjam">
            <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Pinjam</button>
          </form>
        <?php else: ?>
          <button disabled class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">Pinjam</button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════ Popup konfirmasi pinjam (custom, menggantikan confirm() bawaan) ══════════════════ -->
<div id="pinjamOverlay" class="pinjam-overlay" role="dialog" aria-modal="true" aria-labelledby="pinjamJudul">
  <div class="pinjam-card">
    <div class="pinjam-card__banner">
      <div class="pinjam-card__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
        </svg>
      </div>
      <div class="pinjam-card__title">Konfirmasi Peminjaman</div>
    </div>
    <div class="pinjam-card__body">
      <div id="pinjamJudul" class="pinjam-card__judul">&mdash;</div>
      <div class="pinjam-card__sub">Buku akan dicatat sebagai pinjaman kamu. Yakin mau lanjut?</div>
      <div class="pinjam-card__actions">
        <button type="button" class="pinjam-btn-batal" id="pinjamBatal">Batal</button>
        <button type="button" class="pinjam-btn-ok" id="pinjamOk">Ya, Pinjam</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var overlay   = document.getElementById('pinjamOverlay');
    var judulEl   = document.getElementById('pinjamJudul');
    var btnOk     = document.getElementById('pinjamOk');
    var btnBatal  = document.getElementById('pinjamBatal');
    var formAktif = null;

    function bukaPopup(form) {
      formAktif = form;
      judulEl.textContent = form.getAttribute('data-judul') || 'buku ini';
      overlay.classList.add('is-open');
    }
    function tutupPopup() {
      overlay.classList.remove('is-open');
      formAktif = null;
    }

    document.querySelectorAll('form.form-pinjam').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        bukaPopup(form);
      });
    });

    btnOk.addEventListener('click', function () {
      if (formAktif) {
        var f = formAktif;
        tutupPopup();
        f.submit();
      }
    });
    btnBatal.addEventListener('click', tutupPopup);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) tutupPopup();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) tutupPopup();
    });
  })();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>