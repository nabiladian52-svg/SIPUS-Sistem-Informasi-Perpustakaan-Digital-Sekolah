<?php
/**
 * SIPUS - CRUD Data Buku (Admin).
 * Fitur: tambah, edit, hapus, pencarian + sanitasi & validasi input.
 * Tampilan: disamakan dengan dashboard.php (background gradient + kartu glass/blur).
 * Ditambahkan: pencarian instan (live search) via AJAX ke file ini sendiri (?ajax=1),
 *              tanpa reload halaman, senada dengan pola polling pada dashboard.php.
 *
 * FIX:
 *  - Named parameter PDO (:kw) sebelumnya dipakai 3x dalam satu query yang sama.
 *    PDO tidak mendukung reuse named parameter tanpa emulasi prepare, sehingga
 *    query bisa gagal / melempar exception saat live search dijalankan.
 *    -> Sekarang memakai :kw1, :kw2, :kw3 masing-masing dengan value yang sama.
 *  - Output buffer saat mode AJAX sekarang dibersihkan sepenuhnya (loop ob_end_clean)
 *    supaya tidak ada HTML/whitespace nyasar yang merusak JSON.
 *  - fetchAll() dipaksa pakai PDO::FETCH_ASSOC supaya hasil JSON konsisten.
 *  - JS: error pada fetch() tidak lagi dibungkam diam-diam, sekarang di-log ke console
 *    supaya mudah didiagnosis kalau request AJAX gagal.
 *  - Popup konfirmasi hapus sekarang memakai modal custom (senada dengan modal
 *    "Konfirmasi Peminjaman" di sisi siswa), menggantikan confirm() bawaan browser.
 *  - FIX STATUS: kolom `status` di tabel buku bisa basi (tidak ikut terupdate saat
 *    proses peminjaman/pengembalian di file lain), sehingga admin bisa menampilkan
 *    "Tersedia" padahal siswa sudah melihat "Dipinjam". Sekarang status buku
 *    (baik di tabel PHP, live search JS, maupun pengecekan sebelum hapus)
 *    dihitung langsung dari `stok` (stok > 0 = Tersedia, stok <= 0 = Dipinjam),
 *    supaya selalu sinkron dengan yang dilihat siswa.
 */
declare(strict_types=1);

// Tampung dulu output dari header.php, supaya kalau request-nya mode AJAX
// kita bisa buang HTML-nya dan kirim JSON murni (pola sama seperti dashboard.php).
ob_start();

$pageTitle = 'Data Buku — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya admin (mencegah direct URL access) ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

// Batas maksimum stok per buku
const STOK_MAX = 500;

$pdo    = db();
$errors = [];
$notice = '';

// Nilai form (untuk repopulate saat validasi gagal)
$form = ['id_buku' => 0, 'nomor_buku' => '', 'judul' => '', 'penulis' => '', 'penerbit' => '', 'tahun_terbit' => '', 'stok' => '1'];

/**
 * Validasi seluruh field buku; kembalikan array error.
 */
function validate_buku(array $data, PDO $pdo, int $idBuku): array
{
    $errs = [];

    if ($data['nomor_buku'] === '' || mb_strlen($data['nomor_buku']) > 20) {
        $errs[] = 'Nomor buku wajib diisi (maks 20 karakter).';
    } elseif (!preg_match('/^[A-Za-z0-9\-]{2,20}$/', $data['nomor_buku'])) {
        $errs[] = 'Nomor buku hanya boleh huruf, angka, dan tanda hubung.';
    }

    if ($data['judul'] === '') {
        $errs[] = 'Judul wajib diisi.';
    } elseif (mb_strlen($data['judul']) > 150) {
        $errs[] = 'Judul maksimal 150 karakter.';
    }

    if ($data['penulis'] === '') {
        $errs[] = 'Penulis wajib diisi.';
    } elseif (mb_strlen($data['penulis']) > 100) {
        $errs[] = 'Penulis maksimal 100 karakter.';
    }

    if (mb_strlen($data['penerbit']) > 100) {
        $errs[] = 'Penerbit maksimal 100 karakter.';
    }

    if ($data['tahun_terbit'] !== '' && !valid_tahun($data['tahun_terbit'])) {
        $errs[] = 'Tahun terbit harus angka 1901-2155.';
    }

    // Validasi stok: wajib angka, tidak boleh negatif, dan dibatasi maksimum STOK_MAX
    if ($data['stok'] === '' || !ctype_digit((string) $data['stok'])) {
        $errs[] = 'Stok wajib diisi dengan angka (0 atau lebih).';
    } elseif ((int) $data['stok'] > STOK_MAX) {
        $errs[] = 'Stok maksimal ' . STOK_MAX . ' per buku.';
    }

    // Cek duplikasi nomor buku (kecuali record yang sedang diedit)
    if (!in_array('Nomor buku hanya boleh huruf, angka, dan tanda hubung.', $errs, true)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM buku WHERE nomor_buku = :nb AND id_buku <> :id');
        $stmt->execute([':nb' => $data['nomor_buku'], ':id' => $idBuku]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errs[] = 'Nomor buku sudah digunakan.';
        }
    }

    return $errs;
}

