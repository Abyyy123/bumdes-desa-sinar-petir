<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php'); 
    exit;
}

$user_id = $_SESSION['pengguna_id']; 


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


$query_penjual = "SELECT nama_toko, nama_pemilik, email, username, foto, nomor_telepon, alamat, kebijakan_pengiriman FROM penjual WHERE pengguna_id = ?";
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

$metode_pembayaran_platform = [];
$query_metode_pembayaran = "SELECT id, nama_metode, logo_metode, tipe_pembayaran FROM metode_pembayaran WHERE aktif_platform = TRUE ORDER BY nama_metode ASC";
$result_metode_pembayaran = mysqli_query($conn, $query_metode_pembayaran);
if ($result_metode_pembayaran) {
    while ($row_metode = mysqli_fetch_assoc($result_metode_pembayaran)) {
        $metode_pembayaran_platform[] = $row_metode;
    }
} else {
    error_log("Error fetching metode pembayaran data: " . mysqli_error($conn));
}

$pengaturan_pembayaran_penjual = [];
$query_pengaturan_pembayaran_penjual = "SELECT ppp.id, ppp.metode_pembayaran_id, ppp.detail_akun, ppp.nama_pemilik_akun, ppp.aktif_penjual
                                         FROM pengaturan_pembayaran_penjual ppp
                                         WHERE ppp.pengguna_id = ?";

if ($stmt_pengaturan_pembayaran_penjual = mysqli_prepare($conn, $query_pengaturan_pembayaran_penjual)) {
    mysqli_stmt_bind_param($stmt_pengaturan_pembayaran_penjual, 'i', $user_id);
    if (mysqli_stmt_execute($stmt_pengaturan_pembayaran_penjual)) {
        $result_pengaturan_pembayaran_penjual = mysqli_stmt_get_result($stmt_pengaturan_pembayaran_penjual);
        while ($row_pengaturan = mysqli_fetch_assoc($result_pengaturan_pembayaran_penjual)) {
            $pengaturan_pembayaran_penjual[$row_pengaturan['metode_pembayaran_id']] = $row_pengaturan;
        }
    } else {
        error_log("Error executing statement for pengaturan_pembayaran_penjual: " . mysqli_stmt_error($stmt_pengaturan_pembayaran_penjual));
    }
    mysqli_stmt_close($stmt_pengaturan_pembayaran_penjual);
} else {
    error_log("Error preparing statement for pengaturan_pembayaran_penjual: " . mysqli_error($conn));
}


// 3. Proses ketika formulir disimpan (POST request)
if (isset($_POST['simpan_pembayaran'])) {
    foreach ($metode_pembayaran_platform as $metode) {
        $metode_id = $metode['id'];
        $aktif_penjual = isset($_POST['metode_aktif_' . $metode_id]) ? 1 : 0;
        $detail_akun = trim($_POST['detail_akun_' . $metode_id]); 
        $nama_pemilik_akun = trim($_POST['nama_pemilik_akun_' . $metode_id]);

        if (isset($pengaturan_pembayaran_penjual[$metode_id])) {
            $update_query = "UPDATE pengaturan_pembayaran_penjual SET
                                detail_akun = ?, nama_pemilik_akun = ?, aktif_penjual = ?, updated_at = NOW()
                             WHERE pengguna_id = ? AND metode_pembayaran_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, 'ssiii', $detail_akun, $nama_pemilik_akun, $aktif_penjual, $user_id, $metode_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        } else {
            $insert_query = "INSERT INTO pengaturan_pembayaran_penjual (pengguna_id, metode_pembayaran_id, detail_akun, nama_pemilik_akun, aktif_penjual, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt_insert = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt_insert, 'iissi', $user_id, $metode_id, $detail_akun, $nama_pemilik_akun, $aktif_penjual);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);
        }
    }
    echo "<script>alert('Pengaturan pembayaran berhasil diperbarui.'); window.location.href='pengaturan_pembayaran.php';</script>";
    exit;
}

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
mysqli_stmt_bind_param($stmt_produk, 'i', $user_id);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'] ?? 0;
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
mysqli_stmt_bind_param($stmt_stok_rendah, 'i', $user_id);
mysqli_stmt_execute($stmt_stok_rendah);
$result_stok_rendah = mysqli_stmt_get_result($stmt_stok_rendah);
$row_stok_rendah = mysqli_fetch_assoc($result_stok_rendah);
$total_stok_rendah = $row_stok_rendah['total'] ?? 0;
mysqli_stmt_close($stmt_stok_rendah);

$query_pendapatan = "SELECT SUM(p.total_harga) AS total
                     FROM pesanan p
                     WHERE p.status_pesanan = 'selesai'"; // Asumsi ini pendapatan keseluruhan dari semua penjual atau total pendapatan untuk penjual ini
