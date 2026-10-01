<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php'); // Sesuaikan path ini ke halaman login Anda
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID pengguna yang sedang login (ini adalah ID pengguna penjual)

// Ambil data pengguna (untuk kebutuhan tampilan di navbar, dll)
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);
mysqli_stmt_close($stmt_pengguna);

if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan. Silakan login kembali.'); window.location.href='../../../logout.php';</script>";
    exit;
}

// Ambil data penjual (untuk tampilan profil toko, dll, dan untuk mendapatkan penjual_id)
// Kita tetap butuh data penjual untuk sidebar/profil toko, meski ID pesan merujuk ke pengguna_id
$query_penjual = "SELECT pengguna_id, nama_toko, nama_pemilik, email, username, foto, nomor_telepon, alamat, kebijakan_pengiriman FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);

if (!$penjual) {
    echo "<script>alert('Anda belum memiliki profil penjual. Silakan lengkapi profil Anda.'); window.location.href='../../../logout.php';</script>";
    exit;
}

$pesan_sukses = '';
$pesan_error = '';


if (isset($_GET['action']) && $_GET['action'] == 'mark_read' && isset($_GET['id'])) {
    $pesan_id = $_GET['id'];

    // Pastikan pesan itu milik penjual yang sedang login
    $query_update = "UPDATE pesan SET status_baca = 'sudah_dibaca' WHERE id = ? AND penerima_pengguna_id = ?";
    $stmt_update = mysqli_prepare($conn, $query_update);
    mysqli_stmt_bind_param($stmt_update, 'ii', $pesan_id, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
            $pesan_sukses = "Pesan berhasil ditandai sudah dibaca.";
        } else {
            $pesan_error = "Pesan tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.";
        }
    } else {
        $pesan_error = "Gagal menandai pesan sudah dibaca: " . mysqli_error($conn);
        error_log("Error marking message read: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_update);
    header('Location: pesan_masuk.php?status=success_read');
    exit;
}

// Proses Hapus Pesan (soft delete)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $pesan_id = $_GET['id'];

    // Pastikan pesan itu milik penjual yang sedang login
    $query_delete = "UPDATE pesan SET status_pesan = 'dihapus' WHERE id = ? AND penerima_pengguna_id = ?";
    $stmt_delete = mysqli_prepare($conn, $query_delete);
    mysqli_stmt_bind_param($stmt_delete, 'ii', $pesan_id, $user_id);

    if (mysqli_stmt_execute($stmt_delete)) {
        if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
            $pesan_sukses = "Pesan berhasil dihapus.";
        } else {
            $pesan_error = "Pesan tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.";
        }
    } else {
        $pesan_error = "Gagal menghapus pesan: " . mysqli_error($conn);
        error_log("Error deleting message: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_delete);
    header('Location: pesan_masuk.php?status=success_delete');
    exit;
}

// Proses Balas Pesan
if (isset($_POST['balas_pesan'])) {
    $penerima_pengguna_id = $_POST['penerima_pengguna_id']; // ID pengguna pembeli
    $isi_balasan = trim($_POST['isi_balasan']);

    if (empty($isi_balasan)) {
        $pesan_error = "Isi balasan tidak boleh kosong.";
    } else {
        // Balas pesan dari penjual (user_id) ke pembeli (penerima_pengguna_id)
        $insert_query = "INSERT INTO pesan (pengirim_pengguna_id, penerima_pengguna_id, isi_pesan, waktu_kirim, status_baca)
                         VALUES (?, ?, ?, NOW(), 'belum_dibaca')"; // Pesan baru dianggap belum dibaca oleh penerima
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt_insert, 'iis', $user_id, $penerima_pengguna_id, $isi_balasan);

        if (mysqli_stmt_execute($stmt_insert)) {
            // Setelah membalas, tandai pesan asli sebagai sudah dibaca (opsional)
            $pesan_original_id = $_POST['pesan_original_id'] ?? 0;
            if ($pesan_original_id > 0) {
                $query_mark_read_original = "UPDATE pesan SET status_baca = 'sudah_dibaca' WHERE id = ? AND penerima_pengguna_id = ?";
                $stmt_mark_read_original = mysqli_prepare($conn, $query_mark_read_original);
                mysqli_stmt_bind_param($stmt_mark_read_original, 'ii', $pesan_original_id, $user_id);
                mysqli_stmt_execute($stmt_mark_read_original);
                mysqli_stmt_close($stmt_mark_read_original);
            }
            $pesan_sukses = "Balasan berhasil dikirim.";
            header('Location: pesan_masuk.php?status=success_reply');
            exit;
        } else {
            $pesan_error = "Gagal mengirim balasan: " . mysqli_error($conn);
            error_log("Error sending reply: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt_insert);
    }
}

// Tampilkan pesan status dari redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_read') {
        $pesan_sukses = "Pesan berhasil ditandai sudah dibaca.";
    } elseif ($_GET['status'] == 'success_delete') {
        $pesan_sukses = "Pesan berhasil dihapus.";
    } elseif ($_GET['status'] == 'success_reply') {
        $pesan_sukses = "Balasan berhasil dikirim.";
    }
}

