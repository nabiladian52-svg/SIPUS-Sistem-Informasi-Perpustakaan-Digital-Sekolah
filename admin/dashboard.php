<?php
/**
 * SIPUS - Dashboard Admin.
 * Ringkasan total statistik perpustakaan + daftar transaksi terbaru.
 * Kartu statistik & tabel transaksi ter-update otomatis (polling AJAX ke file ini sendiri).
 */
declare(strict_types=1);

// Tampung dulu output dari header.php, supaya kalau request-nya mode AJAX
// kita bisa buang HTML-nya dan kirim JSON murni.
ob_start();

$pageTitle = 'Dashboard Admin — SIPUS';
require __DIR__ . '/../includes/header.php';

// ── RBAC: hanya admin ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$pdo = db();

/**
 * Ambil statistik ringkas perpustakaan.
 */
function sipusAmbilStatistik(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT
        (SELECT COUNT(*) FROM buku)              AS total_buku,
        (SELECT COUNT(*) FROM buku WHERE status = "tersedia")  AS buku_tersedia,
        (SELECT COUNT(*) FROM buku WHERE status = "dipinjam")  AS buku_dipinjam,
        (SELECT COUNT(*) FROM anggota)           AS total_anggota,
        (SELECT COUNT(*) FROM peminjaman WHERE status = "dipinjam")    AS pinjam_aktif,
        (SELECT COUNT(*) FROM peminjaman WHERE status = "dikembalikan") AS pinjam_selesai');
    $stmt->execute();
    /** @var array<string,int> */
    return $stmt->fetch();
}

/**
 * Ambil N transaksi peminjaman terbaru.
 */