// ══════════════════ PROSES POST (CREATE / UPDATE / DELETE) ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── Aksi HAPUS ───
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $idHapus = filter_var($_POST['id_buku'] ?? '', FILTER_VALIDATE_INT);
        if ($idHapus === false || $idHapus <= 0) {
            $errors[] = 'ID buku tidak valid.';
        } else {
            try {
                // FIX STATUS: sebelumnya baca kolom `status` (bisa basi/tidak sinkron).
                // Sekarang cek langsung dari `stok`, sama seperti status yang ditampilkan
                // di tabel & yang dilihat siswa: stok <= 0 dianggap sedang dipinjam.
                $stmt = $pdo->prepare('SELECT stok FROM buku WHERE id_buku = :id');
                $stmt->execute([':id' => $idHapus]);
                $stokBuku = $stmt->fetchColumn();

                if ($stokBuku === false) {
                    $errors[] = 'Buku tidak ditemukan.';
                } elseif ((int) $stokBuku <= 0) {
                    $errors[] = 'Buku sedang dipinjam dan tidak dapat dihapus.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM buku WHERE id_buku = :id');
                    $stmt->execute([':id' => $idHapus]);
                    $notice = 'Buku berhasil dihapus.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Buku tidak dapat dihapus karena masih memiliki riwayat peminjaman.';
            }
        }
    }

    // ─── Aksi TAMBAH / EDIT ───
    elseif (($_POST['aksi'] ?? '') === 'simpan') {
        $form['id_buku']     = filter_var($_POST['id_buku'] ?? '0', FILTER_VALIDATE_INT) ?: 0;
        $form['nomor_buku']  = trim((string) ($_POST['nomor_buku'] ?? ''));
        $form['judul']       = trim((string) ($_POST['judul'] ?? ''));
        $form['penulis']     = trim((string) ($_POST['penulis'] ?? ''));
        $form['penerbit']    = trim((string) ($_POST['penerbit'] ?? ''));
        $form['tahun_terbit']= trim((string) ($_POST['tahun_terbit'] ?? ''));
        $form['stok']        = trim((string) ($_POST['stok'] ?? ''));

        $errors = validate_buku($form, $pdo, $form['id_buku']);

        if (empty($errors)) {
            try {
                if ($form['id_buku'] > 0) {
                    $stmt = $pdo->prepare('UPDATE buku
                                           SET nomor_buku = :nb, judul = :j, penulis = :p,
                                               penerbit = :pn, tahun_terbit = :tt, stok = :st
                                           WHERE id_buku = :id');
                    $stmt->execute([
                        ':nb' => $form['nomor_buku'], ':j' => $form['judul'],
                        ':p'  => $form['penulis'],    ':pn' => $form['penerbit'],
                        ':tt' => ($form['tahun_terbit'] !== '' ? (int) $form['tahun_terbit'] : null),
                        ':st' => (int) $form['stok'],
                        ':id' => $form['id_buku'],
                    ]);
                    $notice = 'Data buku berhasil diperbarui.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO buku (nomor_buku, judul, penulis, penerbit, tahun_terbit, stok)
                                           VALUES (:nb, :j, :p, :pn, :tt, :st)');
                    $stmt->execute([
                        ':nb' => $form['nomor_buku'], ':j' => $form['judul'],
                        ':p'  => $form['penulis'],    ':pn' => $form['penerbit'],
                        ':tt' => ($form['tahun_terbit'] !== '' ? (int) $form['tahun_terbit'] : null),
                        ':st' => (int) $form['stok'],
                    ]);
                    $notice = 'Buku baru berhasil ditambahkan.';
                }
                $form = ['id_buku' => 0, 'nomor_buku' => '', 'judul' => '', 'penulis' => '', 'penerbit' => '', 'tahun_terbit' => '', 'stok' => '1'];
            } catch (PDOException $e) {
                error_log('[SIPUS] Simpan buku error: ' . $e->getMessage());
                $errors[] = 'Gagal menyimpan data buku.';
            }
        }
    }
}

