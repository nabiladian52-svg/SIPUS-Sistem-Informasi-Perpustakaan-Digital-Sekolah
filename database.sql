-- =====================================================
-- SIPUS - Sistem Informasi Perpustakaan Digital Sekolah
-- Database Schema + Data Dummy (password Bcrypt)
-- Import via phpMyAdmin atau: mysql -u root < database.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS perpustakaan;
USE perpustakaan;

CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'siswa') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE anggota (
    id_anggota INT AUTO_INCREMENT PRIMARY KEY,
    nomor_anggota VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    CONSTRAINT fk_anggota_user FOREIGN KEY (username) REFERENCES users(username) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE buku (
    id_buku INT AUTO_INCREMENT PRIMARY KEY,
    nomor_buku VARCHAR(20) NOT NULL UNIQUE,
    judul VARCHAR(150) NOT NULL,
    penulis VARCHAR(100) NOT NULL,
    penerbit VARCHAR(100),
    tahun_terbit YEAR,
    status ENUM('tersedia', 'dipinjam') NOT NULL DEFAULT 'tersedia'
) ENGINE=InnoDB;

CREATE TABLE peminjaman (
    id_peminjaman INT AUTO_INCREMENT PRIMARY KEY,
    id_anggota INT NOT NULL,
    id_buku INT NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali DATE NULL,
    status ENUM('dipinjam', 'dikembalikan') NOT NULL DEFAULT 'dipinjam',
    CONSTRAINT fk_peminjaman_anggota FOREIGN KEY (id_anggota) REFERENCES anggota(id_anggota) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_peminjaman_buku FOREIGN KEY (id_buku) REFERENCES buku(id_buku) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- DATA DUMMY (password sudah di-hash Bcrypt)
-- Admin  : admin    / admin123
-- Siswa 1: siswa01  / siswa123
-- Siswa 2: siswa02  / siswa123
-- =====================================================

INSERT INTO users (username, password, role) VALUES
('admin',   '$2y$12$t3XPybIv4/q8ZAdF4JT8xuQOWaRniq/jRpbqw1E57uiFXncS5zm3u', 'admin'),
('siswa01', '$2y$12$ktdaEPKP2wLXsDTIcIIuOumCrni3JsMc17WHvdexWqeDNPs.Y2Iry', 'siswa'),
('siswa02', '$2y$12$ktdaEPKP2wLXsDTIcIIuOumCrni3JsMc17WHvdexWqeDNPs.Y2Iry', 'siswa');

INSERT INTO anggota (nomor_anggota, nama, kelas, username) VALUES
('AG001', 'Budi Santoso', 'XI RPL 1', 'siswa01'),
('AG002', 'Siti Aminah',  'XI RPL 2', 'siswa02');

INSERT INTO buku (nomor_buku, judul, penulis, penerbit, tahun_terbit, status) VALUES
('BK001', 'Pemrograman Web Dasar',        'Andi Wijaya',    'Informatika',    2021, 'tersedia'),
('BK002', 'Basis Data Relasional MySQL',  'Rina Marlina',   'Erlangga',       2020, 'tersedia'),
('BK003', 'Jaringan Komputer & Internet', 'Dedi Kurniawan', 'Gramedia Pustaka', 2019, 'tersedia'),
('BK004', 'Matematika Untuk SMK Kelas XI','Tuti Hartati',   'Yudhistira',     2022, 'tersedia'),
('BK005', 'Bahasa Indonesia Cerdas',      'Maya Lestari',   'Pusat Kurikulum', 2023, 'tersedia');
