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

// ─── Pencarian ───
$keyword = trim((string) ($_GET['q'] ?? ''));
if ($keyword !== '') {
    $safeKeyword = addcslashes($keyword, '%_\\');
    $stmt = $pdo->prepare('SELECT ag.*,
                                  (SELECT COUNT(*) FROM peminjaman p WHERE p.id_anggota = ag.id_anggota AND p.status = "dipinjam") AS aktif
                           FROM anggota ag
                           WHERE ag.nama LIKE :kw OR ag.nomor_anggota LIKE :kw OR ag.kelas LIKE :kw OR ag.username LIKE :kw
                           ORDER BY ag.id_anggota DESC
                           LIMIT 100');
    $stmt->execute([':kw' => '%' . $safeKeyword . '%']);
} else {
    $stmt = $pdo->prepare('SELECT ag.*,
                                  (SELECT COUNT(*) FROM peminjaman p WHERE p.id_anggota = ag.id_anggota AND p.status = "dipinjam") AS aktif
                           FROM anggota ag
                           ORDER BY ag.id_anggota DESC
                           LIMIT 100');
    $stmt->execute();
}
$daftarAnggota = $stmt->fetchAll();
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold">Data Anggota</h1>
    <p class="text-sm text-slate-500">Tambah anggota otomatis membuat akun login role <em>siswa</em>.</p>
  </div>
  <form method="get" action="anggota.php" class="flex gap-2">
    <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="Cari nama / kelas / nomor..."
           class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Cari</button>
    <?php if ($keyword !== ''): ?>
      <a href="anggota.php" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Reset</a>
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

<div class="grid gap-6 lg:grid-cols-3">
  <!-- Form -->
  <div class="h-fit rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="mb-4 font-semibold"><?= $form['id_anggota'] > 0 ? 'Edit Anggota' : 'Tambah Anggota' ?></h2>
    <form method="post" action="anggota.php" class="space-y-3">
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id_anggota" value="<?= (int) $form['id_anggota'] ?>">

      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Nomor Anggota *</label>
        <input type="text" name="nomor_anggota" required maxlength="20" value="<?= e($form['nomor_anggota']) ?>"
               placeholder="cth: AG003"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Nama Lengkap *</label>
        <input type="text" name="nama" required maxlength="100" value="<?= e($form['nama']) ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Kelas *</label>
        <input type="text" name="kelas" required maxlength="50" value="<?= e($form['kelas']) ?>"
               placeholder="cth: XI RPL 1"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Username (untuk login) *</label>
        <input type="text" name="username" required maxlength="50" value="<?= e($form['username']) ?>"
               placeholder="cth: siswa03" pattern="[A-Za-z0-9_.]{3,50}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">
          Password <?= $form['id_anggota'] > 0 ? '(kosongkan jika tidak diubah)' : '*' ?>
        </label>
        <input type="password" name="password" minlength="6" maxlength="100"
               <?= $form['id_anggota'] > 0 ? '' : 'required' ?> autocomplete="new-password"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <p class="mt-1 text-xs text-slate-400">Disimpan ter-hash dengan Bcrypt.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
          <?= $form['id_anggota'] > 0 ? 'Perbarui' : 'Tambah' ?>
        </button>
        <?php if ($form['id_anggota'] > 0): ?>
          <a href="anggota.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Batal</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabel -->
  <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
    <div class="border-b border-slate-100 px-5 py-4">
      <h2 class="font-semibold">Daftar Anggota <span class="text-sm font-normal text-slate-400">(<?= count($daftarAnggota) ?>)</span></h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">No. Anggota</th>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Kelas</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">Pinjaman Aktif</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (count($daftarAnggota) === 0): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Tidak ada anggota yang cocok.</td></tr>
          <?php endif; ?>
          <?php foreach ($daftarAnggota as $ag): ?>
            <tr class="hover:bg-slate-50">
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
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
