<?php
/**
 * SIPUS - Dashboard Siswa.
 * Profil siswa yang sedang login + ringkasan pinjaman aktif.
 */
declare(strict_types=1);

$pageTitle = 'Dashboard Siswa — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya siswa ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Location: ../login.php');
    exit;
}

// ── Ambil data profil anggota berdasarkan username session ──
$stmt = db()->prepare('SELECT id_anggota, nomor_anggota, nama, kelas, username
                       FROM anggota WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $_SESSION['username']]);
$profil = $stmt->fetch();

if (!$profil) {
    // Data anggota tidak ditemukan: logout paksa agar tidak dangling session
    header('Location: ../logout.php');
    exit;
}

$idAnggota = (int) $profil['id_anggota'];

// ── Ringkasan pinjaman ──
$stmt = db()->prepare('SELECT
    (SELECT COUNT(*) FROM peminjaman WHERE id_anggota = :id1 AND status = "dipinjam")    AS aktif,
    (SELECT COUNT(*) FROM peminjaman WHERE id_anggota = :id2 AND status = "dikembalikan") AS selesai');
$stmt->execute([':id1' => $idAnggota, ':id2' => $idAnggota]);
$ringkas = $stmt->fetch();

// ── Daftar pinjaman aktif ──
$stmtAktif = db()->prepare('SELECT p.id_peminjaman, b.judul, b.penulis, p.tanggal_pinjam
                            FROM peminjaman p
                            JOIN buku b ON b.id_buku = p.id_buku
                            WHERE p.id_anggota = :id AND p.status = "dipinjam"
                            ORDER BY p.tanggal_pinjam DESC');
$stmtAktif->execute([':id' => $idAnggota]);
$pinjamanAktif = $stmtAktif->fetchAll();
?>

<style>
  /* Background gradasi biru — sama persis seperti halaman login */
  html {
    height: 100%;
  }
  body {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #bfdbfe 0%, #dbeafe 45%, #bfdbfe 100%) !important;
  }

  /* Navbar semi-transparan agar menyatu dengan background — sama seperti daftar_buku.php */
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
  }

  /* Footer — sama persis seperti halaman login */
  footer,
  body > footer {
    flex-shrink: 0;
    margin-top: auto;
    background: linear-gradient(180deg, #bfdbfe 0%, #f1f2f4 100%) !important;
    background-image: linear-gradient(180deg, #ffffff 0%, #ffffff 100%) !important;
    color: #1e3a8a !important;
  }
  footer a  
    color: #1e40af !important;
  }
</style>

<div class="mb-6">
  <h1 class="text-2xl font-bold">Halo, <?= e($profil['nama']) ?> </h1>
  <p class="text-sm text-slate-500">Selamat membaca! Berikut ringkasan akun perpustakaanmu.</p>
</div>

<!-- Kartu profil -->
<div class="grid gap-6 md:grid-cols-3">
  <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="mb-4 flex items-center gap-3">
      <span class="grid h-12 w-12 place-items-center rounded-full bg-indigo-100 text-xl font-bold text-indigo-700">
        <?= e(mb_strtoupper(mb_substr($profil['nama'], 0, 1))) ?>
      </span>
      <div>
        <p class="font-semibold"><?= e($profil['nama']) ?></p>
        <p class="text-xs text-slate-400"><?= e($profil['nomor_anggota']) ?></p>
      </div>
    </div>
    <dl class="space-y-2 text-sm">
      <div class="flex justify-between"><dt class="text-slate-500">Kelas</dt><dd class="font-medium"><?= e($profil['kelas']) ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Username</dt><dd class="font-medium"><?= e($profil['username']) ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium text-emerald-600">Aktif</dd></div>
    </dl>
  </div>

  <div class="grid grid-rows-2 gap-4 md:col-span-2">
    <div class="flex items-center justify-between rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white shadow-sm">
      <div>
        <p class="text-sm opacity-80">Sedang Dipinjam</p>
        <p class="text-4xl font-bold"><?= (int) $ringkas['aktif'] ?></p>
      </div>
      <span class="text-4xl"></span>
    </div>
    <div class="flex items-center justify-between rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <div>
        <p class="text-sm text-slate-500">Total Buku Sudah Dibaca (Dikembalikan)</p>
        <p class="text-4xl font-bold text-slate-800"><?= (int) $ringkas['selesai'] ?></p>
      </div>
      <span class="text-4xl"></span>
    </div>
  </div>
</div>

<!-- Pinjaman aktif -->
<div class="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold">Pinjaman Aktif</h2>
    <a href="riwayat.php" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">+ Pinjam Buku</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-5 py-3">Buku</th>
          <th class="px-5 py-3">Penulis</th>
          <th class="px-5 py-3">Tanggal Pinjam</th>
          <th class="px-5 py-3">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (count($pinjamanAktif) === 0): ?>
          <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Kamu belum meminjam buku apa pun.</td></tr>
        <?php endif; ?>
        <?php foreach ($pinjamanAktif as $pinjam): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 font-medium"><?= e($pinjam['judul']) ?></td>
            <td class="px-5 py-3 text-slate-500"><?= e($pinjam['penulis']) ?></td>
            <td class="px-5 py-3"><?= e($pinjam['tanggal_pinjam']) ?></td>
            <td class="px-5 py-3">
              <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>