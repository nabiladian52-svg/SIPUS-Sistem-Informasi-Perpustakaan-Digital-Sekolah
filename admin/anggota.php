<?php
/**
 * SIPUS - CRUD Anggota (Admin).
 * Menambah anggota otomatis membuat akun `users` role 'siswa'
 * dalam SATU transaksi PDO (beginTransaction/commit) agar data sinkron.
 */
declare(strict_types=1);

$pageTitle = 'Data Anggota — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya admin ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo    = db();
$errors = [];
$notice = '';

$form = ['id_anggota' => 0, 'nomor_anggota' => '', 'nama' => '', 'kelas' => '', 'username' => ''];

// ══════════════════ PROSES POST ══════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── Aksi HAPUS ───
    if (($_POST['aksi'] ?? '') === 'hapus') {
        $idHapus = filter_var($_POST['id_anggota'] ?? '', FILTER_VALIDATE_INT);
        if ($idHapus === false || $idHapus <= 0) {
            $errors[] = 'ID anggota tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM peminjaman WHERE id_anggota = :id');
                $stmt->execute([':id' => $idHapus]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = 'Anggota tidak dapat dihapus karena memiliki riwayat peminjaman.';
                } else {
                    // Hapus anggota; akun users ikut terhapus via FK ON DELETE CASCADE
                    $stmt = $pdo->prepare('DELETE FROM anggota WHERE id_anggota = :id');
                    $stmt->execute([':id' => $idHapus]);
                    $notice = 'Anggota beserta akun login-nya berhasil dihapus.';
                }
            } catch (PDOException $e) {
                error_log('[SIPUS] Hapus anggota error: ' . $e->getMessage());
                $errors[] = 'Gagal menghapus anggota.';
            }
        }
    }

    // ─── Aksi TAMBAH / EDIT ───
    elseif (($_POST['aksi'] ?? '') === 'simpan') {
        $form['id_anggota'] = filter_var($_POST['id_anggota'] ?? '0', FILTER_VALIDATE_INT) ?: 0;
        $form['nomor_anggota'] = trim((string) ($_POST['nomor_anggota'] ?? ''));
        $form['nama']          = trim((string) ($_POST['nama'] ?? ''));
        $form['kelas']         = trim((string) ($_POST['kelas'] ?? ''));
        $form['username']      = trim((string) ($_POST['username'] ?? ''));
        $password              = (string) ($_POST['password'] ?? '');

        // ── Validasi server-side ──
        if (!preg_match('/^[A-Za-z0-9]{3,20}$/', $form['nomor_anggota'])) {
            $errors[] = 'Nomor anggota wajib 3-20 karakter alfanumerik.';
        }
        if ($form['nama'] === '' || mb_strlen($form['nama']) > 100) {
            $errors[] = 'Nama wajib diisi (maks 100 karakter).';
        }
        if ($form['kelas'] === '' || mb_strlen($form['kelas']) > 50) {
            $errors[] = 'Kelas wajib diisi (maks 50 karakter).';
        }
        if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $form['username'])) {
            $errors[] = 'Username wajib 3-50 karakter (huruf/angka/titik/garis bawah).';
        }

        // Password: wajib saat tambah; opsional saat edit (kosong = tidak diubah)
        $isEdit = $form['id_anggota'] > 0;
        if (!$isEdit && strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        }
        if (strlen($password) > 0 && strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        }
        if (strlen($password) > 100) {
            $errors[] = 'Password maksimal 100 karakter.';
        }

        // Uniqueness checks via prepared statement
        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM anggota WHERE nomor_anggota = :na AND id_anggota <> :id');
            $stmt->execute([':na' => $form['nomor_anggota'], ':id' => $form['id_anggota']]);
            if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Nomor anggota sudah terdaftar.';

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM anggota WHERE username = :u AND id_anggota <> :id');
            $stmt->execute([':u' => $form['username'], ':id' => $form['id_anggota']]);
            if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Username sudah dipakai anggota lain.';

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u AND username <> (SELECT username FROM anggota WHERE id_anggota = :id)');
            $stmt->execute([':u' => $form['username'], ':id' => $form['id_anggota']]);
            if ((int) $stmt->fetchColumn() > 0) $errors[] = 'Username sudah dipakai pengguna lain.';
        }

        // ── Simpan dalam TRANSAKSI (anggota + users tetap sinkron) ──
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if ($isEdit) {
                    // Ambil username lama untuk sinkronisasi FK
                    $stmt = $pdo->prepare('SELECT username FROM anggota WHERE id_anggota = :id FOR UPDATE');
                    $stmt->execute([':id' => $form['id_anggota']]);
                    $usernameLama = $stmt->fetchColumn();
                    if ($usernameLama === false) {
                        throw new RuntimeException('Anggota tidak ditemukan.');
                    }

                    // Update username di users dulu (FK anggota ON UPDATE CASCADE akan mengikuti)
                    if ($usernameLama !== $form['username']) {
                        $stmt = $pdo->prepare('UPDATE users SET username = :uBaru WHERE username = :uLama');
                        $stmt->execute([':uBaru' => $form['username'], ':uLama' => $usernameLama]);
                    }

                    // Update password hanya jika diisi
                    if (strlen($password) > 0) {
                        $stmt = $pdo->prepare('UPDATE users SET password = :pw WHERE username = :u');
                        $stmt->execute([':pw' => password_hash($password, PASSWORD_BCRYPT), ':u' => $form['username']]);
                    }

                    $stmt = $pdo->prepare('UPDATE anggota SET nomor_anggota = :na, nama = :nm, kelas = :kls, username = :u
                                           WHERE id_anggota = :id');
                    $stmt->execute([
                        ':na' => $form['nomor_anggota'], ':nm' => $form['nama'],
                        ':kls'=> $form['kelas'],         ':u'  => $form['username'],
                        ':id' => $form['id_anggota'],
                    ]);
                    $notice = 'Data anggota berhasil diperbarui.';

                } else {
                    // CREATE: buat akun users (role siswa) lalu data anggota
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (:u, :pw, "siswa")');
                    $stmt->execute([
                        ':u'  => $form['username'],
                        ':pw' => password_hash($password, PASSWORD_BCRYPT),
                    ]);

                    $stmt = $pdo->prepare('INSERT INTO anggota (nomor_anggota, nama, kelas, username)
                                           VALUES (:na, :nm, :kls, :u)');
                    $stmt->execute([
                        ':na' => $form['nomor_anggota'], ':nm' => $form['nama'],
                        ':kls'=> $form['kelas'],         ':u'  => $form['username'],
                    ]);
                    $notice = 'Anggota baru berhasil ditambahkan beserta akun loginnya.';
                }

                $pdo->commit();
                $form = ['id_anggota' => 0, 'nomor_anggota' => '', 'nama' => '', 'kelas' => '', 'username' => ''];

            } catch (PDOException|RuntimeException $e) {
                $pdo->rollBack();
                error_log('[SIPUS] Simpan anggota error: ' . $e->getMessage());
                $errors[] = 'Gagal menyimpan data anggota. Periksa kembali isian Anda.';
            }
        }
    }
}

