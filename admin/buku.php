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
                // Cek apakah buku sedang dipinjam (FK RESTRICT akan menolak jika ada transaksi)
                $stmt = $pdo->prepare('SELECT status FROM buku WHERE id_buku = :id');
                $stmt->execute([':id' => $idHapus]);
                $statusBuku = $stmt->fetchColumn();

                if ($statusBuku === 'dipinjam') {
                    $errors[] = 'Buku sedang dipinjam dan tidak dapat dihapus.';
                } elseif ($statusBuku === false) {
                    $errors[] = 'Buku tidak ditemukan.';
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

// ─── Pencarian (prepared statement, LIKE dengan escape wildcard) ───
// FIX: named parameter tidak boleh dipakai berulang (:kw x3) dalam satu query
// yang sama pada PDO — sekarang tiap placeholder punya nama sendiri (:kw1/:kw2/:kw3).
$keyword = trim((string) ($_GET['q'] ?? ''));
if ($keyword !== '') {
    $safeKeyword = addcslashes($keyword, '%_\\'); // escape wildcard LIKE
    $like = '%' . $safeKeyword . '%';

    $stmt = $pdo->prepare('SELECT * FROM buku
                           WHERE judul LIKE :kw1 OR penulis LIKE :kw2 OR nomor_buku LIKE :kw3
                           ORDER BY id_buku DESC
                           LIMIT 100');
    $stmt->execute([
        ':kw1' => $like,
        ':kw2' => $like,
        ':kw3' => $like,
    ]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM buku ORDER BY id_buku DESC LIMIT 100');
    $stmt->execute();
}
// FIX: paksa FETCH_ASSOC supaya struktur data konsisten saat di-json_encode.
$daftarBuku = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── MODE AJAX: dipanggil oleh JavaScript setiap kali user mengetik di kolom pencarian ──
if (isset($_GET['ajax'])) {
    // FIX: buang SEMUA level output buffer (bisa lebih dari satu, tergantung header.php),
    // supaya tidak ada HTML/whitespace yang bocor sebelum JSON dan merusak response.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => $daftarBuku], JSON_UNESCAPED_UNICODE);
    exit;
}

$judulForm = $form['id_buku'] > 0 ? 'Edit Buku' : 'Tambah Buku';
?>

<!-- ══════════ Background aesthetic, senada dengan dashboard.php ══════════ -->
<div class="fixed inset-0 -z-10 overflow-hidden bg-gradient-to-br from-sky-100 via-blue-50 to-indigo-100">
  <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-300/30 blur-3xl"></div>
  <div class="absolute top-1/3 -right-20 h-80 w-80 rounded-full bg-sky-300/30 blur-3xl"></div>
  <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-blue-200/40 blur-3xl"></div>
</div>

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
  <div class="h-fit rounded-xl bg-white/70 p-5 shadow-sm ring-1 ring-slate-200 backdrop-blur-md transition-all duration-300 hover:shadow-lg">
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
  <div class="overflow-hidden rounded-xl bg-white/70 shadow-sm ring-1 ring-slate-200 backdrop-blur-md lg:col-span-2">
    <div class="border-b border-slate-100 px-5 py-4">
      <h2 class="font-semibold text-slate-800">Daftar Buku <span id="jumlah-buku" class="text-sm font-normal text-slate-400">(<?= count($daftarBuku) ?>)</span></h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">No. Buku</th>
            <th class="px-4 py-3">Judul</th>
            <th class="px-4 py-3">Penulis</th>
            <th class="px-4 py-3">Tahun</th>
            <th class="px-4 py-3">Stok</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody id="tabel-daftar-buku" class="divide-y divide-slate-100">
          <?php if (count($daftarBuku) === 0): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada buku yang cocok.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarBuku as $buku): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 font-mono text-xs"><?= e($buku['nomor_buku']) ?></td>
              <td class="px-4 py-3">
                <p class="font-medium"><?= e($buku['judul']) ?></p>
                <p class="text-xs text-slate-400"><?= e($buku['penerbit'] ?? '—') ?></p>
              </td>
              <td class="px-4 py-3"><?= e($buku['penulis']) ?></td>
              <td class="px-4 py-3"><?= e($buku['tahun_terbit'] ?? '—') ?></td>
              <td class="px-4 py-3"><?= e((string) ($buku['stok'] ?? '0')) ?></td>
              <td class="px-4 py-3">
                <?php if ($buku['status'] === 'tersedia'): ?>
                  <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>
                <?php else: ?>
                  <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <a href="buku.php?edit=<?= (int) $buku['id_buku'] ?>"
                     class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</a>
                  <form method="post" action="buku.php" onsubmit="return confirm('Hapus buku ini?');">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_buku" value="<?= (int) $buku['id_buku'] ?>">
                    <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Hapus</button>
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

<script>
/* ════════════════════════════════════════════════════════════════════════
   JAVASCRIPT — membuat halaman Data Buku lebih responsif:
   1. Pencarian instan (live search): setiap kali user mengetik di kolom
      pencarian, JS menunggu jeda singkat (debounce) lalu memanggil
      buku.php?ajax=1&q=... dan merender ulang tabel TANPA reload halaman.
   2. Submit form pencarian (tombol "Cari"/Enter) juga dialihkan lewat AJAX.
   3. Notifikasi sukses (hijau) otomatis memudar & hilang setelah beberapa detik.

   FIX: error pada fetch() sekarang di-log ke console.error, tidak lagi
   dibungkam diam-diam, supaya kegagalan AJAX mudah didiagnosis.
   ════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var input   = document.getElementById('input-cari');
  var tbody   = document.getElementById('tabel-daftar-buku');
  var counter = document.getElementById('jumlah-buku');
  var formCari = document.getElementById('form-cari');
  var resetLink = document.getElementById('reset-cari');
  var DEBOUNCE_MS = 350;
  var timer = null;

  function badgeStatus(status) {
    return status === 'tersedia'
      ? '<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Tersedia</span>'
      : '<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>';
  }

  // Bangun satu baris <tr> dari data JSON (pakai textContent, bukan HTML mentah, untuk data dari user).
  function buatBaris(buku) {
    var tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50';

    var tdNomor = document.createElement('td');
    tdNomor.className = 'px-4 py-3 font-mono text-xs';
    tdNomor.textContent = buku.nomor_buku;

    var tdJudul = document.createElement('td');
    tdJudul.className = 'px-4 py-3';
    tdJudul.innerHTML = '<p class="font-medium"></p><p class="text-xs text-slate-400"></p>';
    tdJudul.querySelector('p.font-medium').textContent = buku.judul;
    tdJudul.querySelector('p.text-xs').textContent = buku.penerbit || '—';

    var tdPenulis = document.createElement('td');
    tdPenulis.className = 'px-4 py-3';
    tdPenulis.textContent = buku.penulis;

    var tdTahun = document.createElement('td');
    tdTahun.className = 'px-4 py-3';
    tdTahun.textContent = buku.tahun_terbit || '—';

    var tdStok = document.createElement('td');
    tdStok.className = 'px-4 py-3';
    tdStok.textContent = buku.stok || '0';

    var tdStatus = document.createElement('td');
    tdStatus.className = 'px-4 py-3';
    tdStatus.innerHTML = badgeStatus(buku.status);

    var tdAksi = document.createElement('td');
    tdAksi.className = 'px-4 py-3';
    tdAksi.innerHTML =
      '<div class="flex justify-end gap-2">' +
        '<a href="buku.php?edit=' + encodeURIComponent(buku.id_buku) + '" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</a>' +
        '<form method="post" action="buku.php" onsubmit="return confirm(\'Hapus buku ini?\');">' +
          '<input type="hidden" name="aksi" value="hapus">' +
          '<input type="hidden" name="id_buku" value="' + buku.id_buku + '">' +
          '<button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Hapus</button>' +
        '</form>' +
      '</div>';

    tr.appendChild(tdNomor);
    tr.appendChild(tdJudul);
    tr.appendChild(tdPenulis);
    tr.appendChild(tdTahun);
    tr.appendChild(tdStok);
    tr.appendChild(tdStatus);
    tr.appendChild(tdAksi);
    return tr;
  }

  function perbaruiTabel(daftar) {
    tbody.innerHTML = '';

    if (!daftar || daftar.length === 0) {
      var kosong = document.createElement('tr');
      kosong.innerHTML = '<td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada buku yang cocok.</td>';
      tbody.appendChild(kosong);
      if (counter) counter.textContent = '(0)';
      return;
    }

    daftar.forEach(function (buku) {
      tbody.appendChild(buatBaris(buku));
    });
    if (counter) counter.textContent = '(' + daftar.length + ')';
  }

  function cariBuku(kata) {
    var url = 'buku.php?ajax=1' + (kata ? '&q=' + encodeURIComponent(kata) : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('Respon server tidak OK (HTTP ' + res.status + ')');
        }
        return res.json();
      })
      .then(function (json) { perbaruiTabel(json.data); })
      .catch(function (err) {
        // FIX: sebelumnya error di sini dibungkam total, sekarang dicatat ke console
        // supaya kegagalan pencarian (mis. JSON tidak valid / query error) terlihat jelas.
        console.error('Pencarian buku gagal:', err);
      });

    // Perbarui URL address bar (tanpa reload) supaya tetap bisa di-refresh/bookmark.
    var newUrl = kata ? ('buku.php?q=' + encodeURIComponent(kata)) : 'buku.php';
    window.history.replaceState(null, '', newUrl);
  }

  if (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var kata = input.value.trim();
      timer = setTimeout(function () { cariBuku(kata); }, DEBOUNCE_MS);
    });
  }

  if (formCari) {
    formCari.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      cariBuku(input.value.trim());
    });
  }

  if (resetLink) {
    resetLink.addEventListener('click', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      input.value = '';
      cariBuku('');
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
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>