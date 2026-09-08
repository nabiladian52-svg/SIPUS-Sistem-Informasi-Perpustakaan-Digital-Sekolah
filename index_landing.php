<?php
session_start();

// Kalau sudah login, langsung arahkan ke dashboard (index.php)
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIPUS - Sistem Informasi Perpustakaan Digital Sekolah</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }

    body {
        min-height: 100vh;
        background: #eaf2fb;
        color: #1e3a5f;
    }

    nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 50px;
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(6px);
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 10px rgba(30, 58, 95, 0.08);
    }

    nav .logo {
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 1px;
        color: #1e3a5f;
    }

    nav .logo span { color: #4a90d9; }

    nav .nav-links {
        display: flex;
        gap: 30px;
        align-items: center;
    }

    nav .nav-links a {
        color: #3a5a80;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    nav .nav-links a:hover { color: #4a90d9; }

    nav .btn-login {
        background: #4a90d9;
        color: #fff !important;
        padding: 9px 22px;
        border-radius: 8px;
        font-weight: 600;
        transition: background 0.2s;
    }

    nav .btn-login:hover { background: #3a7bc8; }

    /* HERO SECTION - foto gedung sebagai background, overlay biru soft */
    .hero {
        position: relative;
        background: linear-gradient(rgba(173, 209, 245, 0.72), rgba(200, 224, 250, 0.8)),
                    url('images/background.jpeg') no-repeat center center;
        background-size: cover;
        padding: 100px 20px 100px;
        text-align: center;
    }

    .hero-content {
        max-width: 800px;
        margin: 0 auto;
    }

    .hero .badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid rgba(74, 144, 217, 0.5);
        color: #1e3a5f;
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 13px;
        margin-bottom: 25px;
        font-weight: 600;
    }

    .hero h1 {
        font-size: 44px;
        line-height: 1.3;
        margin-bottom: 20px;
        color: #17324f;
        text-shadow: 0 2px 10px rgba(255,255,255,0.4);
    }

    .hero h1 span { color: #2e6cb8; }

    .hero p {
        font-size: 16px;
        color: #2c4a68;
        margin-bottom: 35px;
        line-height: 1.6;
        font-weight: 500;
    }

    .hero-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn-primary, .btn-secondary {
        padding: 14px 30px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.2s;
    }

    .btn-primary {
        background: #4a90d9;
        color: #fff;
        box-shadow: 0 4px 14px rgba(74, 144, 217, 0.35);
    }

    .btn-primary:hover { background: #3a7bc8; transform: translateY(-2px); }

    .btn-secondary {
        background: rgba(255,255,255,0.65);
        color: #1e3a5f;
        border: 1px solid rgba(74, 144, 217, 0.4);
    }

    .btn-secondary:hover { background: rgba(255,255,255,0.9); transform: translateY(-2px); }

    .features {
        max-width: 1100px;
        margin: 0 auto;
        padding: 60px 20px 100px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
    }

    .feature-card {
        background: #ffffff;
        border: 1px solid #dceaf9;
        border-radius: 16px;
        padding: 30px 25px;
        text-align: center;
        box-shadow: 0 4px 16px rgba(74, 144, 217, 0.08);
        transition: transform 0.2s;
    }

    .feature-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(74, 144, 217, 0.15); }

    .feature-card .icon {
        font-size: 38px;
        margin-bottom: 15px;
        display: block;
    }

    .feature-card h3 {
        font-size: 17px;
        margin-bottom: 10px;
        color: #1e3a5f;
    }

    .feature-card p {
        font-size: 13.5px;
        color: #5a7897;
        line-height: 1.5;
    }

    /* ABOUT SECTION - palet sama persis dengan hero & feature cards */
    
    .about-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 50px;
        align-items: center;
    }

    .about-text .badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid rgba(74, 144, 217, 0.5);
        color: #1e3a5f;
        padding: 6px 16px;
        border-radius: 30px;
        font-size: 13px;
        margin-bottom: 18px;
        font-weight: 600;
    }

    .about-text h2 {
        font-size: 30px;
        line-height: 1.35;
        color: #17324f;
        margin-bottom: 18px;
    }

    .about-text h2 span { color: #2e6cb8; }

    .about-text p {
        font-size: 15px;
        color: #2c4a68;
        line-height: 1.7;
        margin-bottom: 16px;
        font-weight: 500;
    }

    .about-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 25px;
    }

    .about-stats .stat {
        background: #eaf2fb;
        border: 1px solid #dceaf9;
        border-radius: 12px;
        padding: 18px 10px;
        text-align: center;
    }

    .about-stats .stat strong {
        display: block;
        font-size: 22px;
        color: #4a90d9;
        margin-bottom: 4px;
    }

    .about-stats .stat span {
        font-size: 12px;
        color: #5a7897;
    }

    .about-visual {
        background: #eaf2fb;
        border: 1px solid #dceaf9;
        border-radius: 16px;
        padding: 40px 30px;
    }

    .about-visual ul {
        list-style: none;
    }

    .about-visual li {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        background: #ffffff;
        border: 1px solid #dceaf9;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 14px;
        box-shadow: 0 4px 16px rgba(74, 144, 217, 0.08);
    }

    .about-visual li:last-child { margin-bottom: 0; }

    .about-visual li .icon {
        flex-shrink: 0;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #4a90d9;
        color: #fff;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .about-visual li div h4 {
        font-size: 14.5px;
        color: #1e3a5f;
        margin-bottom: 3px;
    }

    .about-visual li div p {
        font-size: 12.5px;
        color: #5a7897;
        line-height: 1.4;
    }

    @media (max-width: 800px) {
        .about-wrapper { grid-template-columns: 1fr; }
    }

    footer {
        text-align: center;
        padding: 25px;
        font-size: 12.5px;
        color: #5a7897;
        background: #f2f8fd;
        border-top: 1px solid #dceaf9;
    }

    @media (max-width: 600px) {
        nav { padding: 15px 20px; }
        nav .nav-links { gap: 15px; }
        .hero h1 { font-size: 30px; }
        .about-stats { grid-template-columns: 1fr 1fr; }
    }
</style>
</head>
<body>

    <nav>
        <div class="logo">📚 SI<span>PUS</span></div>
        <div class="nav-links">
            <a href="#fitur">Fitur</a>
            <a href="#tentang">Tentang</a>
            <a href="login.php" class="btn-login">Masuk</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-content">
            <span class="badge">SMK N 1 GIRITONTRO</span>
            <h1>Perpustakaan Digital <span>Lebih Mudah</span>, Lebih Cepat</h1>
            <p>
                Kelola dan akses koleksi buku sekolah kapan saja. Cari buku, pinjam,
                dan pantau riwayat peminjaman langsung secara online lewat SIPUS —
                Sistem Informasi Perpustakaan Digital Sekolah.
            </p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-primary">Masuk ke Akun</a>
                <a href="#fitur" class="btn-secondary">Pelajari Fitur</a>
            </div>
        </div>
    </section>

    <section class="features" id="fitur">
        <div class="feature-card">
            <span class="icon">📖</span>
            <h3>Katalog Buku Online</h3>
            <p>Telusuri koleksi buku perpustakaan sekolah dengan cepat, kapan saja dan di mana saja.</p>
        </div>
        <div class="feature-card">
            <span class="icon">🔄</span>
            <h3>Peminjaman Digital</h3>
            <p>Ajukan dan pantau status peminjaman buku tanpa perlu antre secara manual.</p>
        </div>
        <div class="feature-card">
            <span class="icon">📊</span>
            <h3>Riwayat & Laporan</h3>
            <p>Lihat riwayat peminjaman pribadi, atau rekap laporan lengkap untuk admin.</p>
        </div>
        <div class="feature-card">
            <span class="icon">🔒</span>
            <h3>Akses Aman</h3>
            <p>Login berbasis akun dengan hak akses berbeda untuk admin dan siswa.</p>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="tentang">
        <div class="about-wrapper">
            <div class="about-text">
                <span class="badge">TENTANG SIPUS</span>
                <h2>Solusi Digital untuk <span>Perpustakaan Sekolah</span> yang Lebih Modern</h2>
                <p>
                    SIPUS (Sistem Informasi Perpustakaan Digital Sekolah) dikembangkan untuk
                    SMK N 1 Giritontro guna mempermudah pengelolaan perpustakaan, mulai dari
                    pendataan koleksi buku, proses peminjaman dan pengembalian, hingga
                    pemantauan riwayat aktivitas siswa secara real-time.
                </p>
                <p>
                    Dengan SIPUS, siswa dapat mencari dan mengajukan peminjaman buku
                    tanpa harus datang dan antre secara manual, sementara admin
                    perpustakaan dapat mengelola data koleksi dan memantau laporan
                    peminjaman dengan lebih efisien dan akurat.
                </p>
                <div class="about-stats">
                    <div class="stat">
                        <strong>24/7</strong>
                        <span>Akses Online</span>
                    </div>
                    <div class="stat">
                        <strong>2</strong>
                        <span>Peran Pengguna</span>
                    </div>
                    <div class="stat">
                        <strong>100%</strong>
                        <span>Digital</span>
                    </div>
                </div>
            </div>
            <div class="about-visual">
                <ul>
                    <li>
                        <span class="icon">🏫</span>
                        <div>
                            <h4>Dibangun untuk Sekolah</h4>
                            <p>Dirancang khusus untuk kebutuhan perpustakaan SMK N 1 Giritontro.</p>
                        </div>
                    </li>
                    <li>
                        <span class="icon">🖱️</span>
                        <div>
                            <h4>Mudah Digunakan</h4>
                            <p>Antarmuka sederhana sehingga siswa dan admin dapat memakainya tanpa pelatihan khusus.</p>
                        </div>
                    </li>
                    <li>
                        <span class="icon">🗂️</span>
                        <div>
                            <h4>Data Tersimpan Rapi</h4>
                            <p>Setiap transaksi peminjaman tercatat otomatis dan dapat dilihat kapan saja.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> SIPUS - Sistem Informasi Perpustakaan Digital Sekolah, SMK N 1 Giritontro
    </footer>

</body>
</html>