-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 14 Jun 2025 pada 12.56
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bumdes_app`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `nomor_anggota` varchar(50) DEFAULT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `anggota`
--

INSERT INTO `anggota` (`id`, `nama`, `alamat`, `nomor_anggota`, `tanggal_bergabung`, `created_at`, `updated_at`) VALUES
(1, 'ica', 'Jl. Cinta Raya, Rt 12/ RW 15', 'ANG001', '2025-05-17', '2025-05-17 06:16:33', '2025-05-17 06:16:33'),
(2, 'Andika', 'hbhs ks', 'ANG002', '2025-05-17', '2025-05-17 13:18:42', '2025-05-17 13:18:42'),
(3, 'Yusuf', 'Jl. Desa Kemuning', 'ANG003', '2025-06-09', '2025-06-09 04:56:01', '2025-06-09 04:56:01'),
(5, 'Aby adinthya', 'Jl.Mohd Kahfi I', 'ANG004', '2025-06-10', '2025-06-10 03:29:39', '2025-06-10 03:29:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `artikel`
--

CREATE TABLE `artikel` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `konten` text NOT NULL,
  `penulis_id` int(11) DEFAULT NULL,
  `tanggal_publikasi` timestamp NOT NULL DEFAULT current_timestamp(),
  `gambar_utama` varchar(255) DEFAULT NULL,
  `status` enum('draft','publikasi') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bukti_pengembalian_gambar`
--

CREATE TABLE `bukti_pengembalian_gambar` (
  `id` int(11) NOT NULL,
  `pengembalian_id` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bumdes`
--

CREATE TABLE `bumdes` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `tahun_berdiri` year(4) DEFAULT NULL,
  `visi` text DEFAULT NULL,
  `misi` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `bumdes`
--

INSERT INTO `bumdes` (`id`, `nama`, `alamat`, `tahun_berdiri`, `visi`, `misi`, `logo`, `kontak`, `created_at`, `updated_at`) VALUES
(1, 'BUMDes Sinar Petir', 'Kantor Desa Sinar Petir, Tanggamus, Lampung Kecamatan Talang Padang Kabupaten Tanggamus Provinsi Lampung Kode Pos 35377', '2022', 'Mewujudkan BUMDes yang mandiri, inovatif, dan berkontribusi signifikan terhadap kesejahteraan masyarakat Desa Sinar Petir.', 'Mengembangkan unit-unit usaha yang berbasis potensi lokal, meningkatkan kualitas produk dan layanan, serta membangun kemitraan yang berkelanjutan.', 'sinar_petir.png', '081272020355', '2025-04-23 21:29:56', '2025-06-09 06:25:07'),
(2, 'BUMDes Maju Bersama', 'Jl. Merdeka No. 10, Desa Sukatani, Kecamatan Makmur Jaya, Kabupaten Sejahtera', '2018', 'Menjadi BUMDes unggulan tingkat kabupaten dengan tata kelola profesional dan memberikan manfaat ekonomi maksimal bagi anggota dan masyarakat.', 'Mengembangkan diversifikasi usaha yang adaptif terhadap perubahan pasar, meningkatkan kapasitas sumber daya manusia, dan menjalin kerjasama yang saling menguntungkan dengan pihak lain.', 'maju_bersama.png', '085698765432', '2025-04-23 21:29:56', '2025-04-23 21:29:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `catatan_transaksi_lain`
--

CREATE TABLE `catatan_transaksi_lain` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `jenis_transaksi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `tipe_saldo` enum('debit','kredit') NOT NULL,
  `tanggal_transaksi` datetime NOT NULL,
  `referensi_id` int(11) DEFAULT NULL,
  `dibuat_oleh` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `variasi_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `nama_produk_saat_beli` varchar(255) NOT NULL,
  `variasi_detail_saat_beli` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id`, `pesanan_id`, `produk_id`, `variasi_id`, `quantity`, `harga_satuan`, `nama_produk_saat_beli`, `variasi_detail_saat_beli`, `created_at`, `updated_at`) VALUES
