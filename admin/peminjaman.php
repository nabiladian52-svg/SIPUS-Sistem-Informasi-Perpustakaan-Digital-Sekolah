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
    $where[] = '(a.nama LIKE :kw1 OR a.nomor_anggota LIKE :kw2 OR b.judul LIKE :kw3 OR b.nomor_buku LIKE :kw4)';
    $safeKeyword = '%' . addcslashes($keyword, '%_\\') . '%';
    $params[':kw1'] = $safeKeyword;
    $params[':kw2'] = $safeKeyword;
    $params[':kw3'] = $safeKeyword;
    $params[':kw4'] = $safeKeyword;
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

<style>
  /* Background solid biru muda — disamakan dengan dashboard, buku, dan anggota */
  html {
    height: 100%;
    background: #bfdbfe !important;
  }
  body {
    min-height: 100%;
    background: #bfdbfe !important;
  }

  /* Navbar semi-transparan agar menyatu dengan background */
  body > nav,
  nav.bg-white,
  header nav {
    background: rgba(255, 255, 255, 0.55) !important;
    background-image: none !important;
    backdrop-filter: blur(6px);
  }

  /* ── Responsif: form filter & tabel ── */
  @media (max-width: 640px) {
    #formFilterPeminjaman {
      flex-direction: column;
      align-items: stretch;
    }
    #formFilterPeminjaman input[type="text"],
    #formFilterPeminjaman select,
    #formFilterPeminjaman button,
    #formFilterPeminjaman a {
      width: 100%;
    }
  }

  /* Tabel tetap bisa discroll horizontal di layar sempit tanpa merusak layout */
  .tabel-scroll-wrapper {
    -webkit-overflow-scrolling: touch;
  }
  @media (max-width: 640px) {
    .tabel-scroll-wrapper table {
      min-width: 720px;
    }
  }

  /* ══════════════════ Popup konfirmasi kembalikan buku (custom) ══════════════════ */
  .kembali-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
    animation: kembaliFadeIn .18s ease-out;
  }
  .kembali-overlay.is-open { display: flex; }

  .kembali-card {
    width: 100%;
    max-width: 380px;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 20px 45px -10px rgba(30, 41, 59, 0.35);
    overflow: hidden;
    animation: kembaliPopIn .22s cubic-bezier(.34,1.56,.64,1);
  }

  .kembali-card__banner {
    background: linear-gradient(135deg, #34d399 0%, #059669 100%);
    padding: 1.75rem 1.5rem 1.5rem;
    text-align: center;
    color: #fff;
    position: relative;
  }
  .kembali-card__banner::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: -1px;
    height: 18px;
    background: #fff;
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
  }
  .kembali-card__icon {
    width: 56px;
    height: 56px;
    margin: 0 auto .75rem;
    border-radius: 9999px;
    background: rgba(255,255,255,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .kembali-card__icon svg { width: 28px; height: 28px; }

  .kembali-card__title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
  }

  .kembali-card__body {
    padding: 1.25rem 1.5rem 1.5rem;
    text-align: center;
  }
  .kembali-card__judul {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    margin-bottom: .35rem;
  }
  .kembali-card__sub {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1.4rem;
  }

  .kembali-card__actions {
    display: flex;
    gap: .6rem;
  }
  .kembali-card__actions button {
    flex: 1;
    border-radius: 12px;
    padding: .65rem 1rem;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
    border: none;
  }
  .kembali-btn-batal {
    background: #f1f5f9;
    color: #475569;
  }
  .kembali-btn-batal:hover { background: #e2e8f0; }

  .kembali-btn-ok {
    background: linear-gradient(135deg, #34d399 0%, #059669 100%);
    color: #fff;
    box-shadow: 0 8px 16px -4px rgba(5, 150, 105, .55);
  }
  .kembali-btn-ok:hover { transform: translateY(-1px); box-shadow: 0 10px 20px -4px rgba(5, 150, 105, .65); }
  .kembali-btn-ok:active { transform: translateY(0); }

  @keyframes kembaliFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  @keyframes kembaliPopIn {
    from { opacity: 0; transform: scale(.92) translateY(6px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
</style>

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
<form method="get" action="peminjaman.php" id="formFilterPeminjaman" class="mb-4 flex flex-wrap items-center gap-2">
  <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="Cari nama / buku / nomor..."
         class="w-full sm:w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  <select name="status"
          class="w-full sm:w-auto rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
    <option value="semua"        <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
    <option value="dipinjam"     <?= $filterStatus === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
    <option value="dikembalikan" <?= $filterStatus === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
  </select>
  <button class="w-full sm:w-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Terapkan</button>
  <?php if ($keyword !== '' || $filterStatus !== 'semua'): ?>
    <a href="peminjaman.php" class="w-full sm:w-auto rounded-lg border border-slate-300 px-3 py-2 text-center text-sm text-slate-600 hover:bg-slate-50">Reset</a>
  <?php endif; ?>
</form>

<!-- Tabel transaksi -->
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold">Daftar Transaksi <span class="text-sm font-normal text-slate-400">(<?= count($transaksi) ?>)</span></h2>
  </div>
  <div class="overflow-x-auto tabel-scroll-wrapper">
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
                <form method="post" action="peminjaman.php" class="form-kembalikan" data-judul="<?= e($trx['judul']) ?>">
                  <input type="hidden" name="aksi" value="kembalikan">
                  <input type="hidden" name="id_peminjaman" value="<?= (int) $trx['id_peminjaman'] ?>">
                  <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
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

<!-- ══════════════════ Popup konfirmasi kembalikan (custom, menggantikan confirm() bawaan) ══════════════════ -->
<div id="kembaliOverlay" class="kembali-overlay" role="dialog" aria-modal="true" aria-labelledby="kembaliJudul">
  <div class="kembali-card">
    <div class="kembali-card__banner">
      <div class="kembali-card__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
        </svg>
      </div>
      <div class="kembali-card__title">Konfirmasi Pengembalian</div>
    </div>
    <div class="kembali-card__body">
      <div id="kembaliJudul" class="kembali-card__judul">&mdash;</div>
      <div class="kembali-card__sub">Status buku akan diubah menjadi tersedia kembali. Lanjutkan?</div>
      <div class="kembali-card__actions">
        <button type="button" class="kembali-btn-batal" id="kembaliBatal">Batal</button>
        <button type="button" class="kembali-btn-ok" id="kembaliOk">Ya, Kembalikan</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var overlay   = document.getElementById('kembaliOverlay');
    var judulEl   = document.getElementById('kembaliJudul');
    var btnOk     = document.getElementById('kembaliOk');
    var btnBatal  = document.getElementById('kembaliBatal');
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

    document.querySelectorAll('form.form-kembalikan').forEach(function (form) {
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