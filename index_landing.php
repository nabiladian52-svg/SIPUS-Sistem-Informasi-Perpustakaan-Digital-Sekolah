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
            <span class="badge">SMK N 1 Giritontro</span>
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

    <footer id="tentang">
        &copy; <?= date('Y') ?> SIPUS - Sistem Informasi Perpustakaan Digital Sekolah, SMK N 1 Giritontro
    </footer>

</body>
</html>