// ─── Mode edit via GET ?edit=N ───
if (isset($_GET['edit'])) {
    $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($idEdit !== false && $idEdit > 0) {
        $stmt = $pdo->prepare('SELECT * FROM anggota WHERE id_anggota = :id');
        $stmt->execute([':id' => $idEdit]);
        $row = $stmt->fetch();
        if ($row) {
            $form = [
                'id_anggota'   => (int) $row['id_anggota'],
                'nomor_anggota'=> $row['nomor_anggota'],
                'nama'         => $row['nama'],
                'kelas'        => $row['kelas'],
                'username'     => $row['username'],
            ];
        }
    }
}

// ─── Data anggota (pencarian sekarang dilakukan LIVE di JS, query tetap mengambil semua
//      agar filter instan tanpa reload; parameter ?q= tetap didukung sebagai fallback) ───
$keyword = trim((string) ($_GET['q'] ?? ''));
$stmt = $pdo->prepare('SELECT ag.*,
                              (SELECT COUNT(*) FROM peminjaman p WHERE p.id_anggota = ag.id_anggota AND p.status = "dipinjam") AS aktif
                       FROM anggota ag
                       ORDER BY ag.id_anggota DESC
                       LIMIT 300');
$stmt->execute();
$daftarAnggota = $stmt->fetchAll();
?>

<!-- ══════════ Background aesthetic, senada dengan dashboard ══════════ -->
<div class="fixed inset-0 -z-10 overflow-hidden bg-gradient-to-br from-sky-100 via-blue-50 to-indigo-100">
  <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-300/30 blur-3xl"></div>
  <div class="absolute top-1/3 -right-20 h-80 w-80 rounded-full bg-sky-300/30 blur-3xl"></div>
  <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-blue-200/40 blur-3xl"></div>
</div>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
  <div class="flex items-start gap-3">
    <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg text-white shadow-sm">
      👥
    </span>
    <div>
      <div class="flex items-center gap-2">
        <h1 class="text-2xl font-bold text-slate-800">Data Anggota</h1>
      </div>
      <p class="text-sm text-slate-500">Tambah anggota otomatis membuat akun login role <em>siswa</em>.</p>
    </div>
  </div>

  <!-- Search bar dengan saran (live search) -->
  <div class="relative w-full sm:w-72" id="searchWrap">
    <div class="flex gap-2">
      <div class="relative flex-1">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.35 4.35a7.5 7.5 0 0012.3 12.3z" />
        </svg>
        <input type="text" id="liveSearch" autocomplete="off" value="<?= e($keyword) ?>"
               placeholder="Cari nama / kelas / nomor..."
               class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <!-- Dropdown saran nama -->
        <div id="searchSuggestions"
             class="absolute z-20 mt-1 hidden w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"></div>
      </div>
      <button type="button" id="resetSearch"
              class="hidden shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
        Reset
      </button>
    </div>
    <p class="mt-1 text-xs text-slate-400"><span id="searchCount"><?= count($daftarAnggota) ?></span> anggota ditemukan</p>
  </div>
</div>

<div id="noticeBox" class="<?= $notice !== '' ? '' : 'hidden' ?> mb-4 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 transition-opacity duration-500">
  <span><?= e($notice) ?></span>
</div>
<?php if (!empty($errors)): ?>
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    <ul class="list-inside list-disc">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Form -->
  <div class="h-fit overflow-hidden rounded-2xl bg-white/70 shadow-sm ring-1 ring-slate-200 backdrop-blur-md">
    <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-violet-50/80 px-5 py-4">
      <h2 class="font-semibold text-slate-800"><?= $form['id_anggota'] > 0 ? 'Edit Anggota' : 'Tambah Anggota' ?></h2>
    </div>
    <form method="post" action="anggota.php" class="space-y-3 p-5" id="formAnggota">
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id_anggota" value="<?= (int) $form['id_anggota'] ?>">

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Nomor Anggota *</label>
        <input type="text" name="nomor_anggota" required maxlength="20" value="<?= e($form['nomor_anggota']) ?>"
               placeholder="cth: AG003"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Nama Lengkap *</label>
        <input type="text" name="nama" required maxlength="100" value="<?= e($form['nama']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Kelas *</label>
        <input type="text" name="kelas" required maxlength="50" value="<?= e($form['kelas']) ?>"
               placeholder="cth: XI RPL 1"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Username (untuk login) *</label>
        <input type="text" name="username" required maxlength="50" value="<?= e($form['username']) ?>"
               placeholder="cth: siswa03" pattern="[A-Za-z0-9_.]{3,50}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">
          Password <?= $form['id_anggota'] > 0 ? '(kosongkan jika tidak diubah)' : '*' ?>
        </label>
        <input type="password" name="password" minlength="6" maxlength="100"
               <?= $form['id_anggota'] > 0 ? '' : 'required' ?> autocomplete="new-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Disimpan ter-hash dengan Bcrypt.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button type="submit" id="btnSubmit"
                class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
          <?= $form['id_anggota'] > 0 ? 'Perbarui' : 'Tambah' ?>
        </button>
        <?php if ($form['id_anggota'] > 0): ?>
          <a href="anggota.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabel (desktop) + kartu (mobile) -->
  <div class="overflow-hidden rounded-2xl bg-white/70 shadow-sm ring-1 ring-slate-200 backdrop-blur-md lg:col-span-2">
    <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-violet-50/80 px-5 py-4">
      <h2 class="font-semibold text-slate-800">Daftar Anggota <span class="text-sm font-normal text-slate-400">(<?= count($daftarAnggota) ?>)</span></h2>
    </div>

    <!-- Tampilan tabel: md ke atas -->
    <div class="hidden overflow-x-auto md:block">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">No. Anggota</th>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Kelas</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">Pinjaman Aktif</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100" id="tabelBody">
          <?php foreach ($daftarAnggota as $ag): ?>
            <tr class="row-anggota hover:bg-slate-50"
                data-search="<?= e(mb_strtolower($ag['nama'] . ' ' . $ag['nomor_anggota'] . ' ' . $ag['kelas'] . ' ' . $ag['username'])) ?>"
                data-nama="<?= e($ag['nama']) ?>">
              <td class="px-4 py-3 font-mono text-xs"><?= e($ag['nomor_anggota']) ?></td>
              <td class="px-4 py-3 font-medium"><?= e($ag['nama']) ?></td>
              <td class="px-4 py-3"><?= e($ag['kelas']) ?></td>
              <td class="px-4 py-3 text-slate-500"><?= e($ag['username']) ?></td>
              <td class="px-4 py-3">
                <?php if ((int) $ag['aktif'] > 0): ?>
                  <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"><?= (int) $ag['aktif'] ?> buku</span>
                <?php else: ?>
                  <span class="text-xs text-slate-400">Tidak ada</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <a href="anggota.php?edit=<?= (int) $ag['id_anggota'] ?>"
                     class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</a>
                  <form method="post" action="anggota.php" onsubmit="return confirm('Hapus anggota ini? Akun loginnya juga akan terhapus.');">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_anggota" value="<?= (int) $ag['id_anggota'] ?>">
                    <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Tampilan kartu: di bawah md (mobile) -->
    <div class="divide-y divide-slate-100 md:hidden" id="cardBody">
      <?php foreach ($daftarAnggota as $ag): ?>
        <div class="row-anggota p-4"
             data-search="<?= e(mb_strtolower($ag['nama'] . ' ' . $ag['nomor_anggota'] . ' ' . $ag['kelas'] . ' ' . $ag['username'])) ?>"
             data-nama="<?= e($ag['nama']) ?>">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-medium text-slate-800"><?= e($ag['nama']) ?></p>
              <p class="font-mono text-xs text-slate-400"><?= e($ag['nomor_anggota']) ?> · <?= e($ag['kelas']) ?></p>
              <p class="mt-0.5 text-xs text-slate-500">@<?= e($ag['username']) ?></p>
            </div>
            <?php if ((int) $ag['aktif'] > 0): ?>
              <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"><?= (int) $ag['aktif'] ?> buku</span>
            <?php else: ?>
              <span class="shrink-0 text-xs text-slate-400">Tidak ada</span>
            <?php endif; ?>
          </div>
          <div class="mt-3 flex gap-2">
            <a href="anggota.php?edit=<?= (int) $ag['id_anggota'] ?>"
               class="flex-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-center text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Edit</a>
            <form method="post" action="anggota.php" class="flex-1" onsubmit="return confirm('Hapus anggota ini? Akun loginnya juga akan terhapus.');">
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id_anggota" value="<?= (int) $ag['id_anggota'] ?>">
              <button class="w-full rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Hapus</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pesan kosong (muncul saat hasil pencarian nihil) -->
    <div id="emptyState" class="hidden px-4 py-10 text-center text-slate-400">
      Tidak ada anggota yang cocok dengan pencarian.
    </div>
  </div>
</div>

<script>
(function () {
  const searchInput   = document.getElementById('liveSearch');
  const suggestBox    = document.getElementById('searchSuggestions');
  const resetBtn      = document.getElementById('resetSearch');
  const searchCountEl = document.getElementById('searchCount');
  const emptyState    = document.getElementById('emptyState');
  const noticeBox     = document.getElementById('noticeBox');

  // Kumpulkan semua baris (tabel desktop + kartu mobile tetap disinkronkan sekaligus)
  const rows = Array.from(document.querySelectorAll('.row-anggota'));

  // Debounce kecil supaya pencarian terasa instan tapi tidak "gugup" saat mengetik cepat
  function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
  }

  function applyFilter(keyword) {
    const kw = keyword.trim().toLowerCase();
    let visibleCount = 0;

    rows.forEach((row) => {
      const match = kw === '' || row.dataset.search.includes(kw);
      row.classList.toggle('hidden', !match);
      if (match) visibleCount++;
    });

    searchCountEl.textContent = visibleCount;
    emptyState.classList.toggle('hidden', visibleCount !== 0);
    resetBtn.classList.toggle('hidden', kw === '');
  }

  function buildSuggestions(keyword) {
    const kw = keyword.trim().toLowerCase();
    if (kw === '') { suggestBox.classList.add('hidden'); suggestBox.innerHTML = ''; return; }

    const namaUnik = [...new Set(rows.map(r => r.dataset.nama))]
      .filter(nama => nama.toLowerCase().includes(kw))
      .slice(0, 5);

    if (namaUnik.length === 0) { suggestBox.classList.add('hidden'); suggestBox.innerHTML = ''; return; }

    suggestBox.innerHTML = namaUnik.map(nama =>
      `<button type="button" class="suggestion-item block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-indigo-50">${nama}</button>`
    ).join('');
    suggestBox.classList.remove('hidden');
  }

  const onType = debounce((value) => {
    applyFilter(value);
    buildSuggestions(value);
  }, 150);

  searchInput.addEventListener('input', (e) => onType(e.target.value));

  // Klik salah satu saran -> isi input, filter, sorot baris terkait
  suggestBox.addEventListener('click', (e) => {
    const item = e.target.closest('.suggestion-item');
    if (!item) return;
    const nama = item.textContent;
    searchInput.value = nama;
    applyFilter(nama);
    suggestBox.classList.add('hidden');

    const target = rows.find(r => r.dataset.nama === nama && !r.classList.contains('hidden'));
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      target.classList.add('bg-indigo-50');
      setTimeout(() => target.classList.remove('bg-indigo-50'), 1200);
    }
  });

  // Tutup dropdown saat klik di luar, atau saat Escape ditekan
  document.addEventListener('click', (e) => {
    if (!document.getElementById('searchWrap').contains(e.target)) {
      suggestBox.classList.add('hidden');
    }
  });
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') suggestBox.classList.add('hidden');
  });

  resetBtn.addEventListener('click', () => {
    searchInput.value = '';
    applyFilter('');
    suggestBox.classList.add('hidden');
    searchInput.focus();
  });

  // Filter awal jika ada ?q= dari server (fallback progressive enhancement)
  if (searchInput.value.trim() !== '') applyFilter(searchInput.value);

  // Notifikasi sukses otomatis memudar setelah beberapa detik
  if (noticeBox && !noticeBox.classList.contains('hidden')) {
    setTimeout(() => {
      noticeBox.style.opacity = '0';
      setTimeout(() => noticeBox.classList.add('hidden'), 500);
    }, 4000);
  }

  // Cegah submit ganda saat menyimpan data
  const formAnggota = document.getElementById('formAnggota');
  const btnSubmit    = document.getElementById('btnSubmit');
  if (formAnggota && btnSubmit) {
    formAnggota.addEventListener('submit', () => {
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Menyimpan...';
    });
  }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>