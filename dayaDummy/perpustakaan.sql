-- Dummy Data untuk Database `perpustakaan` (SIPUS)
-- Generated for Nabila - SMKN 1 Giritontro
-- Jalankan file struktur_Database.sql terlebih dahulu sebelum file ini

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Data untuk tabel `users` (50 data: 40 siswa, 10 admin)
-- --------------------------------------------------------
INSERT INTO `users` (`id_user`, `username`, `password`, `role`) VALUES
(1, 'panji01', '$2y$10$1uNBRG48olNoLdiTIqEy.OiyoOJF6WM/3smpEzaFa.O5f/TMg6vPq', 'siswa'), -- password: panji01123
(2, 'hendra02', '$2y$10$tsALMgSSk3p3cZ9WilmUZeW/jwOVSc6mOScZv18cWgoKN7KbbC90S', 'siswa'), -- password: hendra02123
(3, 'budi03', '$2y$10$BkuUM/z8PSZYnU6JdGxuM.zDJfeKyzFlkEe2DgICH9bYD.uMFNgaa', 'siswa'), -- password: budi03123
(4, 'wulan04', '$2y$10$hNW.XF98UWiR5p3hOXyCrut4Xj75tE4EPNkTFRi6rQrT6zVWJ7ipm', 'siswa'), -- password: wulan04123
(5, 'sari05', '$2y$10$BT11YMx2duCxDcUF49OiluLVM4tLdVZT5C5S2abjpxfVVu7G92NGe', 'siswa'), -- password: sari05123
(6, 'putri06', '$2y$10$u0LzDVJjuw2UqJdTcg9Ek.5CyNC3Ai7QT/lxiN2IYtrQJMX7O0wAC', 'siswa'), -- password: putri06123
(7, 'oki07', '$2y$10$esF/AcK/1EogRYujC799RuuVcVNIO5b8GTw3PUQKgS44dCLiYr0J6', 'siswa'), -- password: oki07123
(8, 'indah08', '$2y$10$AFSoYb7b.ruWaV99ozNTAOqm3KcSRLTJ5UYcyiGWjUJx53RuqJgdW', 'siswa'), -- password: indah08123
(9, 'wulan09', '$2y$10$/VR3cDFpWrTfCxlEMBvTIeB/61m2sp8VlplBWgTijs./KPeoy4aYm', 'siswa'), -- password: wulan09123
(10, 'gita10', '$2y$10$overZtnd7z679o7Lv3PEDO8FG6HaHsu8Mpfx/FT7OJ7zzmoOfUmFm', 'siswa'), -- password: gita10123
(11, 'siti11', '$2y$10$QBNDaD7Q9Bhlt446.3O2LeXBi63cZ3x6dH14w2SW3uSnlJHadPqOG', 'siswa'), -- password: siti11123
(12, 'wulan12', '$2y$10$7dowp/KvccvoQ8dDI35lGO/4KYNAW7T5hhIeWi2rqHnbmVXo2koAe', 'siswa'), -- password: wulan12123
(13, 'jihan13', '$2y$10$P9DyShq29TPtTjWa5179jO0Xq85KsJuu7lMMgNNuccUt7vdOWO2.q', 'siswa'), -- password: jihan13123
(14, 'fajar14', '$2y$10$xMIvw4jNUBO3JMCIOAOqRu3zmWll99oGvHbF6Ak50/XE7eT2pO0dK', 'siswa'), -- password: fajar14123
(15, 'mega15', '$2y$10$bCM3YzwfpygI233YI2ftReuv4oDwK9UZ4KUqP5z5/Np/nrycutsh2', 'siswa'), -- password: mega15123
(16, 'cahyo16', '$2y$10$7a.SA4OaOUvQx248K/CD8.To5lH/qhc4wAJoBQvkEMk7WBtL8SD/K', 'siswa'), -- password: cahyo16123
(17, 'citra17', '$2y$10$LF4zXp/ZZBSgIcxICmnTtuCwpdS0F7MipPs.7QF7/ouPNFB.ABysu', 'siswa'), -- password: citra17123
(18, 'budi18', '$2y$10$LzTJ6IkuNxH9lfWIHiiqK.AwPysas4zjFc4cTexIjAAZ8d.6kjEJ2', 'siswa'), -- password: budi18123
(19, 'fajar19', '$2y$10$Zq.C4DRCSUQoRvxvGfrP.eXSlqzVMt8uBG4WRK4MSAO64oOUnj.BO', 'siswa'), -- password: fajar19123
(20, 'nanda20', '$2y$10$qDfn5kfDtH59MJ9UXpfAT.L9cCj6VvbxCvZv0Fej/vqbDUV5KDQOi', 'siswa'), -- password: nanda20123
(21, 'oki21', '$2y$10$txFnolBtD0yOje5BuvjOnO.sxR5zd7FBG8Y9R8s/o/eCorDovF/t2', 'siswa'), -- password: oki21123
(22, 'hana22', '$2y$10$/VFfvFuLzrR05Lk0/jD.5OEXThf7pISXzKAQQsfRpyZxp1pSFjxc6', 'siswa'), -- password: hana22123
(23, 'novi23', '$2y$10$AdMPcSyrGUhd2q2c4t1UHOLJE6WJSovRGe4Q4jWFnVR4sCgogkA56', 'siswa'), -- password: novi23123
(24, 'budi24', '$2y$10$7s9UeosqnTuR8pKGx6EYEuoQT7k3zo2e7.lJfZth0x2/WDAKuCndW', 'siswa'), -- password: budi24123
(25, 'krisna25', '$2y$10$KIIr/rWAvXMe4tTm0MnnXerZYC3RHnnyy44dSpwc32IBO6l0ic21.', 'siswa'), -- password: krisna25123
(26, 'made26', '$2y$10$W15LO7y0G0VCge5xRpdkqetvBGhIesGG8Sm.XbxfyNm8.eSfTy6kO', 'siswa'), -- password: made26123
(27, 'ummu27', '$2y$10$6N1QmnzDQLhFf1lUrZQx7.j58qR0E9vBsFZc4sbd.O1IGSO8.jXJu', 'siswa'), -- password: ummu27123
(28, 'qori28', '$2y$10$cpGMqNCHSulhvMnOn4d/Jez3I.WubrDeOOn1k8diMPFGtwlaDYOYC', 'siswa'), -- password: qori28123
(29, 'taufik29', '$2y$10$wX2d7vXh9ex1sgNx/DgaPuT98AClYKBJMb9DbiaQkeS3kpbKVDHY6', 'siswa'), -- password: taufik29123
(30, 'jihan30', '$2y$10$IJybTrihl9kpgymuPIN9WujdrPwPyA6Vj.r9DI1xvAxaCLhfot8W2', 'siswa'), -- password: jihan30123
(31, 'bayu31', '$2y$10$x9XINATmWGRKzxl6J8G6qeh5su7Z/X0ggxZ8mPK3RXLEvusCZF0oG', 'siswa'), -- password: bayu31123
(32, 'oki32', '$2y$10$260UfP7nrDTgWO79/lOU6uTS1z3KxHP.LctF4HjfuaEC1xA4U1M8.', 'siswa'), -- password: oki32123
(33, 'dian33', '$2y$10$k0grdULYOTFq6/iraDJjqeL2I/Ic5LGrzFZoZ8s7nQOY2Fv2YOUAy', 'siswa'), -- password: dian33123
(34, 'mega34', '$2y$10$I5SjcnBGQAd2v24lmwKXL.qmJ3D1Pw8ZqbxPdp40p6vA40xY2FTRS', 'siswa'), -- password: mega34123
(35, 'sari35', '$2y$10$kdBSJutS2eCfrAcfdrwprel74hlO3.NkNFUtXf1dhhMpchPV.4hXi', 'siswa'), -- password: sari35123
(36, 'ahmad36', '$2y$10$CgMlhXd01BfE4.iQtAPJYeP48sRX4qqnGJFpM/jHFXnKfUEKyIgKS', 'siswa'), -- password: ahmad36123
(37, 'yoga37', '$2y$10$Mar.thR6Z/PGONDCAEeNf.P3aukVAUcL2hCMbqnnCm2hRRd8DP50O', 'siswa'), -- password: yoga37123
(38, 'kurnia38', '$2y$10$WjZCyDPme4t1sHfSb2y.yuTjpFP/Ta4gYol3ZF9DZT9PVuqtFAKfy', 'siswa'), -- password: kurnia38123
(39, 'taufik39', '$2y$10$U58cTMrv.mA.DwvVCraLFeqeCBsUzx45sdb7rh/76vdgOlB.VyBV.', 'siswa'), -- password: taufik39123
(40, 'cahyo40', '$2y$10$MymJ.I3UKYZ.FpmVZJ/Vbe1bV9cU/ClnkHknF0JdR57AzyjoQlrRC', 'siswa'), -- password: cahyo40123
(41, 'admin01', '$2y$10$XyJBDwlnISyIdfssXmlcFOR.GuuOhTftRKxP1IZG2Ncco1Awierq6', 'admin'), -- password: admin01123
(42, 'admin02', '$2y$10$Ps/nny5zXuzDLdLgSFFYnezpbFrDMhl1/xyafLAMObu.2R5voxKYO', 'admin'), -- password: admin02123
(43, 'admin03', '$2y$10$T1LX9eeGPSCoAPc5qdN3jernp78zOsqVZ8B4Yp1FWJEZMY8ZFmD1.', 'admin'), -- password: admin03123
(44, 'admin04', '$2y$10$WHzMvysh8JHu07k2j/W66u6cArtKsCvsAEftMFwY1YpbjIrOxtaFS', 'admin'), -- password: admin04123
(45, 'admin05', '$2y$10$ArfB93zw3YZ/s.YoXAC8L.Emq0a.tHzZnbVO8BpkQLhhLYGrN09ju', 'admin'), -- password: admin05123
(46, 'admin06', '$2y$10$yl3kB4VL6RE1kMN7Q514o.SNFK0FjjSqGsBwcD8JEf9C0NME11tnO', 'admin'), -- password: admin06123
(47, 'admin07', '$2y$10$FDyUhGJBUuVBu/ap27PJv.aWlEzRXMr.SKaVUI7UzsLCCGL.UMM8K', 'admin'), -- password: admin07123
(48, 'admin08', '$2y$10$.jeh6ac1L8szl3wDkEF.geTKINQEYMMGbNV4mrk3zhwar.kJZttRW', 'admin'), -- password: admin08123
(49, 'admin09', '$2y$10$tl2P7AdW6n2St4U4Ry9qOeFhoNWWabLskbIb75DnNj/BN3Ay8Er4u', 'admin'), -- password: admin09123
(50, 'admin10', '$2y$10$3mKSmaoqisOiXu305liPW.kvc9FuTuqgQ4N4vgrY91aaFzUCrch3C', 'admin'); -- password: admin10123