// =====================================================================
// AMBIL DAFTAR PESAN MASUK
// =====================================================================

$daftar_pesan = [];
// Mengambil pesan di mana penjual adalah penerima dan status_pesan bukan 'dihapus'
$query_pesan = "SELECT p.id, p.pengirim_pengguna_id, p.isi_pesan, p.waktu_kirim, p.status_baca, u.nama AS nama_pengirim
                FROM pesan p
                JOIN pengguna u ON p.pengirim_pengguna_id = u.id
                WHERE p.penerima_pengguna_id = ? AND p.status_pesan != 'dihapus'
                ORDER BY p.waktu_kirim DESC";
$stmt_pesan = mysqli_prepare($conn, $query_pesan);
mysqli_stmt_bind_param($stmt_pesan, 'i', $user_id);
mysqli_stmt_execute($stmt_pesan);
$result_pesan = mysqli_stmt_get_result($stmt_pesan);
while ($row = mysqli_fetch_assoc($result_pesan)) {
    $daftar_pesan[] = $row;
}
mysqli_stmt_close($stmt_pesan);

// =====================================================================
// DATA STATISTIK UNTUK SIDEBAR/FOOTER (OPSIONAL, BISA DIHAPUS JIKA TIDAK PERLU)
// =====================================================================

$query_menunggu = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'menunggu_pembayaran'";
$result_menunggu = mysqli_query($conn, $query_menunggu);
$row_menunggu = mysqli_fetch_assoc($result_menunggu);
$total_menunggu = $row_menunggu['total'] ?? 0;

$query_diproses = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'diproses'";
$result_diproses = mysqli_query($conn, $query_diproses);
$row_diproses = mysqli_fetch_assoc($result_diproses);
$total_diproses = $row_diproses['total'] ?? 0;

$query_dikirim = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'dikirim'";
$result_dikirim = mysqli_query($conn, $query_dikirim);
$row_dikirim = mysqli_fetch_assoc($result_dikirim);
$total_dikirim = $row_dikirim['total'] ?? 0;