// ─── Mode edit via GET ?edit=N ───
if (isset($_GET['edit'])) {
    $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($idEdit !== false && $idEdit > 0) {
        $stmt = $pdo->prepare('SELECT * FROM buku WHERE id_buku = :id');
        $stmt->execute([':id' => $idEdit]);
        $row = $stmt->fetch();
        if ($row) {
            $form = [
                'id_buku'      => (int) $row['id_buku'],
                'nomor_buku'   => $row['nomor_buku'],
                'judul'        => $row['judul'],
                'penulis'      => $row['penulis'],
                'penerbit'     => (string) ($row['penerbit'] ?? ''),
                'tahun_terbit' => (string) ($row['tahun_terbit'] ?? ''),
                'stok'         => (string) ($row['stok'] ?? '0'),
            ];
        }
    }
}

// ─── Pencarian + pagination (prepared statement, LIKE dengan escape wildcard) ───
// FIX: named parameter tidak boleh dipakai berulang (:kw x3) dalam satu query
// yang sama pada PDO — sekarang tiap placeholder punya nama sendiri (:kw1/:kw2/:kw3).
$keyword = trim((string) ($_GET['q'] ?? ''));
$perPage = 8; // jumlah buku per halaman
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = '';
$params = [];
if ($keyword !== '') {
    $safeKeyword = addcslashes($keyword, '%_\\'); // escape wildcard LIKE
    $like = '%' . $safeKeyword . '%';

    $where  = 'WHERE judul LIKE :kw1 OR penulis LIKE :kw2 OR nomor_buku LIKE :kw3';
    $params = [
        ':kw1' => $like,
        ':kw2' => $like,
        ':kw3' => $like,
    ];
}