-- --------------------------------------------------------
-- Data untuk tabel `anggota` (50 data)
-- --------------------------------------------------------
INSERT INTO `anggota` (`id_anggota`, `nomor_anggota`, `nama`, `kelas`, `username`) VALUES
(1, '2024001', 'Wawan Utami', 'XI TJKT 1', 'panji01'),
(2, '2024002', 'Nanda Ramadhan', 'X TJKT 2', 'hendra02'),
(3, '2024003', 'Fajar Anggraini', 'X TJKT 2', 'budi03'),
(4, '2024004', 'Xena Puspita', 'X MM 1', 'wulan04'),
(5, '2024005', 'Rizky Wijaya', 'XI RPL 1', 'sari05'),
(6, '2024006', 'Jihan Pratama', 'X RPL 1', 'putri06'),
(7, '2024007', 'Fajar Prasetyo', 'XII TJKT 1', 'oki07'),
(8, '2024008', 'Panji Kurniawan', 'XII TJKT 2', 'indah08'),
(9, '2024009', 'Lutfi Setiawan', 'X TJKT 2', 'wulan09'),
(10, '2024010', 'Citra Handayani', 'XII TJKT 1', 'gita10'),
(11, '2024011', 'Fajar Handayani', 'X TJKT 2', 'siti11'),
(12, '2024012', 'Zaki Utami', 'XI RPL 1', 'wulan12'),
(13, '2024013', 'Panji Puspita', 'XI TJKT 1', 'jihan13'),
(14, '2024014', 'Yuni Puspita', 'XI TJKT 2', 'fajar14'),
(15, '2024015', 'Rina Utami', 'X TJKT 2', 'mega15'),
(16, '2024016', 'Novi Wardani', 'XII RPL 1', 'cahyo16'),
(17, '2024017', 'Vera Handayani', 'XI TJKT 1', 'citra17'),
(18, '2024018', 'Erni Anggraini', 'XII TJKT 1', 'budi18'),
(19, '2024019', 'Panji Prasetyo', 'XI TJKT 2', 'fajar19'),
(20, '2024020', 'Siti Ramadhan', 'X TJKT 1', 'nanda20'),
(21, '2024021', 'Oki Wijaya', 'XII TJKT 2', 'oki21'),
(22, '2024022', 'Agus Utami', 'X TJKT 2', 'hana22'),
(23, '2024023', 'Nanda Lestari', 'XII TJKT 2', 'novi23'),
(24, '2024024', 'Nanda Hidayat', 'X RPL 1', 'budi24'),
(25, '2024025', 'Qori Susanti', 'XI TJKT 1', 'krisna25'),
(26, '2024026', 'Rizky Santoso', 'XI TJKT 2', 'made26'),
(27, '2024027', 'Wulan Prasetyo', 'XII RPL 1', 'ummu27'),
(28, '2024028', 'Rizky Lestari', 'X RPL 1', 'qori28'),
(29, '2024029', 'Mega Anggraini', 'XII TJKT 2', 'taufik29'),
(30, '2024030', 'Oki Santoso', 'XII RPL 1', 'jihan30'),
(31, '2024031', 'Gilang Kusuma', 'X TJKT 1', 'bayu31'),
(32, '2024032', 'Hendra Santoso', 'XI TJKT 1', 'oki32'),
(33, '2024033', 'Siti Firmansyah', 'X MM 1', 'dian33'),
(34, '2024034', 'Eka Anggraini', 'X RPL 1', 'mega34'),
(35, '2024035', 'Novi Susanti', 'XII RPL 1', 'sari35'),
(36, '2024036', 'Rizky Prasetyo', 'X TJKT 1', 'ahmad36'),
(37, '2024037', 'Siti Pratama', 'XII RPL 1', 'yoga37'),
(38, '2024038', 'Yoga Utami', 'XII TJKT 2', 'kurnia38'),
(39, '2024039', 'Hendra Nugroho', 'X RPL 1', 'taufik39'),
(40, '2024040', 'Kurnia Susanti', 'X TJKT 1', 'cahyo40'),
(41, '2024041', 'Vera Utami', 'Staff/Admin', 'admin01'),
(42, '2024042', 'Hana Wardani', 'Staff/Admin', 'admin02'),
(43, '2024043', 'Hana Pratama', 'Staff/Admin', 'admin03'),
(44, '2024044', 'Panji Nugroho', 'Staff/Admin', 'admin04'),
(45, '2024045', 'Panji Maharani', 'Staff/Admin', 'admin05'),
(46, '2024046', 'Novi Setiawan', 'Staff/Admin', 'admin06'),
(47, '2024047', 'Joko Puspita', 'Staff/Admin', 'admin07'),
(48, '2024048', 'Yoga Wardani', 'Staff/Admin', 'admin08'),
(49, '2024049', 'Jihan Maharani', 'Staff/Admin', 'admin09'),
(50, '2024050', 'Ahmad Kurniawan', 'Staff/Admin', 'admin10');