(1, 10, 3, NULL, 1, 12000.00, 'Lada Hitam Bubuk Lampung', 'N/A', '2025-05-30 11:17:43', '2025-05-30 11:17:43'),
(2, 11, 8, 17, 4, 30000.00, 'Pulpen Faster C600 Hitam, CX5 Biru] - Isi 12 per Pack', 'Warna: Merah', '2025-05-31 00:58:19', '2025-05-31 00:58:19'),
(3, 12, 6, NULL, 1, 8000.00, 'Keripik Ubi Ungu Manis', 'N/A', '2025-05-31 09:53:15', '2025-05-31 09:53:15'),
(4, 13, 7, 13, 7, 5000.00, 'Emping Melinjo Mentah ', 'Rasa: Pedas', '2025-05-31 10:22:04', '2025-05-31 10:22:04'),
(5, 14, 1, NULL, 5, 10000.00, 'Keripik Pisang Lampung', 'N/A', '2025-05-31 10:43:47', '2025-05-31 10:43:47'),
(6, 15, 2, NULL, 3, 45000.00, 'Buku Tulis Tiara Campus - 50 Lembar HVS 80 Gsm', 'N/A', '2025-05-31 11:03:14', '2025-05-31 11:03:14'),
(7, 16, 7, 13, 3, 5000.00, 'Emping Melinjo Mentah ', 'Rasa: Pedas', '2025-06-01 00:42:47', '2025-06-01 00:42:47'),
(8, 17, 3, NULL, 1, 12000.00, 'Lada Hitam Bubuk Lampung', 'N/A', '2025-06-02 13:12:27', '2025-06-02 13:12:27'),
(9, 18, 1, NULL, 12, 10000.00, 'Keripik Pisang Lampung', 'N/A', '2025-06-09 03:03:17', '2025-06-09 03:03:17'),
(10, 19, 1, NULL, 4, 10000.00, 'Keripik Pisang Lampung', 'N/A', '2025-06-09 03:59:17', '2025-06-09 03:59:17'),
(11, 20, 6, NULL, 2, 8000.00, 'Keripik Ubi Ungu Manis', 'N/A', '2025-06-09 04:32:20', '2025-06-09 04:32:20'),
(12, 21, 8, 17, 8, 30000.00, 'Pulpen Faster C600 Hitam, CX5 Biru] - Isi 12 per Pack', 'Warna: Merah', '2025-06-09 04:34:06', '2025-06-09 04:34:06'),
(13, 22, 2, NULL, 4, 45000.00, 'Buku Tulis Tiara Campus - 50 Lembar HVS 80 Gsm', 'N/A', '2025-06-09 04:40:12', '2025-06-09 04:40:12'),
(14, 23, 1, NULL, 2, 10000.00, 'Keripik Pisang Lampung', 'N/A', '2025-06-09 11:26:53', '2025-06-09 11:26:53'),
(19, 28, 3, NULL, 2, 12000.00, 'Lada Hitam Bubuk Lampung', 'N/A', '2025-06-10 00:53:59', '2025-06-10 00:53:59'),
(20, 29, 4, 12, 1, 12000.00, 'Topi Rajut', 'Warna: coklat, Ukuran: M', '2025-06-10 01:07:28', '2025-06-10 01:07:28'),
(21, 30, 7, 13, 3, 5000.00, 'Emping Melinjo Mentah ', 'Rasa: Pedas', '2025-06-10 02:18:51', '2025-06-10 02:18:51'),
(22, 31, 7, 13, 2, 5000.00, 'Emping Melinjo Mentah ', 'Rasa: Pedas', '2025-06-10 03:31:41', '2025-06-10 03:31:41'),
(23, 32, 4, 12, 1, 12000.00, 'Topi Rajut', 'Warna: coklat, Ukuran: M', '2025-06-14 10:44:21', '2025-06-14 10:44:21'),
(24, 33, 8, 19, 1, 30000.00, 'Pulpen Faster C600 Hitam, CX5 Biru] - Isi 12 per Pack', 'Warna: biru', '2025-06-14 10:49:55', '2025-06-14 10:49:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `diskon`
--

CREATE TABLE `diskon` (
  `id` int(11) NOT NULL,
  `nama_diskon` varchar(255) NOT NULL,
  `kode_diskon` varchar(50) DEFAULT NULL,
  `jenis_diskon` enum('persen','fixed') NOT NULL,
  `nilai_diskon` decimal(10,2) NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_berakhir` date DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `diskon`
--

INSERT INTO `diskon` (`id`, `nama_diskon`, `kode_diskon`, `jenis_diskon`, `nilai_diskon`, `tanggal_mulai`, `tanggal_berakhir`, `status`, `created_at`, `updated_at`) VALUES
(4, 'CASHBACKCERIAH', 'CBC15K', 'fixed', 15000.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 11:31:15', '2025-06-12 11:31:15'),
(5, 'DISKONSULTANBANGET', 'DSB40K', 'fixed', 40000.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 11:32:27', '2025-06-12 11:32:27'),
(6, 'HEMATMUSIMPANAS', 'HMP25', 'persen', 25.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 11:34:49', '2025-06-12 11:34:49'),
(7, 'BELAJARUNTUNGTERUS', 'BUT10K', 'fixed', 10000.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 12:37:47', '2025-06-12 12:38:11'),
(8, 'PROMOFLASHGILA', 'PFG30', 'persen', 30.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 12:39:56', '2025-06-12 12:39:56'),
(9, 'HEMATTIAPHARI', 'HTH18', 'persen', 18.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 12:40:55', '2025-06-12 12:40:55'),
(10, 'SUPERDEALKILAT', 'SDK22K', 'fixed', 22000.00, '2025-06-12', '2025-07-12', 'aktif', '2025-06-12 12:42:24', '2025-06-12 12:42:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_produk`
--

CREATE TABLE `kategori_produk` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_produk`
--

INSERT INTO `kategori_produk` (`id`, `nama_kategori`, `deskripsi`, `slug`, `gambar`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'Fashion Pria', 'Berbagai jenis pakaian untuk pria.', 'fashion-pria', '', NULL, '2025-04-23 22:51:19', '2025-05-21 13:03:51'),
(2, 'Fashion Wanita', 'Berbagai jenis pakaian untuk wanita.', 'fashion-wanita', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(3, 'Fashion Anak-anak', 'Berbagai jenis pakaian untuk anak-anak.', 'fashion-anak-anak', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(4, 'Pakaian Muslim Pria', 'Pakaian muslim untuk pria.', 'pakaian-muslim-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(5, 'Pakaian Muslim Wanita', 'Pakaian muslim untuk wanita.', 'pakaian-muslim-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(6, 'Pakaian Olahraga Pria', 'Pakaian untuk aktivitas olahraga pria.', 'pakaian-olahraga-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(7, 'Pakaian Olahraga Wanita', 'Pakaian untuk aktivitas olahraga wanita.', 'pakaian-olahraga-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(8, 'Pakaian Dalam Pria', 'Pakaian dalam untuk pria.', 'pakaian-dalam-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(9, 'Pakaian Dalam Wanita', 'Pakaian dalam untuk wanita.', 'pakaian-dalam-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(10, 'Tas Pria', 'Berbagai jenis tas untuk pria.', 'tas-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(11, 'Tas Wanita', 'Berbagai jenis tas untuk wanita.', 'tas-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(12, 'Sepatu Pria', 'Berbagai jenis sepatu untuk pria.', 'sepatu-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(13, 'Sepatu Wanita', 'Berbagai jenis sepatu untuk wanita.', 'sepatu-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(14, 'Perhiasan Pria', 'Berbagai jenis perhiasan untuk pria.', 'perhiasan-pria', NULL, 1, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(15, 'Perhiasan Wanita', 'Berbagai jenis perhiasan untuk wanita.', 'perhiasan-wanita', NULL, 2, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(16, 'Smartphone', 'Telepon pintar berbagai merek.', 'smartphone', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(17, 'Laptop', 'Komputer portabel berbagai spesifikasi.', 'laptop', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(18, 'Tablet', 'Komputer tablet berbagai ukuran.', 'tablet', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(19, 'Televisi', 'Perangkat televisi berbagai ukuran dan teknologi.', 'televisi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(20, 'Audio', 'Perangkat audio seperti speaker dan headphone.', 'audio', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(21, 'Kamera', 'Kamera digital dan aksesorinya.', 'kamera', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(22, 'Aksesoris Smartphone', 'Aksesoris untuk smartphone.', 'aksesoris-smartphone', NULL, 16, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(23, 'Aksesoris Laptop', 'Aksesoris untuk laptop.', 'aksesoris-laptop', NULL, 17, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(24, 'Aksesoris Tablet', 'Aksesoris untuk tablet.', 'aksesoris-tablet', NULL, 18, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(25, 'Sofa', 'Tempat duduk panjang untuk ruang tamu.', 'sofa', '68258994b85d8_sofa.avif', NULL, '2025-04-23 22:51:19', '2025-05-15 06:28:36'),
(26, 'Lemari', 'Tempat penyimpanan pakaian dan barang.', 'lemari', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(27, 'Tempat Tidur', 'Tempat untuk beristirahat.', 'tempat-tidur', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(28, 'Alat Makan', 'Peralatan untuk makan.', 'alat-makan', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(29, 'Dekorasi Rumah', 'Hiasan untuk mempercantik rumah.', 'dekorasi-rumah', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(30, 'Kulkas', 'Alat pendingin makanan dan minuman.', 'kulkas', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(31, 'Mesin Cuci', 'Alat untuk mencuci pakaian.', 'mesin-cuci', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(32, 'Bahan Makanan Pokok', 'Bahan makanan utama seperti beras, gula, minyak.', 'bahan-makanan-pokok', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(33, 'Minuman', 'Berbagai jenis minuman.', 'minuman', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(34, 'Produk Perawatan Diri', 'Produk untuk menjaga kebersihan dan kesehatan diri.', 'produk-perawatan-diri', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(35, 'Perlengkapan Kebersihan Rumah', 'Alat dan bahan untuk membersihkan rumah.', 'perlengkapan-kebersihan-rumah', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(36, 'Makanan Hewan Peliharaan', 'Makanan untuk berbagai jenis hewan peliharaan.', 'makanan-hewan-peliharaan', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(37, 'Perlengkapan Hewan Peliharaan', 'Aksesoris dan perlengkapan untuk hewan peliharaan.', 'perlengkapan-hewan-peliharaan', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(38, 'Perawatan Kulit Wajah', 'Produk untuk merawat kulit wajah.', 'perawatan-kulit-wajah', NULL, 34, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(39, 'Perawatan Kulit Tubuh', 'Produk untuk merawat kulit tubuh.', 'perawatan-kulit-tubuh', NULL, 34, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(40, 'Makeup Wajah', 'Produk kosmetik untuk wajah.', 'makeup-wajah', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(41, 'Makeup Mata', 'Produk kosmetik untuk mata.', 'makeup-mata', NULL, 39, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(42, 'Perawatan Rambut', 'Produk untuk merawat rambut.', 'perawatan-rambut', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(43, 'Vitamin & Suplemen', 'Tambahan nutrisi untuk kesehatan.', 'vitamin-suplemen', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(44, 'Alat Kesehatan Pribadi', 'Alat-alat kesehatan untuk penggunaan pribadi.', 'alat-kesehatan-pribadi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(45, 'Pakaian Bayi', 'Pakaian untuk bayi.', 'pakaian-bayi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(46, 'Perlengkapan Menyusui', 'Peralatan dan perlengkapan untuk ibu menyusui.', 'perlengkapan-menyusui', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(47, 'Mainan Bayi & Anak-anak', 'Berbagai jenis mainan untuk bayi dan anak-anak.', 'mainan-bayi-anak-anak', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(48, 'Stroller & Car Seat', 'Kereta dorong dan kursi mobil untuk bayi.', 'stroller-car-seat', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(49, 'Produk Perawatan Bayi', 'Produk untuk merawat bayi.', 'produk-perawatan-bayi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(50, 'Alat Fitness', 'Peralatan untuk berolahraga di rumah.', 'alat-fitness', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(51, 'Perlengkapan Camping', 'Peralatan untuk kegiatan berkemah.', 'perlengkapan-camping', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(52, 'Pakaian Olahraga', 'Pakaian khusus untuk berolahraga.', 'pakaian-olahraga', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(53, 'Aksesoris Mobil', 'Tambahan untuk mobil.', 'aksesoris-mobil', '', 55, '2025-04-23 22:51:19', '2025-05-15 04:14:52'),
(54, 'Suku Cadang Mobil', 'Bagian-bagian pengganti untuk mobil.', 'suku-cadang-mobil', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(55, 'Perawatan Mobil', 'Produk untuk merawat mobil.', 'perawatan-mobil', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(56, 'Aksesoris Motor', 'Tambahan untuk motor.', 'aksesoris-motor', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(57, 'Suku Cadang Motor', 'Bagian-bagian pengganti untuk motor.', 'suku-cadang-motor', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(58, 'Perawatan Motor', 'Produk untuk merawat motor.', 'perawatan-motor', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(59, 'Buku Fiksi', 'Buku cerita dan novel.', 'buku-fiksi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(60, 'Buku Non-Fiksi', 'Buku pengetahuan dan informasi.', 'buku-non-fiksi', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(61, 'Buku Pelajaran', 'Buku untuk keperluan pendidikan.', 'buku-pelajaran', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(62, 'Alat Tulis Kantor', 'Peralatan untuk menulis dan keperluan kantor.', 'alat-tulis-kantor', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(63, 'Perlengkapan Kantor Lain', 'Barang-barang lain untuk keperluan kantor.', 'perlengkapan-kantor-lain', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(64, 'Action Figure', 'Figur karakter koleksi.', 'action-figure', '6825912b5dd14_figure.jpg', NULL, '2025-04-23 22:51:19', '2025-05-15 07:00:59'),
(65, 'Model Kit', 'Kit rakitan berbagai model.', 'model-kit', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(66, 'Alat Musik', 'Berbagai jenis alat musik.', 'alat-musik', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(67, 'Perlengkapan Seni Rupa', 'Alat dan bahan untuk seni rupa.', 'perlengkapan-seni-rupa', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(68, 'Barang Koleksi Lain', 'Barang-barang unik untuk dikoleksi.', 'barang-koleksi-lain', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(69, 'Anyaman Lokal', 'Produk anyaman khas daerah.', 'anyaman-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(70, 'Ukiran Kayu Lokal', 'Produk ukiran kayu khas daerah.', 'ukiran-kayu-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(71, 'Batik Lokal', 'Kain batik khas daerah.', 'batik-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(72, 'Souvenir Khas', 'Cenderamata khas daerah.', 'souvenir-khas', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(73, 'Sayuran Segar Organik', 'Sayuran segar yang ditanam secara organik.', 'sayuran-segar-organik', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(74, 'Buah-buahan Musiman Lokal', 'Buah-buahan segar yang sedang musim di daerah tersebut.', 'buah-buahan-musiman-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(75, 'Rempah-rempah Lokal', 'Berbagai jenis rempah-rempah khas daerah.', 'rempah-rempah-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(76, 'Keripik Khas Desa', 'Keripik dengan rasa khas desa.', 'keripik-khas-desa', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(77, 'Abon Lokal', 'Abon daging atau ikan khas daerah.', 'abon-lokal', '68258a8f7307e_abon.jpg', 32, '2025-04-23 22:51:19', '2025-05-21 13:02:47'),
(78, 'Selai Buah Lokal', 'Selai yang terbuat dari buah-buahan lokal.', 'selai-buah-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(79, 'Sirup Buah Lokal', 'Sirup yang terbuat dari buah-buahan lokal.', 'sirup-buah-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(80, 'Minuman Tradisional Desa', 'Minuman khas tradisional dari desa.', 'minuman-tradisional-desa', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(81, 'Alat Produksi UMKM Skala Kecil', 'Peralatan produksi untuk usaha mikro kecil.', 'alat-produksi-umkm-skala-kecil', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(82, 'Kemasan Produk UMKM', 'Berbagai jenis kemasan untuk produk UMKM.', 'kemasan-produk-umkm', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(83, 'Bahan Baku UMKM Lokal', 'Bahan-bahan mentah untuk produksi UMKM.', 'bahan-baku-umkm-lokal', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(84, 'Penyewaan Alat Pertanian', 'Layanan penyewaan alat-alat pertanian.', 'penyewaan-alat-pertanian', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(85, 'Penyewaan Tenda & Perlengkapan Acara', 'Layanan penyewaan tenda dan perlengkapan acara.', 'penyewaan-tenda-perlengkapan-acara', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19'),
(86, 'Jasa Konsultasi Bisnis Online', 'Layanan konsultasi bisnis secara online.', 'jasa-konsultasi-bisnis-online', NULL, NULL, '2025-04-23 22:51:19', '2025-04-23 22:51:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keranjang_customer`
--

CREATE TABLE `keranjang_customer` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `variasi_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `tanggal_ditambahkan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `keranjang_customer`
--

INSERT INTO `keranjang_customer` (`id`, `customer_id`, `produk_id`, `variasi_id`, `quantity`, `tanggal_ditambahkan`) VALUES
(23, 4, 2, NULL, 2, '2025-06-10 02:39:34'),
(26, 18, 2, NULL, 5, '2025-06-12 11:37:52'),
(27, 18, 7, 13, 1, '2025-06-14 10:47:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kurir`
--

CREATE TABLE `kurir` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `nomor_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `area_pengiriman` text DEFAULT NULL,
  `status` enum('pending','aktif','nonaktif') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kurir`
--

INSERT INTO `kurir` (`id`, `nama`, `nomor_telepon`, `email`, `foto`, `area_pengiriman`, `status`, `created_at`, `updated_at`, `username`, `password`) VALUES
(1, 'J&T Express', '087654321098', 'jnt@example.com', 'jnt_logo.png', 'Jawa, Bali, Sumatra', 'aktif', '2025-05-30 10:21:01', '2025-06-09 07:39:28', 'jnt_lpg1', '$2y$10$dUhd8MxpmpY8WFCJ4lvdruNodi9GnZ3PtdBNy7Mdtt.g1IA0HsR.G'),
(2, 'SiCepat', '089988776655', 'sicepat@example.com', 'sicepat_logo.png', 'Jabodetabek', 'aktif', '2025-05-30 10:21:01', '2025-06-09 05:47:05', 'sicepat_admin', 'sicepat1234@!'),
(3, 'JNE Express', '081234567890', 'jne@example.com', 'jne_logo.png', 'Seluruh Indonesia', 'aktif', '2025-06-09 06:56:12', '2025-06-13 09:18:04', 'jne_lpg1', '$2y$10$g6sNvWxP.uw59qMvppRsoOL5CUnl/DxKEbmRV2P7wO34DnFaB.era');

-- --------------------------------------------------------

--
-- Struktur dari tabel `metode_pembayaran`
--

CREATE TABLE `metode_pembayaran` (
  `id` int(11) NOT NULL,
  `nama_metode` varchar(255) NOT NULL,
  `kode_metode` varchar(50) NOT NULL,
  `logo_metode` varchar(255) DEFAULT NULL,
  `tipe_pembayaran` enum('bank_transfer','e_wallet','qris','lainnya') DEFAULT 'bank_transfer',
  `aktif_platform` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `metode_pembayaran`
--

INSERT INTO `metode_pembayaran` (`id`, `nama_metode`, `kode_metode`, `logo_metode`, `tipe_pembayaran`, `aktif_platform`, `created_at`, `updated_at`) VALUES
(1, 'Transfer Bank', 'BANK_TRANSFER', 'default_bank.png', 'bank_transfer', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(2, 'Transfer Bank Mandiri', 'MANDIRI_TF', 'mandiri.png', 'bank_transfer', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(3, 'Transfer Bank BCA', 'BCA_TF', 'bca.png', 'bank_transfer', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(4, 'OVO', 'OVO', 'ovo.png', 'e_wallet', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(5, 'GoPay', 'GOPAY', 'gopay.png', 'e_wallet', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(6, 'DANA', 'DANA', 'dana.png', 'e_wallet', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(7, 'Transfer Bank Lampung', 'Lampung_TF', 'Lampung.png', 'bank_transfer', 1, '2025-05-25 07:56:55', '2025-06-12 19:52:15'),
(8, 'Tunai', 'TUNAI', NULL, 'lainnya', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55'),
(9, 'COD', 'COD', NULL, 'lainnya', 1, '2025-05-25 07:56:55', '2025-05-25 07:56:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `pengguna_id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `nomor_telepon` varchar(20) DEFAULT NULL,
  `status` enum('pending','aktif','nonaktif') NOT NULL DEFAULT 'pending',
  `nomor_anggota` varchar(50) DEFAULT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('pria','wanita','lainnya') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggan`
--

INSERT INTO `pelanggan` (`pengguna_id`, `nama`, `email`, `username`, `password`, `foto`, `alamat`, `nomor_telepon`, `status`, `nomor_anggota`, `tanggal_bergabung`, `tanggal_lahir`, `jenis_kelamin`, `created_at`, `updated_at`) VALUES
(4, 'ica', 'ica@gmail.com', 'Pelanggan', '$2y$10$AZ9oS0U2hIrt.Wvo3NQW0uLpJy.d1EGCCnCOxJoZnapS6IcXarJgS', 'Danisya.jpeg', 'Jl. Cinta Raya, Rt 12/ RW 15', '089648768788', 'aktif', 'ANG001', '2025-05-17', '1989-06-15', 'wanita', '2025-05-17 06:16:33', '2025-06-13 05:03:39'),
(15, 'Andika', 'andika@gmail.com', 'andika', '$2y$10$FNMWXsVoVCzMPVu8eZFpIeM0gCRad9fSRCflOywJm7PLkuQ/DBN62', 'abon.jpg', 'hbhs ks', '086754427778', 'aktif', 'ANG002', '2025-05-17', '1989-01-10', 'pria', '2025-05-17 13:18:42', '2025-05-17 13:21:31'),
(18, 'Aby adinthya', 'adinthyaaby@gmail.com', 'aby', '$2y$10$FTlQsmzYw7fBrFVdKJ9Pz.f28rlaUooidXrgywccxs5I3zozEYwvG', 'Screenshot (94).png', 'Jl.Mohd Kahfi I', '085117013527', 'pending', 'ANG004', '2025-06-10', '2001-04-10', 'pria', '2025-06-10 03:29:39', '2025-06-10 03:29:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `tanggal_pembayaran` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `metode_pembayaran` varchar(250) NOT NULL,
  `jumlah_bayar` decimal(15,2) NOT NULL,
  `status_pembayaran` enum('belum_bayar','sudah_bayar','menunggu_konfirmasi') NOT NULL DEFAULT 'belum_bayar',
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `pesanan_id`, `tanggal_pembayaran`, `metode_pembayaran`, `jumlah_bayar`, `status_pembayaran`, `bukti_transfer`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 16, '2025-06-01 17:00:00', 'DANA', 15000.00, '', 'bukti_pembayaran/bukti_683d9fa413b13.png', '', '2025-06-02 12:57:08', '2025-06-03 04:05:43'),
(8, 28, '2025-06-10 00:53:59', 'Transfer Bank BCA', 34000.00, 'belum_bayar', '', 'Pembayaran untuk pesanan ORD1749516839227', '2025-06-10 00:53:59', '2025-06-10 00:53:59'),
(9, 29, '2025-06-10 01:07:28', 'Transfer Bank BCA', 22000.00, 'belum_bayar', '', 'Pembayaran untuk pesanan ORD1749517648297', '2025-06-10 01:07:28', '2025-06-10 01:07:28'),
(10, 30, '2025-06-09 17:00:00', 'Transfer Bank Mandiri', 25000.00, '', 'bukti_pembayaran/bukti_684796eaad65c.png', '', '2025-06-10 02:18:50', '2025-06-10 03:43:36'),
(11, 31, '2025-06-10 03:31:41', 'Transfer Bank Mandiri', 20000.00, 'belum_bayar', '', 'Pembayaran untuk pesanan ORD1749526301546', '2025-06-10 03:31:41', '2025-06-10 03:31:41'),
(12, 32, '2025-06-14 10:44:21', 'COD', 22000.00, 'sudah_bayar', '', 'Pembayaran untuk pesanan ORD1749897861344', '2025-06-14 10:44:21', '2025-06-14 10:44:21'),
(13, 33, '2025-06-14 10:49:55', 'COD', 40000.00, 'sudah_bayar', '', 'Pembayaran untuk pesanan ORD1749898195789', '2025-06-14 10:49:55', '2025-06-14 10:49:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penarikan_dana`
--

CREATE TABLE `penarikan_dana` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `jumlah_penarikan` decimal(10,2) NOT NULL,
  `tanggal_permintaan` datetime DEFAULT current_timestamp(),
  `status_penarikan` enum('pending','diproses','selesai','ditolak') DEFAULT 'pending',
  `metode_penarikan` varchar(50) NOT NULL,
  `nama_bank` varchar(100) NOT NULL,
  `nomor_rekening` varchar(100) NOT NULL,
  `nama_pemilik_rekening` varchar(100) NOT NULL,
  `catatan_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_kurir_penjual`
--

CREATE TABLE `pengaturan_kurir_penjual` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `kurir_id` int(11) NOT NULL,
  `biaya_default` decimal(10,2) DEFAULT 0.00,
  `tipe_biaya` enum('flat_rate','per_berat','dinamis_api') DEFAULT 'flat_rate',
  `aktif_penjual` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_pembayaran_penjual`
--

CREATE TABLE `pengaturan_pembayaran_penjual` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `metode_pembayaran_id` int(11) NOT NULL,
  `detail_akun` varchar(255) DEFAULT NULL,
  `nama_pemilik_akun` varchar(255) DEFAULT NULL,
  `aktif_penjual` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pengaturan_pembayaran_penjual`
--

INSERT INTO `pengaturan_pembayaran_penjual` (`id`, `pengguna_id`, `metode_pembayaran_id`, `detail_akun`, `nama_pemilik_akun`, `aktif_penjual`, `created_at`, `updated_at`) VALUES
(1, 3, 9, '', '', 1, '2025-05-27 09:40:53', '2025-06-10 09:16:53'),
(2, 3, 6, '085775996740', 'Aby adinthya', 1, '2025-05-27 09:40:53', '2025-06-10 09:16:53'),
(3, 3, 5, '', '', 0, '2025-05-27 09:40:53', '2025-06-10 09:16:53'),
(4, 3, 4, '', '', 0, '2025-05-27 09:40:54', '2025-06-10 09:16:53'),
(5, 3, 7, '', '', 0, '2025-05-27 09:40:54', '2025-06-10 09:16:53'),
(6, 3, 1, '', '', 0, '2025-05-27 09:40:54', '2025-06-10 09:16:53'),
(7, 3, 3, '3456788888999009', 'XANDER', 1, '2025-05-27 09:40:54', '2025-06-10 09:16:53'),
(8, 3, 2, '4536778990989', 'Welly', 1, '2025-05-27 09:40:54', '2025-06-10 09:16:53'),
(9, 3, 8, '', '', 0, '2025-05-27 09:40:54', '2025-06-10 09:16:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian_barang`
--

CREATE TABLE `pengembalian_barang` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `tanggal_pengajuan` timestamp NOT NULL DEFAULT current_timestamp(),
  `alasan_pengembalian` text NOT NULL,
  `bukti_video_cacat` varchar(255) DEFAULT NULL,
  `bukti_foto_cacat` varchar(255) DEFAULT NULL,
  `status_pengembalian` enum('diajukan','disetujui','ditolak','selesai') NOT NULL DEFAULT 'diajukan',
  `tanggal_persetujuan` timestamp NULL DEFAULT NULL,
  `tanggal_penolakan` timestamp NULL DEFAULT NULL,
  `catatan_admin` text DEFAULT NULL,
  `tindakan_pengembalian` enum('ganti_baru','refund','lainnya') NOT NULL,
  `detail_tindakan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `role` enum('admin','pengurus','ketua','bendahara','operator_unit','pelanggan') NOT NULL DEFAULT 'pengurus',
  `unit_usaha_id` int(11) DEFAULT NULL,
  `terakhir_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`id`, `nama`, `username`, `password`, `email`, `foto`, `role`, `unit_usaha_id`, `terakhir_login`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Danisya Yulianti Pratiwi', 'admin', '$2y$10$DVXr8AZxTSvdhV17A0qZoOPkHXiCGCgBdZT.CsClLW0UkI.eLVKYq', 'danisyapratiwi@gmail.com', 'Danisya.jpeg', 'admin', NULL, '2025-06-09 06:17:50', 'aktif', '2025-04-23 07:12:28', '2025-06-09 06:17:50'),
(3, 'Mahesa Ahmad Zulfikarr', 'Penjual', '$2y$10$Y5DzOLbTS.4zP1IdYLe1NOslKRhYpbP8jo3zxn4EwB1HIsRVBvikO', 'mahesa@gmail.com', 'foto backgoround merah_danisya.jpeg', '', NULL, '2025-05-15 09:25:05', 'aktif', '2025-04-23 08:36:38', '2025-05-15 09:25:05'),
(4, 'ica', 'Pelanggan', '$2y$10$AZ9oS0U2hIrt.Wvo3NQW0uLpJy.d1EGCCnCOxJoZnapS6IcXarJgS', 'ica@gmail.com', 'update-LOGO-41jkt-w100-1.png', 'pelanggan', NULL, '2025-06-09 06:11:41', 'aktif', '2025-05-17 06:16:33', '2025-06-09 06:11:41'),
(15, 'Andika', 'andika', '$2y$10$FNMWXsVoVCzMPVu8eZFpIeM0gCRad9fSRCflOywJm7PLkuQ/DBN62', 'andika@gmail.com', 'abon.jpg', '', NULL, '2025-05-17 13:18:42', 'aktif', '2025-05-17 13:18:42', '2025-05-17 13:18:42'),
(16, 'Albet', 'Ketdes', '$2y$10$3fvUYjgTDM93KwTatlfA0u6Xhd8mLL3xACX6Da9tA03HuUfWVCW9G', 'albet123@gmail.com', 'Screenshot (93).png', 'ketua', NULL, '2025-06-09 07:54:56', 'aktif', '2025-06-09 07:54:56', '2025-06-09 07:54:56'),
(18, 'Aby adinthya', 'aby', '$2y$10$FTlQsmzYw7fBrFVdKJ9Pz.f28rlaUooidXrgywccxs5I3zozEYwvG', 'adinthyaaby@gmail.com', 'Screenshot (94).png', 'pelanggan', NULL, '2025-06-10 03:29:39', 'aktif', '2025-06-10 03:29:39', '2025-06-10 03:29:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengiriman`
--

CREATE TABLE `pengiriman` (
  `id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `kurir_id` int(11) DEFAULT NULL,
  `tanggal_pengiriman` date DEFAULT NULL,
  `status_pengiriman` enum('menunggu_konfirmasi','diproses','dikirim','tiba','gagal') NOT NULL DEFAULT 'menunggu_konfirmasi',
  `nomor_resi` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengiriman`
--

INSERT INTO `pengiriman` (`id`, `pesanan_id`, `kurir_id`, `tanggal_pengiriman`, `status_pengiriman`, `nomor_resi`, `keterangan`, `created_at`, `updated_at`) VALUES
(4, 10, 1, '2025-05-30', 'menunggu_konfirmasi', '', '', '2025-05-30 11:17:43', '2025-05-30 11:17:43'),
(5, 11, 1, '2025-05-31', 'menunggu_konfirmasi', '', '', '2025-05-31 00:58:19', '2025-05-31 00:58:19'),
(6, 12, 1, '2025-05-31', 'menunggu_konfirmasi', '', '', '2025-05-31 09:53:15', '2025-05-31 09:53:15'),
(7, 13, 1, '2025-05-31', 'menunggu_konfirmasi', '', '', '2025-05-31 10:22:04', '2025-05-31 10:22:04'),
(8, 14, 1, '2025-05-31', 'menunggu_konfirmasi', '', '', '2025-05-31 10:43:47', '2025-05-31 10:43:47'),
(9, 15, 1, '2025-05-31', 'menunggu_konfirmasi', '', '', '2025-05-31 11:03:14', '2025-05-31 11:03:14'),
(10, 16, 1, '2025-06-01', 'menunggu_konfirmasi', '', '', '2025-06-01 00:42:47', '2025-06-01 00:42:47'),
(11, 17, 1, '2025-06-02', 'menunggu_konfirmasi', '', '', '2025-06-02 13:12:27', '2025-06-02 13:12:27'),
(12, 18, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 03:03:17', '2025-06-09 03:03:17'),
(13, 19, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 03:59:17', '2025-06-09 03:59:17'),
(14, 20, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 04:32:20', '2025-06-09 04:32:20'),
(15, 21, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 04:34:06', '2025-06-09 04:34:06'),
(16, 22, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 04:40:12', '2025-06-09 04:40:12'),
(17, 20, 1, '2025-06-09', 'tiba', NULL, NULL, '2025-06-09 10:29:35', '2025-06-09 10:30:34'),
(18, 23, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 11:26:53', '2025-06-09 11:26:53'),
(19, 24, 1, '2025-06-09', 'menunggu_konfirmasi', '', '', '2025-06-09 12:20:34', '2025-06-09 12:20:34'),
(20, 25, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 00:23:59', '2025-06-10 00:23:59'),
(21, 26, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 00:34:11', '2025-06-10 00:34:11'),
(22, 27, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 00:42:53', '2025-06-10 00:42:53'),
(23, 28, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 00:53:59', '2025-06-10 00:53:59'),
(24, 29, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 01:07:28', '2025-06-10 01:07:28'),
(25, 30, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 02:18:50', '2025-06-10 02:18:50'),
(26, 31, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-10 03:31:41', '2025-06-10 03:31:41'),
(27, 16, 1, '2025-06-13', 'tiba', NULL, NULL, '2025-06-13 05:04:52', '2025-06-13 10:10:43'),
(28, 32, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-14 10:44:21', '2025-06-14 10:44:21'),
(29, 33, 1, NULL, 'menunggu_konfirmasi', '', '', '2025-06-14 10:49:55', '2025-06-14 10:49:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjual`
--

CREATE TABLE `penjual` (
  `pengguna_id` int(11) NOT NULL,
  `nama_toko` varchar(255) NOT NULL,
  `nama_pemilik` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `deskripsi_toko` text NOT NULL,
  `kebijakan_pengembalian` text NOT NULL,
  `kebijakan_pengiriman` text NOT NULL,
  `jam_operasional` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `status_toko` enum('aktif','tidak_aktif','libur') NOT NULL,
  `nomor_telepon` varchar(20) DEFAULT NULL,
  `status` enum('pending','aktif','nonaktif') NOT NULL DEFAULT 'pending',
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('pria','wanita','lainnya') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penjual`
--

INSERT INTO `penjual` (`pengguna_id`, `nama_toko`, `nama_pemilik`, `email`, `username`, `password`, `foto`, `deskripsi_toko`, `kebijakan_pengembalian`, `kebijakan_pengiriman`, `jam_operasional`, `alamat`, `status_toko`, `nomor_telepon`, `status`, `tanggal_lahir`, `jenis_kelamin`, `created_at`, `updated_at`) VALUES
(3, 'Artomoro Sukses', ' Al-Fatih Elektronik', 'mahesa@gmail.com', 'Penjual', '$2y$10$Y5DzOLbTS.4zP1IdYLe1NOslKRhYpbP8jo3zxn4EwB1HIsRVBvikO', 'penjual.jpeg', '', '', '', '', 'Lokimola papua barat', 'aktif', '082676775688', 'aktif', '2000-01-19', 'pria', '2025-04-23 08:36:38', '2025-06-12 12:33:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesan`
--

CREATE TABLE `pesan` (
  `id` int(11) NOT NULL,
  `pengirim_pengguna_id` int(11) NOT NULL,
  `penerima_pengguna_id` int(11) NOT NULL,
  `isi_pesan` text NOT NULL,
  `waktu_kirim` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_baca` enum('belum_dibaca','sudah_dibaca') DEFAULT 'belum_dibaca',
  `status_pesan` enum('aktif','dihapus','arsip') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `kurir_id` int(11) DEFAULT NULL,
  `tanggal_pesanan` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_pesanan` enum('menunggu_pembayaran','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
  `metode_pembelian` varchar(100) DEFAULT NULL,
  `metode_pengiriman` enum('ambil_tempat','antar') NOT NULL,
  `alamat_pengiriman` text DEFAULT NULL,
  `ongkos_kirim` decimal(10,2) DEFAULT 0.00,
  `total_harga` decimal(15,2) NOT NULL,
  `kode_unik` varchar(50) DEFAULT NULL,
  `catatan_pelanggan` text DEFAULT NULL,
  `bukti_pengiriman` varchar(250) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id`, `pelanggan_id`, `kurir_id`, `tanggal_pesanan`, `status_pesanan`, `metode_pembelian`, `metode_pengiriman`, `alamat_pengiriman`, `ongkos_kirim`, `total_harga`, `kode_unik`, `catatan_pelanggan`, `bukti_pengiriman`, `created_at`, `updated_at`) VALUES
(10, 4, 1, '2025-05-30 06:17:43', 'dibatalkan', '', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 12000.00, 'ORD1748603863566', '', '', '2025-05-30 11:17:43', '2025-05-31 08:41:01'),
(11, 4, 1, '2025-05-30 19:58:19', 'dibatalkan', '', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 120000.00, 'ORD1748653099512', '', '', '2025-05-31 00:58:19', '2025-05-31 09:52:15'),
(12, 4, 1, '2025-05-31 04:53:15', 'dibatalkan', 'DANA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 8000.00, 'ORD1748685195593', '', '', '2025-05-31 09:53:15', '2025-06-01 00:37:52'),
(13, 4, 1, '2025-05-31 05:22:04', 'dibatalkan', 'DANA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 35000.00, 'ORD1748686924997', '', '', '2025-05-31 10:22:04', '2025-05-31 10:32:06'),
(14, 4, 1, '2025-05-31 05:43:47', 'dibatalkan', 'COD', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 50000.00, 'ORD1748688227215', '', '', '2025-05-31 10:43:47', '2025-06-01 00:36:33'),
(15, 4, 1, '2025-05-31 06:03:14', 'selesai', 'COD', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 135000.00, 'ORD1748689394366', 'ya', '', '2025-05-31 11:03:14', '2025-06-01 12:52:16'),
(16, 4, 1, '2025-05-31 19:42:47', 'selesai', 'DANA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 15000.00, 'ORD1748738567744', '', '', '2025-06-01 00:42:47', '2025-06-13 10:10:43'),
(17, 4, 1, '2025-06-02 08:12:27', '', '6', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 12000.00, 'ORD1748869947136', '', '', '2025-06-02 13:12:27', '2025-06-03 12:04:10'),
(18, 4, 1, '2025-06-08 22:03:17', 'selesai', 'COD', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 120000.00, 'ORD1749438197267', 'ya', '', '2025-06-09 03:03:17', '2025-06-09 03:04:22'),
(19, 4, 1, '2025-06-08 22:59:17', '', 'Transfer Bank BCA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 40000.00, 'ORD1749441557120', '', '', '2025-06-09 03:59:17', '2025-06-09 04:00:21'),
(20, 4, 1, '2025-06-08 23:32:20', 'selesai', 'COD', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 16000.00, 'ORD1749443540715', '', 'delivery_684c08a77c142.png', '2025-06-09 04:32:20', '2025-06-13 11:16:55'),
(21, 4, 1, '2025-06-08 23:34:06', 'dibatalkan', 'DANA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 240000.00, 'ORD1749443646275', 'ya', '', '2025-06-09 04:34:06', '2025-06-09 04:34:38'),
(22, 4, 1, '2025-06-08 23:40:12', 'dibatalkan', 'DANA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 180000.00, 'ORD1749444012314', '', '', '2025-06-09 04:40:12', '2025-06-09 04:40:53'),
(23, 4, 1, '2025-06-09 06:26:53', '', 'Transfer Bank BCA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 20000.00, 'ORD1749468413668', '', '', '2025-06-09 11:26:53', '2025-06-09 11:36:56'),
(24, 4, 1, '2025-06-09 07:20:34', 'dibatalkan', 'Transfer Bank BCA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 60000.00, 'ORD1749471634320', '', '', '2025-06-09 12:20:34', '2025-06-10 00:41:11'),
(25, 4, 1, '2025-06-09 19:23:59', 'dibatalkan', 'Transfer Bank BCA', 'antar', '0', 10000.00, 40000.00, 'ORD1749515039634', 'iya', '', '2025-06-10 00:23:59', '2025-06-10 00:41:06'),
(26, 4, 1, '2025-06-09 19:34:11', 'dibatalkan', 'Transfer Bank BCA', 'antar', '0', 10000.00, 145000.00, 'ORD1749515651320', '', '', '2025-06-10 00:34:11', '2025-06-10 00:41:02'),
(27, 4, 1, '2025-06-09 19:42:53', 'menunggu_pembayaran', 'Transfer Bank BCA', 'antar', '0', 10000.00, 46000.00, 'ORD1749516173639', '', '', '2025-06-10 00:42:53', '2025-06-10 00:42:53'),
(28, 4, 1, '2025-06-09 19:53:59', 'dibatalkan', 'Transfer Bank BCA', 'antar', '0', 10000.00, 34000.00, 'ORD1749516839227', '', '', '2025-06-10 00:53:59', '2025-06-10 01:06:11'),
(29, 4, 1, '2025-06-09 20:07:28', 'menunggu_pembayaran', 'Transfer Bank BCA', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 22000.00, 'ORD1749517648297', '', '', '2025-06-10 01:07:28', '2025-06-10 01:07:28'),
(30, 4, 1, '2025-06-09 21:18:50', 'dikirim', 'Transfer Bank Mandiri', 'antar', 'Jl. Cinta Raya, Rt 12/ RW 15', 10000.00, 25000.00, 'ORD1749521930896', '', '', '2025-06-10 02:18:50', '2025-06-10 02:51:57'),
(31, 18, 1, '2025-06-09 22:31:41', 'menunggu_pembayaran', 'Transfer Bank Mandiri', 'antar', 'Jl.Mohd Kahfi I', 10000.00, 20000.00, 'ORD1749526301546', '', '', '2025-06-10 03:31:41', '2025-06-10 03:31:41'),
(32, 18, 1, '2025-06-14 05:44:21', 'diproses', 'COD', 'antar', 'Jl.Mohd Kahfi I', 10000.00, 22000.00, 'ORD1749897861344', 'ya', '', '2025-06-14 10:44:21', '2025-06-14 10:44:21'),
(33, 18, 1, '2025-06-14 05:49:55', 'diproses', 'COD', 'antar', 'Jl.Mohd Kahfi I', 10000.00, 40000.00, 'ORD1749898195789', '', '', '2025-06-14 10:49:55', '2025-06-14 10:49:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `unit_usaha_id` int(11) NOT NULL,
  `penjual_id` int(11) DEFAULT NULL,
  `nama` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` decimal(15,2) NOT NULL,
  `stok` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `asal_desa` varchar(255) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `berat` decimal(10,2) DEFAULT NULL,
  `status_produk` enum('aktif','tidak_aktif','arsip') DEFAULT 'aktif',
  `tanggal_publikasi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `jumlah_terjual` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `unit_usaha_id`, `penjual_id`, `nama`, `deskripsi`, `harga`, `stok`, `gambar`, `asal_desa`, `kategori_id`, `berat`, `status_produk`, `tanggal_publikasi`, `jumlah_terjual`, `created_at`, `updated_at`) VALUES
(1, 23, 3, 'Keripik Pisang Lampung', 'Keripik pisang Lampung renyah dengan aneka rasa favorit! Pilih dari cokelat, keju, stroberi, dan banyak lagi. Camilan manis dan gurih yang pas untuk segala suasana. Kemasan 150 gram', 10000.00, 10, 'kripikpisang.jpg', 'Sinar Petir', 76, 0.15, 'aktif', '2025-06-10 00:41:06', 0, '2025-04-24 03:39:55', '2025-06-10 00:41:06'),
(2, 23, 3, 'Buku Tulis Tiara Campus - 50 Lembar HVS 80 Gsm', 'Buku tulis dengan desain sampul bertema \"Tiara Campus\" yang stylish dan menarik. Ideal untuk mencatat materi pelajaran, tugas kuliah, atau catatan penting lainnya. Menggunakan kertas HVS berkualitas 80 Gsm yang nyaman untuk ditulis dan tidak mudah tembus tinta. Isi 50 lembar dengan format garis.', 45000.00, 450, '68255a1dee0945.76738770.jpg', 'Sinar Petir', 62, 0.15, 'aktif', '2025-06-10 00:41:02', 0, '2025-04-24 03:41:30', '2025-06-10 00:41:02'),
(3, 22, 3, 'Lada Hitam Bubuk Lampung', 'Lada hitam bubuk murni dari perkebunan lada di Lampung, terkenal dengan aroma dan rasa pedasnya yang kuat. Cocok sebagai bumbu masakan. Kemasan 1 kg.', 12000.00, 2, '6809ba4c697e1.jpg', 'Sinar Petir', 75, 1.00, 'aktif', '2025-06-10 01:06:11', 0, '2025-04-24 04:13:00', '2025-06-10 01:06:11'),
(4, 21, 3, 'Topi Rajut', 'Topi rajut tangan dengan motif-motif khas Lampung yang unik dan menarik. Terbuat dari benang berkualitas, cocok untuk gaya kasual maupun acara tertentu. Ukuran dewasa.', 12000.00, 14, '6809c6a0bfb00.jpg', 'Sinar Petir', 2, 0.15, 'aktif', '2025-06-14 10:44:21', 0, '2025-04-24 05:05:36', '2025-06-14 10:44:21'),
(6, 23, 3, 'Keripik Ubi Ungu Manis', 'Keripik ubi ungu yang diolah tanpa tambahan pemanis buatan, menghasilkan rasa manis alami ubi yang lezat dan tekstur renyah. Camilan sehat dan kaya serat, kemasan 100 gram.', 8000.00, 0, '6809dd3adc0ad.jpg', 'Sinar Petir', 76, 0.12, 'aktif', '2025-06-09 04:32:20', 0, '2025-04-24 06:42:02', '2025-06-09 04:32:20'),
(7, 23, 3, 'Emping Melinjo Mentah ', 'Emping melinjo mentah kualitas super, dibuat dari biji melinjo pilihan. Cocok untuk digoreng sendiri sebagai camilan renyah dan gurih. Tanpa bahan pengawet, berbagai rasa, berat  250 gram.', 5000.00, 2, '680de61ea76858.12680374.jpg', 'Sinar Petir', 76, 0.10, 'aktif', '2025-06-10 03:31:41', 0, '2025-04-24 10:32:35', '2025-06-10 03:31:41'),
(8, 19, 3, 'Pulpen Faster C600 Hitam, CX5 Biru] - Isi 12 per Pack', 'Paket pulpen Faster berisi 12 buah pulpen tipe C600 dengan warna tinta  hitam] berkualitas. Menulis lancar dan nyaman digenggam. Ideal untuk keperluan sekolah, kuliah, kantor, atau sehari-hari. ', 30000.00, 11, '680a156750458.jpg', 'Sinar Petir', 62, 0.20, 'aktif', '2025-06-14 10:49:55', 0, '2025-04-24 10:41:43', '2025-06-14 10:49:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_diskon`
--

CREATE TABLE `produk_diskon` (
  `produk_id` int(11) NOT NULL,
  `diskon_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_media`
--

CREATE TABLE `produk_media` (
  `id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `jenis_media` enum('gambar','video') NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk_media`
--

INSERT INTO `produk_media` (`id`, `produk_id`, `jenis_media`, `nama_file`, `created_at`) VALUES
(1, 2, 'video', 'Langkah Tak Sia-Sia.mp4', '2025-05-15 01:50:46'),
(2, 2, 'gambar', 'pulpenmerah.jpg', '2025-05-15 01:52:28'),
(3, 2, 'gambar', 'WhatsApp Image 2025-05-11 at 08.43.25.jpeg', '2025-05-16 05:38:02'),
(4, 2, 'gambar', 'tpirajutcoklat.avif', '2025-05-16 05:41:18'),
(5, 2, 'gambar', 'buku.jpg', '2025-05-16 05:41:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_variasi`
--

CREATE TABLE `produk_variasi` (
  `id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `warna_id` int(11) DEFAULT NULL,
  `ukuran_id` int(11) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `harga` decimal(15,2) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rasa_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk_variasi`
--

INSERT INTO `produk_variasi` (`id`, `produk_id`, `warna_id`, `ukuran_id`, `stok`, `harga`, `gambar`, `created_at`, `updated_at`, `rasa_id`) VALUES
(11, 4, 5, 3, 3, 12000.00, '6818760ad1ea1_tpirajutcoklat.avif', '2025-05-05 08:25:46', '2025-05-15 08:30:00', NULL),
(12, 4, 5, 1, 3, 15000.00, '68188d27da3d4_tpirajutcoklat.avif', '2025-05-05 08:26:24', '2025-06-14 10:44:21', NULL),
(13, 7, NULL, NULL, 2, 7000.00, '6818b54e041d5_empingpedas.jpg', '2025-05-05 12:55:42', '2025-06-10 03:31:41', 2),
(14, 4, 4, 3, 2, 16000.00, '6818ba74c2732_rajutbiru.jpg', '2025-05-05 13:17:40', '2025-06-10 00:42:53', NULL),
(16, 4, 4, 2, 6, 13000.00, '', '2025-05-05 13:57:09', '2025-05-05 13:57:09', NULL),
(17, 8, 3, NULL, 0, 2500.00, '68244b091481b_pulpenmerah.jpg', '2025-05-14 07:49:29', '2025-06-09 04:34:06', NULL),
(19, 8, 4, NULL, 11, 2500.00, '68247cac1ea70_pulpenbiru.jpg', '2025-05-14 11:21:16', '2025-06-14 10:49:55', NULL);

--
-- Trigger `produk_variasi`
--
DELIMITER $$
CREATE TRIGGER `after_produk_variasi_delete` AFTER DELETE ON `produk_variasi` FOR EACH ROW BEGIN
    UPDATE produk
    SET stok = (SELECT SUM(stok) FROM produk_variasi WHERE produk_id = OLD.produk_id)
    WHERE id = OLD.produk_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_produk_variasi_insert` AFTER INSERT ON `produk_variasi` FOR EACH ROW BEGIN
    UPDATE produk
    SET stok = (SELECT SUM(stok) FROM produk_variasi WHERE produk_id = NEW.produk_id)
    WHERE id = NEW.produk_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_produk_variasi_update` AFTER UPDATE ON `produk_variasi` FOR EACH ROW BEGIN
    UPDATE produk
    SET stok = (SELECT SUM(stok) FROM produk_variasi WHERE produk_id = NEW.produk_id)
    WHERE id = NEW.produk_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rasa`
--

CREATE TABLE `rasa` (
  `id` int(11) NOT NULL,
  `nama_rasa` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rasa`
--

INSERT INTO `rasa` (`id`, `nama_rasa`, `created_at`, `updated_at`) VALUES
(2, 'Pedas', '2025-04-30 21:51:05', '2025-04-30 21:51:05'),
(3, 'Original', '2025-05-13 12:06:34', '2025-05-14 13:25:12'),
(4, 'Asin', '2025-05-14 13:29:26', '2025-05-14 13:29:53'),
(5, 'Sedang', '2025-05-17 02:38:00', '2025-05-17 02:38:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tarif_kurir_wilayah`
--

CREATE TABLE `tarif_kurir_wilayah` (
  `id` int(11) NOT NULL,
  `kurir_id` int(11) NOT NULL,
  `nama_wilayah` varchar(255) NOT NULL,
  `tarif_per_kg` decimal(10,2) NOT NULL,
  `tarif_flat` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tarif_wilayah`
--

CREATE TABLE `tarif_wilayah` (
  `id` int(11) NOT NULL,
  `nama_wilayah` varchar(255) NOT NULL,
  `tarif_per_kg` decimal(10,2) NOT NULL,
  `tarif_flat` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_keuangan`
--

CREATE TABLE `transaksi_keuangan` (
  `id` int(11) NOT NULL,
  `unit_usaha_id` int(11) DEFAULT NULL,
  `jenis_transaksi` enum('pemasukan','pengeluaran') NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `deskripsi` text NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `bukti_transaksi` varchar(255) DEFAULT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ukuran`
--

CREATE TABLE `ukuran` (
  `id` int(11) NOT NULL,
  `nama_ukuran` varchar(50) NOT NULL,
  `singkatan` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ukuran`
--

INSERT INTO `ukuran` (`id`, `nama_ukuran`, `singkatan`, `created_at`, `updated_at`) VALUES
(1, 'M', 'M', '2025-04-30 13:21:56', '2025-04-30 13:21:56'),
(2, 'S', 'S', '2025-05-03 02:06:27', '2025-05-03 02:06:27'),
(3, 'L', 'L', '2025-05-03 02:06:36', '2025-05-14 13:25:06'),
(4, 'XL', 'XL', '2025-05-14 11:53:49', '2025-05-14 11:53:49'),
(5, 'Sedang', 'S', '2025-05-17 02:38:56', '2025-05-17 02:38:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ulasan_media`
--

CREATE TABLE `ulasan_media` (
  `id` int(11) NOT NULL,
  `ulasan_id` int(11) NOT NULL,
  `jenis_media` enum('foto','video') NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ulasan_produk`
--

CREATE TABLE `ulasan_produk` (
  `id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `komentar` text DEFAULT NULL,
  `tanggal_ulasan` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `unit_usaha`
--

CREATE TABLE `unit_usaha` (
  `id` int(11) NOT NULL,
  `bumdes_id` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `unit_usaha`
--

INSERT INTO `unit_usaha` (`id`, `bumdes_id`, `nama`, `deskripsi`, `kontak`, `slug`, `created_at`, `updated_at`) VALUES
(11, 1, 'BUMSINPET', 'Menyediakan berbagai jenis pakaian untuk pria, wanita, dan anak-anak, termasuk pakaian muslim, pakaian olahraga, pakaian dalam, serta aksesoris fashion seperti tas, sepatu, dan perhiasan.', NULL, 'fashion', '2025-04-23 22:06:11', '2025-06-09 06:38:40'),
(12, 1, 'Elektronik', 'Meliputi berbagai perangkat elektronik konsumen seperti smartphone, laptop, tablet, televisi, audio, kamera, dan aksesoris elektronik lainnya.', NULL, 'elektronik', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(13, 1, 'Peralatan Rumah Tangga', 'Terdiri dari berbagai produk untuk kebutuhan rumah, mulai dari perabot besar (sofa, lemari, tempat tidur), perabot kecil (alat makan, dekorasi), hingga peralatan elektronik rumah tangga (kulkas, mesin cuci).', NULL, 'peralatan-rumah-tangga', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(14, 1, 'Kebutuhan Sehari-hari', 'Mencakup bahan makanan, minuman, produk perawatan pribadi, perlengkapan kebersihan rumah tangga, serta makanan dan perlengkapan untuk hewan peliharaan.', NULL, 'kebutuhan-sehari-hari', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(15, 1, 'Kesehatan & Kecantikan', 'Meliputi produk perawatan kulit, makeup, perawatan rambut, produk kesehatan, suplemen, hingga alat-alat kesehatan.', NULL, 'kesehatan-kecantikan', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(16, 1, 'Bayi & Anak-anak', 'Menyediakan berbagai kebutuhan bayi dan anak-anak, seperti pakaian bayi, perlengkapan menyusui, mainan, stroller, dan produk perawatan bayi.', NULL, 'bayi-anak-anak', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(17, 1, 'Olahraga & Outdoor', 'Menawarkan berbagai perlengkapan dan pakaian untuk berbagai jenis olahraga dan aktivitas luar ruangan, seperti alat fitness, perlengkapan camping, dan pakaian olahraga.', NULL, 'olahraga-outdoor', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(18, 1, 'Otomotif', 'Mencakup berbagai aksesoris mobil dan motor, suku cadang, hingga produk perawatan kendaraan.', NULL, 'otomotif', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(19, 1, 'Buku & Alat Tulis', 'Menyediakan berbagai jenis buku (fiksi, non-fiksi, pelajaran), alat tulis, dan perlengkapan kantor.', NULL, 'buku-alat-tulis', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(20, 1, 'Hobi & Koleksi', 'Meliputi berbagai barang untuk hobi seperti action figure, model kit, alat musik, perlengkapan seni, dan barang koleksi lainnya.', NULL, 'hobi-koleksi', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(21, 1, 'Kerajinan Tangan & Souvenir Lokal', 'Menawarkan berbagai kerajinan tangan unik khas desa, seperti anyaman, ukiran kayu, batik, dan souvenir menarik lainnya.', NULL, 'kerajinan-tangan-souvenir', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(22, 1, 'Produk Pertanian & Perkebunan Segar', 'Menyediakan hasil panen segar dari petani lokal, termasuk sayuran organik, buah-buahan musiman, rempah-rempah, dan produk pertanian lainnya.', NULL, 'produk-pertanian-segar', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(23, 1, 'Makanan & Minuman Olahan Desa', 'Menjual berbagai makanan dan minuman olahan khas desa, seperti keripik, abon, selai, sirup, dan minuman tradisional.', NULL, 'makanan-minuman-olahan', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(24, 1, 'Perlengkapan Usaha Mikro', 'Menyediakan berbagai peralatan dan perlengkapan yang dibutuhkan oleh usaha mikro di desa, seperti alat produksi skala kecil, kemasan, dan bahan baku.', NULL, 'perlengkapan-usaha-mikro', '2025-04-23 22:06:11', '2025-04-23 22:06:11'),
(25, 1, 'Jasa & Penyewaan Online', 'Menawarkan berbagai layanan dan penyewaan berbasis online, seperti penyewaan alat pertanian (jika belum ada unit usaha fisik), penyewaan tenda dan perlengkapan acara, atau jasa konsultasi online.', NULL, 'jasa-penyewaan-online', '2025-04-23 22:06:11', '2025-04-23 22:06:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `warna`
--

CREATE TABLE `warna` (
  `id` int(11) NOT NULL,
  `nama_warna` varchar(50) NOT NULL,
  `kode_hex` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `warna`
--

INSERT INTO `warna` (`id`, `nama_warna`, `kode_hex`, `created_at`, `updated_at`) VALUES
(3, 'Merah', NULL, '2025-04-30 09:00:19', '2025-04-30 09:00:19'),
(4, 'biru', NULL, '2025-04-30 09:01:25', '2025-05-14 14:17:24'),
(5, 'coklat', NULL, '2025-05-05 13:16:36', '2025-05-05 13:16:36'),
(7, 'Abu-abu', NULL, '2025-05-14 14:17:42', '2025-05-15 00:47:46'),
(8, 'Hitam', NULL, '2025-05-14 14:17:59', '2025-05-14 14:17:59'),
(9, 'Putih', NULL, '2025-05-14 14:18:25', '2025-05-14 14:18:25'),
(10, 'Hijau', NULL, '2025-05-14 14:18:55', '2025-05-14 14:18:55'),
(11, 'Cream', NULL, '2025-05-14 14:19:08', '2025-05-14 14:19:08'),
(12, 'Orange', NULL, '2025-05-14 14:19:37', '2025-05-14 14:19:37'),
(13, 'Kuning', NULL, '2025-05-14 14:20:14', '2025-05-14 14:20:14'),
(14, 'Ungu', NULL, '2025-05-14 14:20:36', '2025-05-14 14:20:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `tanggal_ditambahkan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `wishlist`
--

INSERT INTO `wishlist` (`id`, `pelanggan_id`, `produk_id`, `tanggal_ditambahkan`) VALUES
(31, 4, 8, '2025-06-09 03:01:39'),
(32, 18, 1, '2025-06-10 03:31:03'),
(33, 18, 2, '2025-06-10 03:31:05');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_anggota` (`nomor_anggota`);

--
-- Indeks untuk tabel `artikel`
--
ALTER TABLE `artikel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `penulis_id` (`penulis_id`);

--
-- Indeks untuk tabel `bukti_pengembalian_gambar`
--
ALTER TABLE `bukti_pengembalian_gambar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengembalian_id` (`pengembalian_id`);

--
-- Indeks untuk tabel `bumdes`
--
ALTER TABLE `bumdes`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `catatan_transaksi_lain`
--
ALTER TABLE `catatan_transaksi_lain`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `variasi_id` (`variasi_id`);

--
-- Indeks untuk tabel `diskon`
--
ALTER TABLE `diskon`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_diskon` (`kode_diskon`);

--
-- Indeks untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `keranjang_customer`
--
ALTER TABLE `keranjang_customer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`customer_id`,`produk_id`,`variasi_id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `variasi_id` (`variasi_id`);

--
-- Indeks untuk tabel `kurir`
--
ALTER TABLE `kurir`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_metode` (`kode_metode`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`pengguna_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nomor_anggota` (`nomor_anggota`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`);

--
-- Indeks untuk tabel `penarikan_dana`
--
ALTER TABLE `penarikan_dana`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

--
-- Indeks untuk tabel `pengaturan_kurir_penjual`
--
ALTER TABLE `pengaturan_kurir_penjual`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`,`kurir_id`),
  ADD KEY `kurir_id` (`kurir_id`);

--
-- Indeks untuk tabel `pengaturan_pembayaran_penjual`
--
ALTER TABLE `pengaturan_pembayaran_penjual`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`,`metode_pembayaran_id`),
  ADD KEY `metode_pembayaran_id` (`metode_pembayaran_id`);

--
-- Indeks untuk tabel `pengembalian_barang`
--
ALTER TABLE `pengembalian_barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `pelanggan_id` (`pelanggan_id`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `unit_usaha_id` (`unit_usaha_id`);

--
-- Indeks untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `kurir_id` (`kurir_id`);

--
-- Indeks untuk tabel `penjual`
--
ALTER TABLE `penjual`
  ADD PRIMARY KEY (`pengguna_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `pesan`
--
ALTER TABLE `pesan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengirim_pengguna_id` (`pengirim_pengguna_id`),
  ADD KEY `penerima_pengguna_id` (`penerima_pengguna_id`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_unik` (`kode_unik`),
  ADD KEY `pelanggan_id` (`pelanggan_id`),
  ADD KEY `kurir_id` (`kurir_id`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_usaha_id` (`unit_usaha_id`),
  ADD KEY `penjual_id` (`penjual_id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indeks untuk tabel `produk_diskon`
--
ALTER TABLE `produk_diskon`
  ADD PRIMARY KEY (`produk_id`,`diskon_id`),
  ADD KEY `diskon_id` (`diskon_id`);

--
-- Indeks untuk tabel `produk_media`
--
ALTER TABLE `produk_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indeks untuk tabel `produk_variasi`
--
ALTER TABLE `produk_variasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_variasi_lengkap` (`produk_id`,`warna_id`,`ukuran_id`,`rasa_id`),
  ADD KEY `warna_id` (`warna_id`),
  ADD KEY `ukuran_id` (`ukuran_id`),
  ADD KEY `rasa_id` (`rasa_id`);

--
-- Indeks untuk tabel `rasa`
--
ALTER TABLE `rasa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_rasa` (`nama_rasa`);

--
-- Indeks untuk tabel `tarif_kurir_wilayah`
--
ALTER TABLE `tarif_kurir_wilayah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tarif` (`kurir_id`,`nama_wilayah`);

--
-- Indeks untuk tabel `tarif_wilayah`
--
ALTER TABLE `tarif_wilayah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_wilayah` (`nama_wilayah`);

--
-- Indeks untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_usaha_id` (`unit_usaha_id`);

--
-- Indeks untuk tabel `ukuran`
--
ALTER TABLE `ukuran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_ukuran` (`nama_ukuran`);

--
-- Indeks untuk tabel `ulasan_media`
--
ALTER TABLE `ulasan_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ulasan_id` (`ulasan_id`);

--
-- Indeks untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `pelanggan_id` (`pelanggan_id`);

--
-- Indeks untuk tabel `unit_usaha`
--
ALTER TABLE `unit_usaha`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `bumdes_id` (`bumdes_id`);

--
-- Indeks untuk tabel `warna`
--
ALTER TABLE `warna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_warna` (`nama_warna`);

--
-- Indeks untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`pelanggan_id`,`produk_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `artikel`
--
ALTER TABLE `artikel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `bukti_pengembalian_gambar`
--
ALTER TABLE `bukti_pengembalian_gambar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `bumdes`
--
ALTER TABLE `bumdes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `catatan_transaksi_lain`
--
ALTER TABLE `catatan_transaksi_lain`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `diskon`
--
ALTER TABLE `diskon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT untuk tabel `keranjang_customer`
--
ALTER TABLE `keranjang_customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `kurir`
--
ALTER TABLE `kurir`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `penarikan_dana`
--
ALTER TABLE `penarikan_dana`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_kurir_penjual`
--
ALTER TABLE `pengaturan_kurir_penjual`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_pembayaran_penjual`
--
ALTER TABLE `pengaturan_pembayaran_penjual`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `pengembalian_barang`
--
ALTER TABLE `pengembalian_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `pesan`
--
ALTER TABLE `pesan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `produk_media`
--
ALTER TABLE `produk_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `produk_variasi`
--
ALTER TABLE `produk_variasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `rasa`
--
ALTER TABLE `rasa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tarif_kurir_wilayah`
--
ALTER TABLE `tarif_kurir_wilayah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tarif_wilayah`
--
ALTER TABLE `tarif_wilayah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ukuran`
--
ALTER TABLE `ukuran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `ulasan_media`
--
ALTER TABLE `ulasan_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `unit_usaha`
--
ALTER TABLE `unit_usaha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `warna`
--
ALTER TABLE `warna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `artikel`
--
ALTER TABLE `artikel`
  ADD CONSTRAINT `artikel_ibfk_1` FOREIGN KEY (`penulis_id`) REFERENCES `pengguna` (`id`);

--
-- Ketidakleluasaan untuk tabel `bukti_pengembalian_gambar`
--
ALTER TABLE `bukti_pengembalian_gambar`
  ADD CONSTRAINT `bukti_pengembalian_gambar_ibfk_1` FOREIGN KEY (`pengembalian_id`) REFERENCES `pengembalian_barang` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `catatan_transaksi_lain`
--
ALTER TABLE `catatan_transaksi_lain`
  ADD CONSTRAINT `catatan_transaksi_lain_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `penjual` (`pengguna_id`);

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `detail_pesanan_ibfk_3` FOREIGN KEY (`variasi_id`) REFERENCES `produk_variasi` (`id`);

--
-- Ketidakleluasaan untuk tabel `keranjang_customer`
--
ALTER TABLE `keranjang_customer`
  ADD CONSTRAINT `keranjang_customer_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `pelanggan` (`pengguna_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keranjang_customer_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keranjang_customer_ibfk_3` FOREIGN KEY (`variasi_id`) REFERENCES `produk_variasi` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD CONSTRAINT `pelanggan_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`);

--
-- Ketidakleluasaan untuk tabel `penarikan_dana`
--
ALTER TABLE `penarikan_dana`
  ADD CONSTRAINT `penarikan_dana_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `penjual` (`pengguna_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengaturan_kurir_penjual`
--
ALTER TABLE `pengaturan_kurir_penjual`
  ADD CONSTRAINT `pengaturan_kurir_penjual_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengaturan_kurir_penjual_ibfk_2` FOREIGN KEY (`kurir_id`) REFERENCES `kurir` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengaturan_pembayaran_penjual`
--
ALTER TABLE `pengaturan_pembayaran_penjual`
  ADD CONSTRAINT `pengaturan_pembayaran_penjual_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengaturan_pembayaran_penjual_ibfk_2` FOREIGN KEY (`metode_pembayaran_id`) REFERENCES `metode_pembayaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengembalian_barang`
--
ALTER TABLE `pengembalian_barang`
  ADD CONSTRAINT `pengembalian_barang_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`),
  ADD CONSTRAINT `pengembalian_barang_ibfk_2` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pengguna_id`);

--
-- Ketidakleluasaan untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD CONSTRAINT `pengguna_ibfk_1` FOREIGN KEY (`unit_usaha_id`) REFERENCES `unit_usaha` (`id`);

--
-- Ketidakleluasaan untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD CONSTRAINT `pengiriman_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`),
  ADD CONSTRAINT `pengiriman_ibfk_2` FOREIGN KEY (`kurir_id`) REFERENCES `kurir` (`id`);

--
-- Ketidakleluasaan untuk tabel `penjual`
--
ALTER TABLE `penjual`
  ADD CONSTRAINT `penjual_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`);

--
-- Ketidakleluasaan untuk tabel `pesan`
--
ALTER TABLE `pesan`
  ADD CONSTRAINT `pesan_ibfk_1` FOREIGN KEY (`pengirim_pengguna_id`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `pesan_ibfk_2` FOREIGN KEY (`penerima_pengguna_id`) REFERENCES `pengguna` (`id`);

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pengguna_id`),
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`kurir_id`) REFERENCES `kurir` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`unit_usaha_id`) REFERENCES `unit_usaha` (`id`),
  ADD CONSTRAINT `produk_ibfk_2` FOREIGN KEY (`penjual_id`) REFERENCES `penjual` (`pengguna_id`),
  ADD CONSTRAINT `produk_ibfk_3` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk_diskon`
--
ALTER TABLE `produk_diskon`
  ADD CONSTRAINT `produk_diskon_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `produk_diskon_ibfk_2` FOREIGN KEY (`diskon_id`) REFERENCES `diskon` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk_media`
--
ALTER TABLE `produk_media`
  ADD CONSTRAINT `produk_media_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produk_variasi`
--
ALTER TABLE `produk_variasi`
  ADD CONSTRAINT `produk_variasi_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `produk_variasi_ibfk_2` FOREIGN KEY (`warna_id`) REFERENCES `warna` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `produk_variasi_ibfk_3` FOREIGN KEY (`ukuran_id`) REFERENCES `ukuran` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `produk_variasi_ibfk_4` FOREIGN KEY (`rasa_id`) REFERENCES `rasa` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tarif_kurir_wilayah`
--
ALTER TABLE `tarif_kurir_wilayah`
  ADD CONSTRAINT `tarif_kurir_wilayah_ibfk_1` FOREIGN KEY (`kurir_id`) REFERENCES `kurir` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD CONSTRAINT `transaksi_keuangan_ibfk_1` FOREIGN KEY (`unit_usaha_id`) REFERENCES `unit_usaha` (`id`);

--
-- Ketidakleluasaan untuk tabel `ulasan_media`
--
ALTER TABLE `ulasan_media`
  ADD CONSTRAINT `ulasan_media_ibfk_1` FOREIGN KEY (`ulasan_id`) REFERENCES `ulasan_produk` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  ADD CONSTRAINT `ulasan_produk_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `ulasan_produk_ibfk_2` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pengguna_id`);

--
-- Ketidakleluasaan untuk tabel `unit_usaha`
--
ALTER TABLE `unit_usaha`
  ADD CONSTRAINT `unit_usaha_ibfk_1` FOREIGN KEY (`bumdes_id`) REFERENCES `bumdes` (`id`);

--
-- Ketidakleluasaan untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pengguna_id`),
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