$result_pendapatan = mysqli_query($conn, $query_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total'] ?: 0; // Jika null, set ke 0

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Pembayaran - Dashboard Penjual</title>
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
            <form action="../profil/profil_toko.php" method="POST" enctype="multipart/form-data"> <div class="modal-header">
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
                <a class="nav-link collapsed" data-toggle="collapse" href="#tokoMenu" role="button" aria-expanded="true" aria-controls="tokoMenu">
                    <i class="fas fa-store"></i>
                    <span class="ml-2">Toko Saya</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="tokoMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../profil/profil_toko.php">Profil Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pengaturan/pengaturan_toko.php">Pengaturan Toko</a> </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pengiriman/pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../unit/unit_usaha_saya.php">Unit Usaha Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#komunikasiMenu" role="button" aria-expanded="false" aria-controls="komunikasiMenu">
                    <i class="fas fa-envelope"></i>
                    <span class="ml-2">Komunikasi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="komunikasiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../komunikasi/pesan/pesan_masuk.php">Pesan Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../komunikasi/ulasan/ulasan.php">Ulasan</a>
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
        <h1>Pengaturan Pembayaran</h1>
        <hr>

        <div class="card p-4">
            <h4 class="mb-4">Kelola Metode Pembayaran Toko Anda</h4>
            <form action="" method="POST">
                <?php if (empty($metode_pembayaran_platform)) : ?>
                    <div class="alert alert-info">Tidak ada metode pembayaran yang tersedia di platform. Silakan hubungi administrator.</div>
                <?php else : ?>
                    <?php foreach ($metode_pembayaran_platform as $metode) :
                        // Ambil pengaturan spesifik untuk metode ini dari array yang sudah diambil
                        $current_pengaturan = $pengaturan_pembayaran_penjual[$metode['id']] ?? null;
                        
                        // Set nilai default jika belum ada pengaturan
                        $is_aktif_penjual = $current_pengaturan ? $current_pengaturan['aktif_penjual'] : 0;
                        $detail_akun = $current_pengaturan ? $current_pengaturan['detail_akun'] : '';
                        $nama_pemilik_akun = $current_pengaturan ? $current_pengaturan['nama_pemilik_akun'] : '';
                    ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($metode['logo_metode']) : // Tampilkan logo jika ada ?>
                                        <img src="../../../img/metode_pembayaran/<?= htmlspecialchars($metode['logo_metode']) ?>" alt="<?= htmlspecialchars($metode['nama_metode']) ?>" width="50" class="mr-3">
                                    <?php endif; ?>
                                    <h6 class="mb-0"><?= htmlspecialchars($metode['nama_metode']) ?></h6>
                                    <div class="form-check form-switch ml-auto">
                                        <input class="form-check-input" type="checkbox" id="metode_aktif_<?= $metode['id'] ?>" name="metode_aktif_<?= $metode['id'] ?>" value="1" <?= $is_aktif_penjual ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="metode_aktif_<?= $metode['id'] ?>">Aktifkan</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="detail_akun_<?= $metode['id'] ?>">Detail Akun</label>
                                    <input type="text" class="form-control" id="detail_akun_<?= $metode['id'] ?>" name="detail_akun_<?= $metode['id'] ?>" value="<?= htmlspecialchars($detail_akun) ?>" placeholder="Contoh: Nomor Rekening, Nomor Telepon E-wallet, ID QRIS">
                                    <small class="form-text text-muted">Isi dengan nomor rekening/akun, atau ID yang relevan untuk metode ini.</small>
                                </div>
                                <div class="form-group">
                                    <label for="nama_pemilik_akun_<?= $metode['id'] ?>">Nama Pemilik Akun</label>
                                    <input type="text" class="form-control" id="nama_pemilik_akun_<?= $metode['id'] ?>" name="nama_pemilik_akun_<?= $metode['id'] ?>" value="<?= htmlspecialchars($nama_pemilik_akun) ?>" placeholder="Contoh: Nama Pemilik Rekening">
                                    <small class="form-text text-muted">Nama pemilik rekening/akun yang akan dilihat oleh pembeli.</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <button type="submit" name="simpan_pembayaran" class="btn btn-primary mt-4">Simpan Pengaturan Pembayaran</button>
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
        // Menyesuaikan margin-left konten saat sidebar di-toggle
        document.querySelector(".content").classList.toggle('ml-collapsed'); // Anda perlu menambahkan CSS untuk .ml-collapsed jika belum ada
    }

    $(document).ready(function() {

        $('.nav-link[data-toggle="collapse"]').click(function(e) {
        });
    });
</script>

</body>
</html>