function sipusAmbilTransaksiTerbaru(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare('SELECT p.id_peminjaman, a.nomor_anggota, a.nama, b.judul,
                                   p.tanggal_pinjam, p.tanggal_kembali, p.status
                            FROM peminjaman p
                            JOIN anggota a ON a.id_anggota = p.id_anggota
                            JOIN buku    b ON b.id_buku    = p.id_buku
                            ORDER BY p.id_peminjaman DESC
                            LIMIT ' . (int) $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

$stat    = sipusAmbilStatistik($pdo);
$terbaru = sipusAmbilTransaksiTerbaru($pdo);

// ── MODE AJAX: dipanggil oleh JavaScript setiap beberapa detik untuk polling data terbaru ──
if (isset($_GET['ajax'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'stat'    => $stat,
        'terbaru' => $terbaru,
    ]);
    exit;
}

// ── Ikon SVG (menggantikan emoji) — statis, ditulis developer, aman untuk di-echo langsung ──
$iconBuku = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
$iconCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
$iconBukuTerbuka = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
$iconUsers = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$iconRefresh = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
$iconFlag = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>';

$cards = [
    ['key' => 'total_buku',     'label' => 'Total Buku',       'icon' => $iconBuku,        'color' => 'bg-indigo-600'],
    ['key' => 'buku_tersedia',  'label' => 'Buku Tersedia',    'icon' => $iconCheck,       'color' => 'bg-emerald-600'],
    ['key' => 'buku_dipinjam',  'label' => 'Buku Dipinjam',    'icon' => $iconBukuTerbuka, 'color' => 'bg-amber-600'],
    ['key' => 'total_anggota',  'label' => 'Total Anggota',    'icon' => $iconUsers,       'color' => 'bg-sky-600'],
    ['key' => 'pinjam_aktif',   'label' => 'Pinjaman Aktif',   'icon' => $iconRefresh,     'color' => 'bg-rose-600'],
    ['key' => 'pinjam_selesai', 'label' => 'Pinjaman Selesai', 'icon' => $iconFlag,        'color' => 'bg-slate-600'],
];

/**
 * Ambil inisial (huruf pertama nama, kapital) untuk avatar bulat.
 */
function sipusInisial(string $nama): string
{
    return mb_strtoupper(mb_substr(trim($nama) !== '' ? trim($nama) : '?', 0, 1));
}
?>

<!-- ══════════ Background aesthetic, senada dengan halaman login (tidak diubah) ══════════ -->
<div class="fixed inset-0 -z-10 overflow-hidden bg-gradient-to-br from-sky-100 via-blue-50 to-indigo-100">
  <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-300/30 blur-3xl"></div>
  <div class="absolute top-1/3 -right-20 h-80 w-80 rounded-full bg-sky-300/30 blur-3xl"></div>
  <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-blue-200/40 blur-3xl"></div>
</div>

<style>
  /* Ikon SVG di dalam badge kartu statistik */
  .stat-icon svg {
    width: 1.15rem;
    height: 1.15rem;
  }

  /* Aksen garis tipis di atas tiap kartu, warnanya mengikuti badge ikon */
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 9999px 9999px 0 0;
    opacity: 0.9;
  }
  .stat-card { position: relative; overflow: hidden; }
  .stat-card.accent-indigo::before  { background: #4f46e5; }
  .stat-card.accent-emerald::before { background: #059669; }
  .stat-card.accent-amber::before   { background: #d97706; }
  .stat-card.accent-sky::before     { background: #0284c7; }
  .stat-card.accent-rose::before    { background: #e11d48; }
  .stat-card.accent-slate::before   { background: #475569; }

  /* Avatar inisial anggota pada tabel transaksi */
  .avatar-inisial {
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: #e0e7ff;
    color: #4338ca;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
  }

  /* ═══ Responsif: tabel transaksi berubah jadi kartu bertumpuk di layar kecil ═══ */
  @media (max-width: 640px) {
    .tabel-transaksi thead { display: none; }
    .tabel-transaksi, .tabel-transaksi tbody, .tabel-transaksi tr, .tabel-transaksi td {
      display: block;
      width: 100%;
    }
    .tabel-transaksi tr {
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      margin-bottom: 0.75rem;
      padding: 0.75rem 1rem;
      background: #fff;
    }
    .tabel-transaksi tr:hover { background: #fff; }
    .tabel-transaksi td {
      padding: 0.35rem 0 !important;
      border: none !important;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
    }
    .tabel-transaksi td[data-label]::before {
      content: attr(data-label);
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: #94a3b8;
      flex-shrink: 0;
    }
    .tabel-transaksi td.td-anggota { justify-content: flex-start; }
    .tabel-transaksi td.td-anggota::before { display: none; }
  }
</style>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Dashboard Admin</h1>
    <p class="text-sm text-slate-500">Ringkasan statistik perpustakaan digital sekolah.</p>
  </div>
  <!-- Indikator kecil bahwa data berjalan real-time -->
  <div class="flex items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm ring-1 ring-slate-200 backdrop-blur">
    <span class="relative flex h-2 w-2">
      <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
      <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
    </span>
    <span id="live-status">Live</span>
    <span id="last-updated" class="text-slate-400"></span>
  </div>
</div>

<!-- Kartu statistik -->
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
  <?php
    $accentMap = [
        'bg-indigo-600'  => 'accent-indigo',
        'bg-emerald-600' => 'accent-emerald',
        'bg-amber-600'   => 'accent-amber',
        'bg-sky-600'     => 'accent-sky',
        'bg-rose-600'    => 'accent-rose',
        'bg-slate-600'   => 'accent-slate',
    ];
  ?>
  <?php foreach ($cards as $card): ?>
    <div
      id="card-<?= $card['key'] ?>"
      class="stat-card <?= $accentMap[$card['color']] ?? '' ?> group rounded-xl bg-white/70 p-4 shadow-sm ring-1 ring-slate-200 backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
    >
      <span class="stat-icon mb-3 grid h-9 w-9 place-items-center rounded-lg <?= $card['color'] ?> text-white transition-transform duration-300 group-hover:scale-110"><?= $card['icon'] ?></span>
      <p
        id="stat-<?= $card['key'] ?>"
        class="text-2xl font-bold text-slate-800 transition-colors duration-500"
        data-value="<?= (int) $stat[$card['key']] ?>"
      ><?= (int) $stat[$card['key']] ?></p>
      <p class="text-xs font-medium text-slate-500"><?= e($card['label']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Transaksi terbaru -->
<div class="mt-8 overflow-hidden rounded-xl bg-white/70 shadow-sm ring-1 ring-slate-200 backdrop-blur-md">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold text-slate-800">Transaksi Terbaru</h2>
    <a href="peminjaman.php" class="text-sm font-medium text-indigo-600 hover:underline">Lihat semua &rarr;</a>
  </div>
  <div class="overflow-x-auto p-2 sm:p-0">
    <table class="tabel-transaksi w-full text-left text-sm">
      <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-5 py-3">Anggota</th>
          <th class="px-5 py-3">Buku</th>
          <th class="px-5 py-3">Tgl Pinjam</th>
          <th class="px-5 py-3">Tgl Kembali</th>
          <th class="px-5 py-3">Status</th>
        </tr>
      </thead>
      <tbody id="tabel-transaksi-terbaru" class="divide-y divide-slate-100">
        <?php if (count($terbaru) === 0): ?>
          <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Belum ada transaksi peminjaman.</td></tr>
        <?php endif; ?>
        <?php foreach ($terbaru as $row): ?>
          <tr class="hover:bg-slate-50">
            <td class="td-anggota px-5 py-3">
              <div class="flex items-center gap-3">
                <span class="avatar-inisial"><?= e(sipusInisial($row['nama'])) ?></span>
                <div>
                  <p class="font-medium text-slate-800"><?= e($row['nama']) ?></p>
                  <p class="text-xs text-slate-400"><?= e($row['nomor_anggota']) ?></p>
                </div>
              </div>
            </td>
            <td class="px-5 py-3" data-label="Buku"><?= e($row['judul']) ?></td>
            <td class="px-5 py-3" data-label="Tgl Pinjam"><?= e($row['tanggal_pinjam']) ?></td>
            <td class="px-5 py-3" data-label="Tgl Kembali"><?= e($row['tanggal_kembali'] ?? '—') ?></td>
            <td class="px-5 py-3" data-label="Status">
              <?php if ($row['status'] === 'dipinjam'): ?>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>
              <?php else: ?>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dikembalikan</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
/* ════════════════════════════════════════════════════════════════════════
   JAVASCRIPT — bagian ini membuat dashboard "hidup":
   1. Animasi hitung naik (count-up) saat halaman pertama kali dimuat.
   2. Polling berkala ke file ini sendiri (?ajax=1) untuk mengambil data
      terbaru dari database, lalu memperbarui kartu statistik & tabel
      transaksi tanpa reload halaman.
   3. Efek highlight singkat pada kartu saat nilainya berubah.
   Catatan: render tabel disesuaikan agar cocok dengan tampilan kartu
   avatar + atribut data-label untuk mode responsif (lihat CSS di atas).
   ════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var STAT_KEYS   = ['total_buku', 'buku_tersedia', 'buku_dipinjam', 'total_anggota', 'pinjam_aktif', 'pinjam_selesai'];
  var POLL_MS     = 5000; // seberapa sering cek data baru (5 detik)

  // Animasikan sebuah angka dari nilai lama ke nilai baru.
  function animasiAngka(elemen, dari, ke, durasiMs) {
    var mulai = null;
    dari = Number(dari) || 0;
    ke = Number(ke) || 0;

    function langkah(timestamp) {
      if (!mulai) mulai = timestamp;
      var progres = Math.min((timestamp - mulai) / durasiMs, 1);
      var nilaiSekarang = Math.round(dari + (ke - dari) * progres);
      elemen.textContent = nilaiSekarang;
      if (progres < 1) {
        requestAnimationFrame(langkah);
      } else {
        elemen.textContent = ke;
      }
    }
    requestAnimationFrame(langkah);
  }

  // Kasih efek kedip warna sebentar pada kartu (hijau = naik, merah = turun).
  function kedipkanKartu(kartu, naik) {
    var warna = naik ? 'ring-emerald-400' : 'ring-rose-400';
    kartu.classList.add('ring-2', warna);
    setTimeout(function () {
      kartu.classList.remove('ring-2', warna);
    }, 1200);
  }

  // Ambil inisial (huruf pertama nama, kapital) untuk avatar bulat — versi JS, sinkron dengan sipusInisial() di PHP.
  function ambilInisial(nama) {
    var bersih = (nama || '').trim();
    return bersih === '' ? '?' : bersih.charAt(0).toUpperCase();
  }

  // Bangun ulang isi tabel "Transaksi Terbaru" dari data JSON.
  function perbaruiTabelTransaksi(daftar) {
    var tbody = document.getElementById('tabel-transaksi-terbaru');
    tbody.innerHTML = '';

    if (!daftar || daftar.length === 0) {
      var kosong = document.createElement('tr');
      kosong.innerHTML = '<td colspan="5" class="px-5 py-8 text-center text-slate-400">Belum ada transaksi peminjaman.</td>';
      tbody.appendChild(kosong);
      return;
    }

    daftar.forEach(function (row) {
      var tr = document.createElement('tr');
      tr.className = 'hover:bg-slate-50';

      var statusBadge = row.status === 'dipinjam'
        ? '<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Dipinjam</span>'
        : '<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dikembalikan</span>';

      // Kolom anggota: avatar inisial + nama + nomor anggota (dibuat lewat textContent, aman untuk data dari user).
      var tdAnggota = document.createElement('td');
      tdAnggota.className = 'td-anggota px-5 py-3';
      tdAnggota.innerHTML =
        '<div class="flex items-center gap-3">' +
          '<span class="avatar-inisial"></span>' +
          '<div><p class="font-medium text-slate-800"></p><p class="text-xs text-slate-400"></p></div>' +
        '</div>';
      tdAnggota.querySelector('.avatar-inisial').textContent = ambilInisial(row.nama);
      tdAnggota.querySelector('p.font-medium').textContent = row.nama;
      tdAnggota.querySelector('p.text-xs').textContent = row.nomor_anggota;

      var tdBuku = document.createElement('td');
      tdBuku.className = 'px-5 py-3';
      tdBuku.setAttribute('data-label', 'Buku');
      tdBuku.textContent = row.judul;

      var tdPinjam = document.createElement('td');
      tdPinjam.className = 'px-5 py-3';
      tdPinjam.setAttribute('data-label', 'Tgl Pinjam');
      tdPinjam.textContent = row.tanggal_pinjam;

      var tdKembali = document.createElement('td');
      tdKembali.className = 'px-5 py-3';
      tdKembali.setAttribute('data-label', 'Tgl Kembali');
      tdKembali.textContent = row.tanggal_kembali || '—';

      var tdStatus = document.createElement('td');
      tdStatus.className = 'px-5 py-3';
      tdStatus.setAttribute('data-label', 'Status');
      tdStatus.innerHTML = statusBadge;

      tr.appendChild(tdAnggota);
      tr.appendChild(tdBuku);
      tr.appendChild(tdPinjam);
      tr.appendChild(tdKembali);
      tr.appendChild(tdStatus);
      tbody.appendChild(tr);
    });
  }

  // Ambil data terbaru dari server (mode AJAX file ini sendiri) lalu perbarui tampilan.
  function ambilDataTerbaru() {
    fetch(window.location.pathname + '?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        STAT_KEYS.forEach(function (key) {
          var elemenAngka = document.getElementById('stat-' + key);
          var kartu = document.getElementById('card-' + key);
          if (!elemenAngka) return;

          var nilaiLama = Number(elemenAngka.getAttribute('data-value')) || 0;
          var nilaiBaru = Number(data.stat[key]) || 0;

          if (nilaiBaru !== nilaiLama) {
            animasiAngka(elemenAngka, nilaiLama, nilaiBaru, 600);
            kedipkanKartu(kartu, nilaiBaru > nilaiLama);
            elemenAngka.setAttribute('data-value', nilaiBaru);
          }
        });

        perbaruiTabelTransaksi(data.terbaru);

        var jamSekarang = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('last-updated').textContent = '· diperbarui ' + jamSekarang;
      })
      .catch(function () {
        document.getElementById('live-status').textContent = 'Terputus';
      });
  }

  // Jalankan animasi count-up awal begitu halaman siap.
  document.addEventListener('DOMContentLoaded', function () {
    STAT_KEYS.forEach(function (key) {
      var elemenAngka = document.getElementById('stat-' + key);
      if (!elemenAngka) return;
      var nilaiAkhir = Number(elemenAngka.getAttribute('data-value')) || 0;
      animasiAngka(elemenAngka, 0, nilaiAkhir, 900);
    });

    // Mulai polling berkala setelah animasi awal selesai.
    setInterval(ambilDataTerbaru, POLL_MS);
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>