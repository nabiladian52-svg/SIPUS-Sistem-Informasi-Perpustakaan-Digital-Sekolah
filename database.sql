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