// Hitung total data
$stmt = $pdo->prepare("SELECT COUNT(*) FROM buku $where");
$stmt->execute($params);
$totalBuku  = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalBuku / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Ambil data untuk halaman aktif (angka sudah di-cast int, aman)
$stmt = $pdo->prepare("SELECT * FROM buku $where
                       ORDER BY id_buku DESC
                       LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
// FIX: paksa FETCH_ASSOC supaya struktur data konsisten saat di-json_encode.
$daftarBuku = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper URL pagination (mempertahankan kata kunci pencarian)
$urlHalaman = function (int $p) use ($keyword): string {
    $query = ['page' => $p];
    if ($keyword !== '') {
        $query['q'] = $keyword;
    }
    return 'buku.php?' . http_build_query($query);
};

// ── MODE AJAX: dipanggil oleh JavaScript setiap kali user mengetik di kolom pencarian ──
if (isset($_GET['ajax'])) {
    // FIX: buang SEMUA level output buffer (bisa lebih dari satu, tergantung header.php),
    // supaya tidak ada HTML/whitespace yang bocor sebelum JSON dan merusak response.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'data'        => $daftarBuku,
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $totalBuku,
        'total_pages' => $totalPages,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$judulForm = $form['id_buku'] > 0 ? 'Edit Buku' : 'Tambah Buku';
?>

<style>
  /* Background solid biru muda — disamakan dengan dashboard, peminjaman, dan anggota */
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
</style>

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Data Buku</h1>
    <p class="text-sm text-slate-500">Kelola koleksi buku perpustakaan (CRUD lengkap).</p>
  </div>

    <form method="get" action="buku.php" id="form-cari" class="flex gap-2">
      <input type="text" id="input-cari" name="q" value="<?= e($keyword) ?>" placeholder="Cari judul / penulis / nomor..."
             class="w-56 rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm outline-none backdrop-blur focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">Cari</button>
      <?php if ($keyword !== ''): ?>
        <a href="buku.php" id="reset-cari" class="rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($notice !== ''): ?>
  <div id="alert-notice" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-700 backdrop-blur transition-opacity duration-700"><?= e($notice) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-700 backdrop-blur">
    <ul class="list-inside list-disc">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Form tambah/edit -->
  <div class="h-fit rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition-all duration-300 hover:shadow-lg">
    <h2 class="mb-4 font-semibold text-slate-800"><?= e($judulForm) ?></h2>
    <form method="post" action="buku.php" class="space-y-3">
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id_buku" value="<?= (int) $form['id_buku'] ?>">

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Nomor Buku *</label>
        <input type="text" name="nomor_buku" required maxlength="20" value="<?= e($form['nomor_buku']) ?>"
               placeholder="cth: BK006"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Judul *</label>
        <input type="text" name="judul" required maxlength="150" value="<?= e($form['judul']) ?>"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Penulis *</label>
        <input type="text" name="penulis" required maxlength="100" value="<?= e($form['penulis']) ?>"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Penerbit</label>
        <input type="text" name="penerbit" maxlength="100" value="<?= e($form['penerbit']) ?>"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Tahun Terbit</label>
        <input type="number" name="tahun_terbit" min="1901" max="2155" value="<?= e($form['tahun_terbit']) ?>"
               placeholder="cth: 2024"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Stok *</label>
        <input type="number" name="stok" required min="0" max="<?= STOK_MAX ?>" value="<?= e($form['stok']) ?>"
               placeholder="cth: 5"
               class="w-full rounded-lg border border-slate-300 bg-white/80 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Maksimal <?= STOK_MAX ?> per buku.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
          <?= $form['id_buku'] > 0 ? 'Perbarui' : 'Tambah' ?>
        </button>
        <?php if ($form['id_buku'] > 0): ?>
          <a href="buku.php" class="rounded-lg border border-slate-300 bg-white/80 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabel daftar buku -->
  <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
    <div class="border-b border-slate-100 px-5 py-4">
      <h2 class="font-semibold text-slate-800">Daftar Buku <span id="jumlah-buku" class="text-sm font-normal text-slate-400">(<?= $totalBuku ?>)</span></h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="whitespace-nowrap px-3 py-2">No. Buku</th>
            <th class="px-3 py-2">Judul</th>
            <th class="px-3 py-2">Penulis</th>
            <th class="px-3 py-2 text-center">Stok</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody id="tabel-daftar-buku" class="divide-y divide-slate-100">
          <?php if (count($daftarBuku) === 0): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada buku yang cocok.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarBuku as $buku): ?>
            <tr class="hover:bg-slate-50">
              <td class="whitespace-nowrap px-3 py-2 font-mono text-xs"><?= e($buku['nomor_buku']) ?></td>
              <td class="px-3 py-2">
                <div class="max-w-[220px] truncate font-medium leading-tight" title="<?= e($buku['judul']) ?>"><?= e($buku['judul']) ?></div>
                <div class="max-w-[220px] truncate text-xs leading-tight text-slate-400"><?= e($buku['penerbit'] ?? '—') ?><?= !empty($buku['tahun_terbit']) ? ' &middot; ' . e((string) $buku['tahun_terbit']) : '' ?></div>
              </td>
              <td class="px-3 py-2"><div class="max-w-[140px] truncate" title="<?= e($buku['penulis']) ?>"><?= e($buku['penulis']) ?></div></td>
              <td class="px-3 py-2 text-center"><?= e((string) ($buku['stok'] ?? '0')) ?></td>
              <td class="px-3 py-2">
                <?php if ((int) ($buku['stok'] ?? 0) > 0): ?>
                  <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>
                <?php else: ?>
                  <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2">
                <div class="flex justify-end gap-1.5">
                  <a href="buku.php?edit=<?= (int) $buku['id_buku'] ?>"
                     class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</a>
                  <form method="post" action="<?= e($urlHalaman($page)) ?>" class="form-hapus">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
                    <button type="button" class="btn-hapus rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100" data-judul="<?= e($buku['judul']) ?>">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pagination (dirender ulang oleh JS saat live search / pindah halaman) -->
<div id="pagination-buku">
  <?php if ($totalBuku > 0 && $totalPages > 1): ?>
    <?php
      $dari = $offset + 1;
      $ke   = min($offset + $perPage, $totalBuku);

      // Daftar nomor halaman dengan "…" bila terlalu banyak
      $nomor = [];
      for ($i = 1; $i <= $totalPages; $i++) {
          if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1) {
              $nomor[] = $i;
          } elseif (end($nomor) !== '...') {
              $nomor[] = '...';
          }
      }
    ?>
    <nav class="mt-8 flex flex-col items-center gap-3" aria-label="Navigasi halaman">
      <div class="flex flex-wrap items-center justify-center gap-1.5">

        <?php if ($page > 1): ?>
          <a href="<?= e($urlHalaman($page - 1)) ?>" data-page="<?= $page - 1 ?>"
             class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">&laquo; Sebelumnya</a>
        <?php else: ?>
          <span class="cursor-not-allowed rounded-lg border border-slate-200 bg-white/60 px-3 py-2 text-sm font-medium text-slate-300">&laquo; Sebelumnya</span>
        <?php endif; ?>

        <?php foreach ($nomor as $n): ?>
          <?php if ($n === '...'): ?>
            <span class="px-2 text-sm text-slate-500">&hellip;</span>
          <?php elseif ($n === $page): ?>
            <span class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm"><?= $n ?></span>
          <?php else: ?>
            <a href="<?= e($urlHalaman($n)) ?>" data-page="<?= $n ?>"
               class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><?= $n ?></a>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= e($urlHalaman($page + 1)) ?>" data-page="<?= $page + 1 ?>"
             class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Berikutnya &raquo;</a>
        <?php else: ?>
          <span class="cursor-not-allowed rounded-lg border border-slate-200 bg-white/60 px-3 py-2 text-sm font-medium text-slate-300">Berikutnya &raquo;</span>
        <?php endif; ?>

      </div>
      <p class="text-xs text-slate-600">Menampilkan <?= $dari ?>–<?= $ke ?> dari <?= $totalBuku ?> buku</p>
    </nav>
  <?php endif; ?>