-- --------------------------------------------------------
-- Data untuk tabel `buku` (100 data)
-- --------------------------------------------------------
INSERT INTO `buku` (`id_buku`, `nomor_buku`, `judul`, `penulis`, `penerbit`, `tahun_terbit`, `status`, `stok`) VALUES
(1, 'BK0001', 'Laskar Pelangi', 'Lewis Carroll', 'Grasindo', 2005, 'tersedia', 6),
(2, 'BK0002', 'Bumi Manusia', 'Arthur Conan Doyle', 'Erlangga', 2006, 'tersedia', 2),
(3, 'BK0003', 'Negeri 5 Menara', 'Ahmad Fuadi', 'Grasindo', 2007, 'tersedia', 3),
(4, 'BK0004', 'Ayat-Ayat Cinta', 'Dewi Kurnia', 'Kompas', 2010, 'tersedia', 7),
(5, 'BK0005', 'Sang Pemimpi', 'J.K. Rowling', 'Kompas', 2011, 'tersedia', 7),
(6, 'BK0006', 'Perahu Kertas', 'Chairil Anwar', 'Grasindo', 2021, 'dipinjam', 0),
(7, 'BK0007', 'Filosofi Kopi', 'Rick Riordan', 'Erlangga', 2007, 'tersedia', 1),
(8, 'BK0008', 'Cantik Itu Luka', 'Sari Handayani', 'Kompas', 2012, 'tersedia', 1),
(9, 'BK0009', 'Pulang', 'Ahmad Fuadi', 'Gramedia Pustaka Utama', 2012, 'tersedia', 1),
(10, 'BK0010', 'Ronggeng Dukuh Paruk', 'Lewis Carroll', 'Bentang Pustaka', 2021, 'tersedia', 5),
(11, 'BK0011', 'Ayah', 'Dewi Kurnia', 'Erlangga', 2022, 'tersedia', 8),
(12, 'BK0012', 'Rindu', 'Rick Riordan', 'Grasindo', 2018, 'tersedia', 2),
(13, 'BK0013', 'Dilan 1990', 'Habiburrahman El Shirazy', 'Elex Media Komputindo', 2016, 'dipinjam', 1),
(14, 'BK0014', 'Ketika Cinta Bertasbih', 'Agus Prasetyo', 'Gramedia Pustaka Utama', 2025, 'tersedia', 1),
(15, 'BK0015', 'Ranah 3 Warna', 'Budi Santoso', 'Informatika Bandung', 2008, 'tersedia', 4),
(16, 'BK0016', 'Ilmu Pengetahuan Alam Kelas X', 'J.K. Rowling', 'Kompas', 2019, 'tersedia', 7),
(17, 'BK0017', 'Matematika Dasar', 'Tere Liye', 'Andi Publisher', 2019, 'tersedia', 2),
(18, 'BK0018', 'Fisika untuk SMK', 'Agus Prasetyo', 'Kompas', 2008, 'tersedia', 1),
(19, 'BK0019', 'Kimia Terapan', 'Ahmad Fuadi', 'Erlangga', 2010, 'dipinjam', 1),
(20, 'BK0020', 'Bahasa Indonesia Lanjutan', 'Dewi Kurnia', 'Erlangga', 2017, 'tersedia', 3),
(21, 'BK0021', 'Dasar Pemrograman', 'Budi Santoso', 'Gramedia Pustaka Utama', 2017, 'tersedia', 8),
(22, 'BK0022', 'Jaringan Komputer Dasar', 'Arthur Conan Doyle', 'Elex Media Komputindo', 2022, 'dipinjam', 0),
(23, 'BK0023', 'Algoritma dan Struktur Data', 'J.K. Rowling', 'Andi Publisher', 2011, 'tersedia', 1),
(24, 'BK0024', 'Basis Data MySQL', 'Lewis Carroll', 'Gramedia Pustaka Utama', 2006, 'dipinjam', 2),
(25, 'BK0025', 'Pemrograman Web PHP', 'Hendra Setiawan', 'Mizan', 2006, 'tersedia', 3),
(26, 'BK0026', 'Sistem Operasi Linux', 'Ahmad Fuadi', 'Republika Penerbit', 2007, 'tersedia', 7),
(27, 'BK0027', 'Keamanan Jaringan', 'Habiburrahman El Shirazy', 'Republika Penerbit', 2012, 'tersedia', 2),
(28, 'BK0028', 'Elektronika Digital', 'Rina Wijaya', 'Republika Penerbit', 2023, 'tersedia', 5),
(29, 'BK0029', 'Rangkaian Listrik', 'J.K. Rowling', 'Informatika Bandung', 2012, 'tersedia', 7),
(30, 'BK0030', 'Manajemen Proyek TI', 'Dee Lestari', 'Andi Publisher', 2019, 'tersedia', 2),
(31, 'BK0031', 'Cisco CCNA', 'Andrea Hirata', 'Grasindo', 2024, 'tersedia', 2),
(32, 'BK0032', 'Instalasi Jaringan LAN', 'Fajar Nugroho', 'Erlangga', 2021, 'tersedia', 3),
(33, 'BK0033', 'Administrasi Server', 'Chairil Anwar', 'Bentang Pustaka', 2012, 'tersedia', 5),
(34, 'BK0034', 'Pengantar Sistem Informasi', 'Tere Liye', 'Grasindo', 2022, 'tersedia', 1),
(35, 'BK0035', 'Desain Grafis Dasar', 'Fajar Nugroho', 'Andi Publisher', 2008, 'tersedia', 5),
(36, 'BK0036', 'HTML dan CSS untuk Pemula', 'Habiburrahman El Shirazy', 'Bentang Pustaka', 2022, 'tersedia', 5),
(37, 'BK0037', 'JavaScript Modern', 'Arthur Conan Doyle', 'Republika Penerbit', 2011, 'tersedia', 4),
(38, 'BK0038', 'Python untuk Pemula', 'C.S. Lewis', 'Kompas', 2020, 'tersedia', 1),
(39, 'BK0039', 'Machine Learning Dasar', 'Ahmad Fuadi', 'Elex Media Komputindo', 2013, 'tersedia', 1),
(40, 'BK0040', 'Kecerdasan Buatan', 'Lewis Carroll', 'Mizan', 2025, 'tersedia', 3),
(41, 'BK0041', 'Cloud Computing', 'Agus Prasetyo', 'Kompas', 2018, 'tersedia', 2),
(42, 'BK0042', 'Internet of Things', 'Ahmad Fuadi', 'Mizan', 2022, 'tersedia', 6),
(43, 'BK0043', 'Robotika Dasar', 'Sari Handayani', 'Kompas', 2009, 'dipinjam', 0),
(44, 'BK0044', 'Statistika Terapan', 'Pramoedya Ananta Toer', 'Andi Publisher', 2016, 'tersedia', 6),
(45, 'BK0045', 'Sejarah Indonesia', 'J.K. Rowling', 'Erlangga', 2008, 'tersedia', 7),
(46, 'BK0046', 'Geografi Nusantara', 'Joko Susanto', 'Mizan', 2012, 'tersedia', 3),
(47, 'BK0047', 'Ekonomi Bisnis', 'Rina Wijaya', 'Gramedia Pustaka Utama', 2010, 'tersedia', 7),
(48, 'BK0048', 'PKN untuk SMK', 'Rick Riordan', 'Andi Publisher', 2010, 'tersedia', 7),
(49, 'BK0049', 'Bahasa Inggris Teknik', 'Pramoedya Ananta Toer', 'Grasindo', 2012, 'tersedia', 8),
(50, 'BK0050', 'Kewirausahaan', 'Chairil Anwar', 'Andi Publisher', 2012, 'tersedia', 1),
(51, 'BK0051', 'Etika Profesi TI', 'J.K. Rowling', 'Elex Media Komputindo', 2015, 'tersedia', 2),
(52, 'BK0052', 'Komunikasi Data', 'C.S. Lewis', 'Informatika Bandung', 2025, 'dipinjam', 2),
(53, 'BK0053', 'Harry Potter dan Batu Bertuah', 'Fajar Nugroho', 'Informatika Bandung', 2005, 'tersedia', 5),
(54, 'BK0054', 'Percy Jackson', 'Tere Liye', 'Republika Penerbit', 2013, 'tersedia', 2),
(55, 'BK0055', 'The Chronicles of Narnia', 'Joko Susanto', 'Elex Media Komputindo', 2016, 'tersedia', 7),
(56, 'BK0056', 'Sherlock Holmes', 'Joko Susanto', 'Kompas', 2008, 'dipinjam', 2),
(57, 'BK0057', 'Alice in Wonderland', 'J.K. Rowling', 'Andi Publisher', 2006, 'dipinjam', 0),
(58, 'BK0058', 'Cerita Rakyat Nusantara', 'Hendra Setiawan', 'Kompas', 2011, 'tersedia', 7),
(59, 'BK0059', 'Fabel Dunia', 'Ahmad Fuadi', 'Informatika Bandung', 2024, 'tersedia', 2),
(60, 'BK0060', 'Kumpulan Puisi Chairil Anwar', 'Arthur Conan Doyle', 'Kompas', 2014, 'dipinjam', 1),
(61, 'BK0061', 'Belajar Cepat Excel', 'Budi Santoso', 'Andi Publisher', 2022, 'tersedia', 4),
(62, 'BK0062', 'Belajar Cepat Word', 'Rina Wijaya', 'Elex Media Komputindo', 2010, 'tersedia', 7),
(63, 'BK0063', 'Panduan PowerPoint', 'Fajar Nugroho', 'Gramedia Pustaka Utama', 2014, 'tersedia', 4),
(64, 'BK0064', 'Tutorial Adobe Photoshop', 'Rina Wijaya', 'Republika Penerbit', 2024, 'tersedia', 8),
(65, 'BK0065', 'Desain UI/UX Modern', 'Agus Prasetyo', 'Grasindo', 2011, 'dipinjam', 2),
(66, 'BK0066', 'Framework Laravel', 'Tere Liye', 'Bentang Pustaka', 2014, 'tersedia', 2),
(67, 'BK0067', 'Framework CodeIgniter', 'Rick Riordan', 'Andi Publisher', 2012, 'tersedia', 3),
(68, 'BK0068', 'React JS untuk Pemula', 'Andrea Hirata', 'Gramedia Pustaka Utama', 2012, 'dipinjam', 2),
(69, 'BK0069', 'Vue JS Dasar', 'Ahmad Fuadi', 'Grasindo', 2018, 'tersedia', 7),
(70, 'BK0070', 'Node JS Backend', 'Dewi Kurnia', 'Elex Media Komputindo', 2012, 'tersedia', 1),
(71, 'BK0071', 'Git dan GitHub', 'Habiburrahman El Shirazy', 'Elex Media Komputindo', 2012, 'tersedia', 8),
(72, 'BK0072', 'Docker untuk Pemula', 'Pramoedya Ananta Toer', 'Kompas', 2012, 'tersedia', 8),
(73, 'BK0073', 'Linux Server Administration', 'Dee Lestari', 'Grasindo', 2021, 'tersedia', 8),
(74, 'BK0074', 'Keamanan Siber', 'Joko Susanto', 'Kompas', 2018, 'dipinjam', 0),
(75, 'BK0075', 'Etika Digital', 'Dewi Kurnia', 'Grasindo', 2013, 'tersedia', 5),
(76, 'BK0076', 'Literasi Media', 'Hendra Setiawan', 'Grasindo', 2025, 'tersedia', 5),
(77, 'BK0077', 'Public Speaking', 'Agus Prasetyo', 'Bentang Pustaka', 2014, 'tersedia', 5),
(78, 'BK0078', 'Manajemen Waktu', 'Lewis Carroll', 'Informatika Bandung', 2022, 'tersedia', 3),
(79, 'BK0079', 'Psikologi Remaja', 'Dee Lestari', 'Erlangga', 2017, 'tersedia', 4),
(80, 'BK0080', 'Bimbingan Karir', 'Ahmad Fuadi', 'Elex Media Komputindo', 2018, 'tersedia', 8),
(81, 'BK0081', 'Persiapan Kerja', 'Rina Wijaya', 'Gramedia Pustaka Utama', 2011, 'dipinjam', 1),
(82, 'BK0082', 'Tata Boga Dasar', 'Sari Handayani', 'Gramedia Pustaka Utama', 2023, 'dipinjam', 1),
(83, 'BK0083', 'Perhotelan dan Pariwisata', 'Andrea Hirata', 'Informatika Bandung', 2014, 'dipinjam', 1),
(84, 'BK0084', 'Akuntansi Dasar', 'Fajar Nugroho', 'Kompas', 2024, 'tersedia', 8),
(85, 'BK0085', 'Perpajakan Indonesia', 'Rick Riordan', 'Andi Publisher', 2018, 'dipinjam', 0),
(86, 'BK0086', 'Hukum Bisnis', 'Budi Santoso', 'Informatika Bandung', 2017, 'tersedia', 8),
(87, 'BK0087', 'Sosiologi Pendidikan', 'Dee Lestari', 'Republika Penerbit', 2022, 'tersedia', 7),
(88, 'BK0088', 'Antropologi Budaya', 'Sari Handayani', 'Republika Penerbit', 2005, 'tersedia', 7),
(89, 'BK0089', 'Biologi Molekuler', 'Dee Lestari', 'Grasindo', 2010, 'tersedia', 5),
(90, 'BK0090', 'Anatomi Manusia', 'Budi Santoso', 'Informatika Bandung', 2011, 'dipinjam', 1),
(91, 'BK0091', 'Kesehatan Masyarakat', 'Lewis Carroll', 'Elex Media Komputindo', 2013, 'dipinjam', 1),
(92, 'BK0092', 'Ilmu Gizi Dasar', 'Ahmad Fuadi', 'Grasindo', 2005, 'tersedia', 6),
(93, 'BK0093', 'Farmakologi Dasar', 'Rick Riordan', 'Bentang Pustaka', 2025, 'tersedia', 1),
(94, 'BK0094', 'Keperawatan Dasar', 'Rick Riordan', 'Erlangga', 2005, 'tersedia', 4),
(95, 'BK0095', 'Pertolongan Pertama', 'Dee Lestari', 'Grasindo', 2008, 'tersedia', 8),
(96, 'BK0096', 'Olahraga dan Kesehatan', 'C.S. Lewis', 'Informatika Bandung', 2010, 'tersedia', 3),
(97, 'BK0097', 'Seni Musik Nusantara', 'Arthur Conan Doyle', 'Bentang Pustaka', 2023, 'tersedia', 5),
(98, 'BK0098', 'Seni Tari Tradisional', 'Sari Handayani', 'Elex Media Komputindo', 2017, 'tersedia', 2),
(99, 'BK0099', 'Sejarah Seni Rupa', 'Sari Handayani', 'Erlangga', 2008, 'tersedia', 2),
(100, 'BK0100', 'Fotografi Dasar', 'Sari Handayani', 'Gramedia Pustaka Utama', 2016, 'dipinjam', 2);