$query_produk = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
mysqli_stmt_bind_param($stmt_produk, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'] ?? 0;
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
mysqli_stmt_bind_param($stmt_stok_rendah, 'i', $penjual['id']);
mysqli_stmt_execute($stmt_stok_rendah);
$result_stok_rendah = mysqli_stmt_get_result($stmt_stok_rendah);
$row_stok_rendah = mysqli_fetch_assoc($result_stok_rendah);
$total_stok_rendah = $row_stok_rendah['total'] ?? 0;
mysqli_stmt_close($stmt_stok_rendah);

$query_pendapatan = "SELECT SUM(p.total_harga) AS total
                     FROM pesanan p
                     WHERE p.status_pesanan = 'selesai'";
$result_pendapatan = mysqli_query($conn, $query_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total'] ?: 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesan Masuk - Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* CSS umum (dari file-file Anda sebelumnya) */
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f2f6fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #1f2937;
            min-height: 100vh;
            padding: 20px 0;
            color: white;
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 30px;
        }
        .sidebar a {
            color: #dcdcdc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #495057;
            color: #ffffff;
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #cfcfcf;
        }
        .sidebar .submenu:hover {
            color: #ffffff;
        }
        .sidebar .menu-text {
            margin-left: 10px;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .sidebar.collapsed .submenu {
            display: none;
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            margin-left: 20px;
            font-size: 20px;
        }

        .card-dashboard {
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
        }
        .profile-section {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .profile-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }
        .profile-menu {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .profile-menu h5 {
            margin-top: 0;
        }
        .profile-menu p {
            margin: 0;
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .custom-card {
        height: 80px;
        width: 59%;
        padding: 10px 5px;
        margin: 3px 5px;
        }
        #salesChart {
            width: 100% !important;
            max-width: 600px !important;
            height: 300px !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Foto Profil Penjual" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Profil Penjual" width="80" height="80" style="object-fit: cover;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($penjual['nama_pemilik']); ?></h5>
                <p class="mb-0 small">Username Toko: <?= htmlspecialchars($penjual['username']); ?></p>
                <p class="mb-0 small">Email Toko: <?= htmlspecialchars($penjual['email']); ?></p>
                <p class="mb-0 small">Nama Toko: <?= htmlspecialchars($penjual['nama_toko']); ?></p>
                <p class="mb-0 small">Nomor Telepon: <?= htmlspecialchars($penjual['nomor_telepon']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                <a class="btn btn-link text-danger p-0 d-block" href="../../../logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../toko/profil/profil_toko.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($pengguna_data['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($penjual['nama_toko']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" class="form-control" value="<?= htmlspecialchars($penjual['nama_pemilik']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Toko</label>
                        <input type="email" name="email_penjual" class="form-control" value="<?= htmlspecialchars($penjual['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username Toko</label>
                        <input type="text" name="username_penjual" class="form-control" value="<?= htmlspecialchars($penjual['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Toko</label>
                        <textarea name="alamat" class="form-control"><?= htmlspecialchars($penjual['alamat']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="nomor_telepon" class="form-control" value="<?= htmlspecialchars($penjual['nomor_telepon']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= htmlspecialchars($penjual['tanggal_lahir']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="pria" <?= ($penjual['jenis_kelamin'] == 'pria') ? 'selected' : '' ?>>Pria</option>
                            <option value="wanita" <?= ($penjual['jenis_kelamin'] == 'wanita') ? 'selected' : '' ?>>Wanita</option>
                            <option value="lainnya" <?= ($penjual['jenis_kelamin'] == 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($penjual['foto']) : ?>
                            <img src="../../../img/foto/<?= $penjual['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <h4>&nbsp;</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../../dashboard_penjual.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#produkMenu" role="button" aria-expanded="false" aria-controls="produkMenu">
                    <i class="fas fa-box"></i>
                    <span class="ml-2">Produk</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="produkMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/daftar/daftar_produk.php">Daftar Produk</a>
                        </li>
                        <li class="nav-item">
                            </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/stok/stok.php">Stok / Inventaris</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/varian/varian_produk.php">Varian Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../produk/kategori/kategori_produk.php">Kategori Produk</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pesananMenu" role="button" aria-expanded="false" aria-controls="pesananMenu">
                    <i class="fas fa-list-check"></i>
                    <span class="ml-2">Pesanan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pesananMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/daftar/pesanan.php">Daftar Pesanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengiriman/pengiriman.php">Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengembalian/pengembalian_barang.php">Pengembalian Barang</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pembayaranMenu" role="button" aria-expanded="false" aria-controls="pembayaranMenu">
                    <i class="fas fa-wallet"></i>
                    <span class="ml-2">Pembayaran</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pembayaranMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/riwayat/pembayaran.php">Riwayat Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/penarikan/penarikan.php">Penarikan Dana</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/catatan/transaksi_lain.php">Catatan Transaksi Lain</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/metode/metode_pembayaran.php">Metode Pembayaran</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#laporanMenu" role="button" aria-expanded="false" aria-controls="laporanMenu">
                    <i class="fas fa-chart-line"></i>
                    <span class="ml-2">Laporan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="laporanMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/penjualan/laporan_penjualan.php">Laporan Penjualan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/stok/laporan_stok.php">Laporan Stok</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/keuangan/laporan_keuangan.php">Laporan Keuangan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#promosiMenu" role="button" aria-expanded="false" aria-controls="promosiMenu">
                    <i class="fas fa-bullhorn"></i>
                    <span class="ml-2">Promosi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="promosiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../promosi/diskon.php">Diskon</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#tokoMenu" role="button" aria-expanded="false" aria-controls="tokoMenu">
                    <i class="fas fa-store"></i>
                    <span class="ml-2">Toko Saya</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="tokoMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/profil/profil_toko.php">Profil Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengaturan/pengaturan_toko.php">Pengaturan Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengiriman/pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pembayaran/pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/unit/unit_usaha_saya.php">Unit Usaha Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#komunikasiMenu" role="button" aria-expanded="true" aria-controls="komunikasiMenu">
                    <i class="fas fa-envelope"></i>
                    <span class="ml-2">Komunikasi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="komunikasiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="pesan_masuk.php">Pesan Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../ulasan/ulasan.php">Ulasan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#artikelMenu" role="button" aria-expanded="false" aria-controls="artikelMenu">
                    <i class="fas fa-newspaper"></i>
                    <span class="ml-2">Artikel</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="artikelMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../artikel/artikel_saya.php">Artikel Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#akunMenu" role="button" aria-expanded="false" aria-controls="akunMenu">
                    <i class="fas fa-user"></i>
                    <span class="ml-2">Akun</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="akunMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../akun/profil.php">Profil</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <a class="nav-link text-danger" href="../../../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="ml-2">Logout</span>
        </a>
    </div>
    <div class="content">
        <h1>Pesan Masuk</h1>
        <hr>

        <?php if ($pesan_sukses) : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($pesan_sukses) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if ($pesan_error) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($pesan_error) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card p-4">
            <h4 class="mb-4">Daftar Pesan</h4>

            <?php if (empty($daftar_pesan)) : ?>
                <div class="alert alert-info">Anda belum memiliki pesan masuk.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Pengirim</th>
                                <th>Isi Pesan</th>
                                <th>Waktu Kirim</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($daftar_pesan as $pesan) : ?>
                                <tr class="<?= ($pesan['status_baca'] == 'belum_dibaca') ? 'font-weight-bold' : '' ?>">
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($pesan['nama_pengirim']) ?></td>
                                    <td><?= htmlspecialchars(substr($pesan['isi_pesan'], 0, 100)) . (strlen($pesan['isi_pesan']) > 100 ? '...' : '') ?></td>
                                    <td><?= date('d M Y H:i', strtotime($pesan['waktu_kirim'])) ?></td>
                                    <td>
                                        <?php if ($pesan['status_baca'] == 'belum_dibaca') : ?>
                                            <span class="badge badge-warning">Belum Dibaca</span>
                                        <?php else : ?>
                                            <span class="badge badge-success">Sudah Dibaca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info mb-1"
                                            data-toggle="modal"
                                            data-target="#detailPesanModal"
                                            data-id="<?= $pesan['id'] ?>"
                                            data-pengirim="<?= htmlspecialchars($pesan['nama_pengirim']) ?>"
                                            data-konten="<?= htmlspecialchars($pesan['isi_pesan']) ?>"
                                            data-waktu="<?= htmlspecialchars(date('d M Y H:i', strtotime($pesan['waktu_kirim']))) ?>"
                                            data-pengguna-id="<?= $pesan['pengirim_pengguna_id'] ?>">
                                            Lihat
                                        </button>
                                        <?php if ($pesan['status_baca'] == 'belum_dibaca') : ?>
                                            <a href="pesan_masuk.php?action=mark_read&id=<?= $pesan['id'] ?>"
                                               class="btn btn-sm btn-primary mb-1">
                                                Baca
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-success mb-1"
                                            data-toggle="modal"
                                            data-target="#balasPesanModal"
                                            data-id="<?= $pesan['id'] ?>"
                                            data-pengirim="<?= htmlspecialchars($pesan['nama_pengirim']) ?>"
                                            data-pengguna-id="<?= $pesan['pengirim_pengguna_id'] ?>">
                                            Balas
                                        </button>
                                        <a href="pesan_masuk.php?action=delete&id=<?= $pesan['id'] ?>"
                                           class="btn btn-sm btn-danger mb-1"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus pesan ini?');">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="detailPesanModal" tabindex="-1" aria-labelledby="detailPesanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailPesanModalLabel">Detail Pesan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Pengirim:</strong> <span id="detail_pengirim"></span></p>
                <p><strong>Waktu Kirim:</strong> <span id="detail_waktu"></span></p>
                <hr>
                <p><strong>Isi Pesan:</strong></p>
                <p id="detail_konten" style="white-space: pre-wrap;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success" id="btn_balas_dari_detail">Balas</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="balasPesanModal" tabindex="-1" aria-labelledby="balasPesanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="balasPesanModalLabel">Balas Pesan ke <span id="balas_pengirim_nama"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="penerima_pengguna_id" id="balas_penerima_pengguna_id">
                    <input type="hidden" name="pesan_original_id" id="balas_pesan_original_id">
                    <div class="form-group">
                        <label for="isi_balasan">Isi Balasan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="isi_balasan" name="isi_balasan" rows="6" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="balas_pesan" class="btn btn-primary">Kirim Balasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    $(document).ready(function() {
        // Ketika modal detail pesan muncul
        $('#detailPesanModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button yang memicu modal
            var id = button.data('id');
            var pengirim = button.data('pengirim');
            var konten = button.data('konten');
            var waktu = button.data('waktu');
            var penggunaId = button.data('pengguna-id'); // ID pengguna pengirim

            var modal = $(this);
            modal.find('#detail_pengirim').text(pengirim);
            modal.find('#detail_waktu').text(waktu);
            modal.find('#detail_konten').text(konten);

            // Set data untuk tombol balas dari modal detail
            $('#btn_balas_dari_detail').data('id', id);
            $('#btn_balas_dari_detail').data('pengirim', pengirim);
            $('#btn_balas_dari_detail').data('pengguna-id', penggunaId);
        });

        // Ketika tombol 'Balas' di modal detail diklik
        $('#btn_balas_dari_detail').on('click', function() {
            $('#detailPesanModal').modal('hide'); // Sembunyikan modal detail
            // Tampilkan modal balas dengan data yang sama
            $('#balasPesanModal').modal('show', $(this)); // Kirim data dari tombol 'Balas'
        });

        // Ketika modal balas pesan muncul (baik dari tombol 'Balas' di tabel atau dari modal detail)
        $('#balasPesanModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button yang memicu modal (bisa tombol 'Balas' di tabel atau 'btn_balas_dari_detail')
            var id = button.data('id'); // ID pesan asli
            var pengirim = button.data('pengirim');
            var penggunaId = button.data('pengguna-id'); // ID pengguna pengirim asli

            var modal = $(this);
            modal.find('#balas_pengirim_nama').text(pengirim);
            modal.find('#balas_penerima_pengguna_id').val(penggunaId);
            modal.find('#balas_pesan_original_id').val(id); // Simpan ID pesan asli jika perlu
            modal.find('#isi_balasan').val(''); // Kosongkan textarea balasan
        });
    });
</script>

</body>
</html>