</div>

<!-- ══════════ Modal Konfirmasi Hapus (custom, senada dengan modal konfirmasi di sisi siswa) ══════════ -->
<div id="modal-hapus" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
  <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl">
    <div class="bg-gradient-to-br from-indigo-600 to-indigo-500 px-6 py-8 text-center text-white">
      <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0-1 13a2 2 0 01-2 2H9a2 2 0 01-2-2L6 7h12z" />
        </svg>
      </div>
      <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Konfirmasi Hapus</p>
    </div>
    <div class="px-6 py-6 text-center">
      <h3 id="modal-hapus-judul" class="mb-2 text-base font-semibold text-slate-800">Buku ini</h3>
      <p class="mb-6 text-sm text-slate-500">Buku akan dihapus secara permanen. Yakin mau lanjut?</p>
      <div class="flex gap-3">
        <button type="button" id="modal-hapus-batal" class="flex-1 rounded-lg border border-slate-300 bg-white py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</button>
        <button type="button" id="modal-hapus-konfirmasi" class="flex-1 rounded-lg bg-rose-600 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">Ya, Hapus</button>
      </div>
    </div>
  </div>
</div>

<script>
/* ════════════════════════════════════════════════════════════════════════
   JAVASCRIPT — membuat halaman Data Buku lebih responsif:
   1. Pencarian instan (live search): setiap kali user mengetik di kolom
      pencarian, JS menunggu jeda singkat (debounce) lalu memanggil
      buku.php?ajax=1&q=... dan merender ulang tabel TANPA reload halaman.
   2. Submit form pencarian (tombol "Cari"/Enter) juga dialihkan lewat AJAX.
   3. Notifikasi sukses (hijau) otomatis memudar & hilang setelah beberapa detik.
   4. Konfirmasi hapus memakai modal custom (bukan confirm() bawaan browser),
      supaya senada dengan modal "Konfirmasi Peminjaman" di sisi siswa.
      Delegasi event dipakai supaya tombol "Hapus" pada baris hasil AJAX
      (live search) juga otomatis terhubung ke modal ini.
   5. Pagination: nomor halaman dirender ulang dari respon AJAX (page,
      total_pages, total), klik nomor halaman tidak me-reload halaman.

   FIX: error pada fetch() sekarang di-log ke console.error, tidak lagi
   dibungkam diam-diam, supaya kegagalan AJAX mudah didiagnosis.

   FIX STATUS: badgeStatus() sekarang dihitung dari stok (bukan dari
   field status di JSON), supaya hasil live search juga konsisten dengan
   status yang dilihat siswa.
   ════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var input   = document.getElementById('input-cari');
  var tbody   = document.getElementById('tabel-daftar-buku');
  var counter = document.getElementById('jumlah-buku');
  var formCari = document.getElementById('form-cari');
  var resetLink = document.getElementById('reset-cari');
  var paginasi  = document.getElementById('pagination-buku');
  var DEBOUNCE_MS = 350;
  var timer = null;
  var halamanAktif = <?= (int) $page ?>;

  // Bangun URL buku.php dengan parameter halaman (& kata kunci bila ada).
  function buatUrl(halaman, kata) {
    var url = 'buku.php?page=' + encodeURIComponent(halaman);
    if (kata) url += '&q=' + encodeURIComponent(kata);
    return url;
  }

  function badgeStatus(stok) {
    var jumlahStok = parseInt(stok, 10);
    if (isNaN(jumlahStok)) jumlahStok = 0;
    return jumlahStok > 0
      ? '<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>'
      : '<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>';
  }

  // Bangun satu baris <tr> dari data JSON (pakai textContent, bukan HTML mentah, untuk data dari user).
  function buatBaris(buku) {
    var tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50';

    var tdNomor = document.createElement('td');
    tdNomor.className = 'whitespace-nowrap px-3 py-2 font-mono text-xs';
    tdNomor.textContent = buku.nomor_buku;

    var tdJudul = document.createElement('td');
    tdJudul.className = 'px-3 py-2';
    tdJudul.innerHTML = '<div class="max-w-[220px] truncate font-medium leading-tight"></div><div class="max-w-[220px] truncate text-xs leading-tight text-slate-400"></div>';
    var elJudul = tdJudul.querySelector('div.font-medium');
    elJudul.textContent = buku.judul;
    elJudul.title = buku.judul;
    tdJudul.querySelector('div.text-xs').textContent = (buku.penerbit || '—') + (buku.tahun_terbit ? ' \u00b7 ' + buku.tahun_terbit : '');

    var tdPenulis = document.createElement('td');
    tdPenulis.className = 'px-3 py-2';
    var elPenulis = document.createElement('div');
    elPenulis.className = 'max-w-[140px] truncate';
    elPenulis.textContent = buku.penulis;
    elPenulis.title = buku.penulis;
    tdPenulis.appendChild(elPenulis);

    var tdStok = document.createElement('td');
    tdStok.className = 'px-3 py-2 text-center';
    tdStok.textContent = buku.stok || '0';

    var tdStatus = document.createElement('td');
    tdStatus.className = 'px-3 py-2';
    tdStatus.innerHTML = badgeStatus(buku.stok);

    // Kolom Aksi dibangun lewat DOM API (bukan innerHTML string) supaya
    // atribut data-judul aman menampung judul buku apa pun (mis. ada tanda kutip).
    var tdAksi = document.createElement('td');
    tdAksi.className = 'px-3 py-2';

    var wrapAksi = document.createElement('div');
    wrapAksi.className = 'flex justify-end gap-1.5';

    var linkEdit = document.createElement('a');
    linkEdit.href = 'buku.php?edit=' + encodeURIComponent(buku.id_buku);
    linkEdit.className = 'rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100';
    linkEdit.textContent = 'Edit';

    var formHapus = document.createElement('form');
    formHapus.method = 'post';
    formHapus.action = buatUrl(halamanAktif, input ? input.value.trim() : '');
    formHapus.className = 'form-hapus';

    var inputAksi = document.createElement('input');
    inputAksi.type = 'hidden';
    inputAksi.name = 'aksi';
    inputAksi.value = 'hapus';

    var inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id_buku';
    inputId.value = buku.id_buku;

    var btnHapus = document.createElement('button');
    btnHapus.type = 'button';
    btnHapus.className = 'btn-hapus rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100';
    btnHapus.textContent = 'Hapus';
    btnHapus.setAttribute('data-judul', buku.judul);

    formHapus.appendChild(inputAksi);
    formHapus.appendChild(inputId);
    formHapus.appendChild(btnHapus);

    wrapAksi.appendChild(linkEdit);
    wrapAksi.appendChild(formHapus);
    tdAksi.appendChild(wrapAksi);

    tr.appendChild(tdNomor);
    tr.appendChild(tdJudul);
    tr.appendChild(tdPenulis);
    tr.appendChild(tdStok);
    tr.appendChild(tdStatus);
    tr.appendChild(tdAksi);
    return tr;
  }

  // ════ Pagination: render ulang kontrol halaman dari info respon AJAX ════
  function renderPagination(info, kata) {
    if (!paginasi) return;
    paginasi.innerHTML = '';
    if (!info || info.total <= 0 || info.total_pages <= 1) return;

    var page = info.page;
    var totalPages = info.total_pages;
    var dari = (page - 1) * info.per_page + 1;
    var ke = Math.min(page * info.per_page, info.total);

    var nav = document.createElement('nav');
    nav.className = 'mt-8 flex flex-col items-center gap-3';
    nav.setAttribute('aria-label', 'Navigasi halaman');

    var row = document.createElement('div');
    row.className = 'flex flex-wrap items-center justify-center gap-1.5';

    function tombolLink(label, p, angka) {
      var a = document.createElement('a');
      a.href = buatUrl(p, kata);
      a.setAttribute('data-page', p);
      a.className = 'rounded-lg border border-slate-300 bg-white ' + (angka ? 'px-3.5' : 'px-3') + ' py-2 text-sm font-medium text-slate-600 hover:bg-slate-50';
      a.textContent = label;
      return a;
    }
    function tombolMati(label) {
      var s = document.createElement('span');
      s.className = 'cursor-not-allowed rounded-lg border border-slate-200 bg-white/60 px-3 py-2 text-sm font-medium text-slate-300';
      s.textContent = label;
      return s;
    }

    row.appendChild(page > 1 ? tombolLink('\u00ab Sebelumnya', page - 1) : tombolMati('\u00ab Sebelumnya'));

    var terakhirTitik = false;
    for (var i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || Math.abs(i - page) <= 1) {
        terakhirTitik = false;
        if (i === page) {
          var aktif = document.createElement('span');
          aktif.className = 'rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm';
          aktif.textContent = i;
          row.appendChild(aktif);
        } else {
          row.appendChild(tombolLink(String(i), i, true));
        }
      } else if (!terakhirTitik) {
        terakhirTitik = true;
        var titik = document.createElement('span');
        titik.className = 'px-2 text-sm text-slate-500';
        titik.textContent = '\u2026';
        row.appendChild(titik);
      }
    }

    row.appendChild(page < totalPages ? tombolLink('Berikutnya \u00bb', page + 1) : tombolMati('Berikutnya \u00bb'));

    var info2 = document.createElement('p');
    info2.className = 'text-xs text-slate-600';
    info2.textContent = 'Menampilkan ' + dari + '\u2013' + ke + ' dari ' + info.total + ' buku';

    nav.appendChild(row);
    nav.appendChild(info2);
    paginasi.appendChild(nav);
  }

  function perbaruiTabel(daftar, info) {
    tbody.innerHTML = '';

    if (!daftar || daftar.length === 0) {
      var kosong = document.createElement('tr');
      kosong.innerHTML = '<td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada buku yang cocok.</td>';
      tbody.appendChild(kosong);
      if (counter) counter.textContent = '(' + (info ? info.total : 0) + ')';
      return;
    }

    daftar.forEach(function (buku) {
      tbody.appendChild(buatBaris(buku));
    });
    if (counter) counter.textContent = '(' + (info ? info.total : daftar.length) + ')';
  }

  function cariBuku(kata, halaman) {
    halaman = halaman || 1;
    var url = 'buku.php?ajax=1&page=' + encodeURIComponent(halaman) + (kata ? '&q=' + encodeURIComponent(kata) : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('Respon server tidak OK (HTTP ' + res.status + ')');
        }
        return res.json();
      })
      .then(function (json) {
        halamanAktif = json.page || 1;
        perbaruiTabel(json.data, json);
        renderPagination(json, kata);

        // Perbarui URL address bar (tanpa reload) supaya tetap bisa di-refresh/bookmark.
        var newUrl = (kata || halamanAktif > 1) ? buatUrl(halamanAktif, kata) : 'buku.php';
        window.history.replaceState(null, '', newUrl);
      })
      .catch(function (err) {
        // FIX: sebelumnya error di sini dibungkam total, sekarang dicatat ke console
        // supaya kegagalan pencarian (mis. JSON tidak valid / query error) terlihat jelas.
        console.error('Pencarian buku gagal:', err);
      });
  }

  if (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var kata = input.value.trim();
      timer = setTimeout(function () { cariBuku(kata, 1); }, DEBOUNCE_MS);
    });
  }

  if (formCari) {
    formCari.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      cariBuku(input.value.trim(), 1);
    });
  }

  if (resetLink) {
    resetLink.addEventListener('click', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      input.value = '';
      cariBuku('', 1);
    });
  }

  // Klik nomor halaman / Sebelumnya / Berikutnya (delegasi, karena kontrol dirender ulang oleh JS).
  if (paginasi) {
    paginasi.addEventListener('click', function (e) {
      var link = e.target.closest('a[data-page]');
      if (!link) return;
      e.preventDefault();
      clearTimeout(timer);
      cariBuku(input ? input.value.trim() : '', parseInt(link.getAttribute('data-page'), 10) || 1);

      // Gulir ke atas tabel supaya user langsung melihat data halaman baru.
      var kartu = tbody.closest('.rounded-xl');
      if (kartu && kartu.scrollIntoView) kartu.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // Notifikasi sukses (hijau) otomatis memudar & hilang setelah beberapa detik.
  var alertNotice = document.getElementById('alert-notice');
  if (alertNotice) {
    setTimeout(function () {
      alertNotice.style.opacity = '0';
      setTimeout(function () { alertNotice.remove(); }, 700);
    }, 3500);
  }

  // ════ Modal konfirmasi hapus (custom, menggantikan confirm() bawaan browser) ════
  var modalHapus            = document.getElementById('modal-hapus');
  var modalHapusJudul       = document.getElementById('modal-hapus-judul');
  var modalHapusBatal       = document.getElementById('modal-hapus-batal');
  var modalHapusKonfirmasi  = document.getElementById('modal-hapus-konfirmasi');
  var formHapusAktif        = null;

  function bukaModalHapus(form, judul) {
    formHapusAktif = form;
    modalHapusJudul.textContent = judul || 'Buku ini';
    modalHapus.classList.remove('hidden');
    modalHapus.classList.add('flex');
  }

  function tutupModalHapus() {
    formHapusAktif = null;
    modalHapus.classList.add('hidden');
    modalHapus.classList.remove('flex');
  }

  // Delegasi ke document: tetap berfungsi untuk baris yang dirender ulang oleh AJAX.
  document.addEventListener('click', function (e) {
    var tombol = e.target.closest('.btn-hapus');
    if (!tombol) return;
    var form = tombol.closest('.form-hapus');
    if (form) bukaModalHapus(form, tombol.getAttribute('data-judul'));
  });

  if (modalHapusBatal) {
    modalHapusBatal.addEventListener('click', tutupModalHapus);
  }
  if (modalHapus) {
    modalHapus.addEventListener('click', function (e) {
      if (e.target === modalHapus) tutupModalHapus();
    });
  }
  if (modalHapusKonfirmasi) {
    modalHapusKonfirmasi.addEventListener('click', function () {
      if (formHapusAktif) formHapusAktif.submit();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modalHapus && !modalHapus.classList.contains('hidden')) {
      tutupModalHapus();
    }
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>