-- --------------------------------------------------------
-- Data untuk tabel `peminjaman` (20 data)
-- --------------------------------------------------------
INSERT INTO `peminjaman` (`id_peminjaman`, `id_anggota`, `id_buku`, `tanggal_pinjam`, `tanggal_kembali`, `status`) VALUES
(1, 24, 9, '2026-07-29', NULL, 'dipinjam'),
(2, 1, 54, '2026-07-30', '2026-08-06', 'dikembalikan'),
(3, 30, 91, '2026-08-21', '2026-09-03', 'dikembalikan'),
(4, 34, 84, '2026-08-13', NULL, 'dipinjam'),
(5, 35, 100, '2026-07-31', '2026-08-13', 'dikembalikan'),
(6, 38, 35, '2026-08-10', NULL, 'dipinjam'),
(7, 6, 36, '2026-08-02', '2026-08-11', 'dikembalikan'),
(8, 37, 79, '2026-07-19', '2026-07-21', 'dikembalikan'),
(9, 32, 42, '2026-08-19', '2026-08-26', 'dikembalikan'),
(10, 17, 44, '2026-08-13', NULL, 'dipinjam'),
(11, 18, 72, '2026-08-30', '2026-09-04', 'dikembalikan'),
(12, 6, 31, '2026-08-04', '2026-08-18', 'dikembalikan'),
(13, 16, 89, '2026-07-31', NULL, 'dipinjam'),
(14, 32, 58, '2026-08-29', '2026-09-03', 'dikembalikan'),
(15, 26, 89, '2026-08-15', '2026-08-26', 'dikembalikan'),
(16, 24, 61, '2026-07-26', '2026-08-03', 'dikembalikan'),
(17, 36, 43, '2026-08-08', NULL, 'dipinjam'),
(18, 18, 40, '2026-08-14', '2026-08-27', 'dikembalikan'),
(19, 13, 41, '2026-08-23', NULL, 'dipinjam'),
(20, 12, 25, '2026-08-17', NULL, 'dipinjam');

-- --------------------------------------------------------
-- Update AUTO_INCREMENT agar data baru selanjutnya tidak bentrok
-- --------------------------------------------------------
ALTER TABLE `users` AUTO_INCREMENT = 51;
ALTER TABLE `anggota` AUTO_INCREMENT = 51;
ALTER TABLE `buku` AUTO_INCREMENT = 101;
ALTER TABLE `peminjaman` AUTO_INCREMENT = 21;

COMMIT;