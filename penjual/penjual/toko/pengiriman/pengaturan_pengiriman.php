<?php
session_start();
include('../../../koneksi/koneksi.php'); // Sesuaikan path ini sesuai lokasi koneksi.php Anda

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php'); // Sesuaikan path ini
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// Ambil data pengguna (untuk mendapatkan nama lengkap di navbar)
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);
mysqli_stmt_close($stmt_pengguna);

if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan.'); window.location.href='../../../logout.php';</script>"; // Sesuaikan path ini
    exit;
}

// Ambil data penjual (untuk menampilkan informasi toko, foto profil, dan kebijakan pengiriman)
$query_penjual = "SELECT nama_toko, nama_pemilik, email, username, foto, nomor_telepon, alamat, kebijakan_pengiriman FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
mysqli_stmt_close($stmt_penjual);

if (!$penjual) {
    echo "<script>alert('Data penjual tidak ditemukan.'); window.location.href='../../../logout.php';</script>"; // Sesuaikan path ini
    exit;
}

// Ambil semua kurir yang tersedia di platform dari tabel 'kurir' Anda
$kurir_platform = [];
$query_kurir = "SELECT id, nama, foto, status FROM kurir WHERE status = 'aktif' ORDER BY nama ASC"; // Asumsi 'status' adalah string 'aktif'
$result_kurir = mysqli_query($conn, $query_kurir);
if ($result_kurir) {
    while ($row_kurir = mysqli_fetch_assoc($result_kurir)) {
        $kurir_platform[] = $row_kurir;
    }
} else {
    // Handle error if query fails (e.g., table not found or column missing)
    error_log("Error fetching kurir data: " . mysqli_error($conn));
}


// Ambil pengaturan kurir penjual yang sudah ada
$pengaturan_kurir_penjual = [];
$query_pengaturan_kurir_penjual = "SELECT pkp.id, pkp.kurir_id, pkp.biaya_default, pkp.tipe_biaya, pkp.aktif_penjual
                                   FROM pengaturan_kurir_penjual pkp
                                   WHERE pkp.pengguna_id = ?"; // pkp.penjual_id diganti pkp.pengguna_id

// Pengecekan apakah mysqli_prepare berhasil
if ($stmt_pengaturan_kurir_penjual = mysqli_prepare($conn, $query_pengaturan_kurir_penjual)) {
    mysqli_stmt_bind_param($stmt_pengaturan_kurir_penjual, 'i', $user_id);

    // Pengecekan apakah mysqli_stmt_execute berhasil
    if (mysqli_stmt_execute($stmt_pengaturan_kurir_penjual)) {
        $result_pengaturan_kurir_penjual = mysqli_stmt_get_result($stmt_pengaturan_kurir_penjual);
        while ($row_pengaturan = mysqli_fetch_assoc($result_pengaturan_kurir_penjual)) {
            $pengaturan_kurir_penjual[$row_pengaturan['kurir_id']] = $row_pengaturan;
        }
    } else {
        // Jika eksekusi gagal
        error_log("Error saat mengeksekusi statement pengaturan_kurir_penjual: " . mysqli_stmt_error($stmt_pengaturan_kurir_penjual));
        //echo "Error saat mengeksekusi statement: " . mysqli_stmt_error($stmt_pengaturan_kurir_penjual); // Tampilkan di frontend untuk debugging
        //die(); // Hentikan eksekusi
    }
    mysqli_stmt_close($stmt_pengaturan_kurir_penjual);
} else {
    // Jika prepare gagal (biasanya karena nama tabel/kolom salah)
    error_log("Error saat mempersiapkan statement pengaturan_kurir_penjual: " . mysqli_error($conn));
    //echo "Error saat mempersiapkan statement: " . mysqli_error($conn); // Tampilkan di frontend untuk debugging
    //die(); // Hentikan eksekusi
}

// Proses update pengaturan pengiriman
if (isset($_POST['simpan_pengiriman'])) {
    $kebijakan_pengiriman_toko = $_POST['kebijakan_pengiriman_toko'];

    // Update kebijakan pengiriman di tabel penjual
    $update_penjual_kebijakan = "UPDATE penjual SET kebijakan_pengiriman = ?, updated_at = NOW() WHERE pengguna_id = ?";
    $stmt_kebijakan = mysqli_prepare($conn, $update_penjual_kebijakan);
    mysqli_stmt_bind_param($stmt_kebijakan, 'si', $kebijakan_pengiriman_toko, $user_id);
    mysqli_stmt_execute($stmt_kebijakan);
    mysqli_stmt_close($stmt_kebijakan);

    // Proses untuk setiap kurir yang tersedia di platform
    foreach ($kurir_platform as $kurir) {
        $kurir_id = $kurir['id'];
        $aktif_penjual = isset($_POST['kurir_aktif_' . $kurir_id]) ? 1 : 0;
        $biaya_default = filter_var($_POST['biaya_default_' . $kurir_id], FILTER_VALIDATE_FLOAT) ?: 0;
        $tipe_biaya = $_POST['tipe_biaya_' . $kurir_id];

        if (isset($pengaturan_kurir_penjual[$kurir_id])) {
            // Update pengaturan yang sudah ada untuk kurir ini
            $update_query = "UPDATE pengaturan_kurir_penjual SET
                                biaya_default = ?, tipe_biaya = ?, aktif_penjual = ?, updated_at = NOW()
                             WHERE pengguna_id = ? AND kurir_id = ?"; // Kondisi WHERE diubah
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, 'dsiii', $biaya_default, $tipe_biaya, $aktif_penjual, $user_id, $kurir_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
        } else {
            // Insert pengaturan baru untuk kurir ini
            $insert_query = "INSERT INTO pengaturan_kurir_penjual (pengguna_id, kurir_id, biaya_default, tipe_biaya, aktif_penjual, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"; // pengguna_id
            $stmt_insert = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt_insert, 'iidsi', $user_id, $kurir_id, $biaya_default, $tipe_biaya, $aktif_penjual);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);
        }
    }
    echo "<script>alert('Pengaturan pengiriman berhasil diperbarui.'); window.location.href='pengaturan_pengiriman.php';</script>";
    exit;
}

// Data statistik (seperti di dashboard_penjual.php, bisa disesuaikan)
$query_menunggu = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'menunggu_pembayaran'";
$result_menunggu = mysqli_query($conn, $query_menunggu);
$row_menunggu = mysqli_fetch_assoc($result_menunggu);
$total_menunggu = $row_menunggu['total'];

$query_diproses = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'diproses'";
$result_diproses = mysqli_query($conn, $query_diproses);
$row_diproses = mysqli_fetch_assoc($result_diproses);
$total_diproses = $row_diproses['total'];

$query_dikirim = "SELECT COUNT(id) AS total FROM pesanan WHERE status_pesanan = 'dikirim'";
$result_dikirim = mysqli_query($conn, $query_dikirim);
$row_dikirim = mysqli_fetch_assoc($result_dikirim);
$total_dikirim = $row_dikirim['total'];

$query_produk = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ?";
$stmt_produk = mysqli_prepare($conn, $query_produk);
mysqli_stmt_bind_param($stmt_produk, 'i', $user_id);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$row_produk = mysqli_fetch_assoc($result_produk);
$total_produk = $row_produk['total'];
mysqli_stmt_close($stmt_produk);

$query_stok_rendah = "SELECT COUNT(id) AS total FROM produk WHERE penjual_id = ? AND stok < 5";
$stmt_stok_rendah = mysqli_prepare($conn, $query_stok_rendah);
mysqli_stmt_bind_param($stmt_stok_rendah, 'i', $user_id);
mysqli_stmt_execute($stmt_stok_rendah);
$result_stok_rendah = mysqli_stmt_get_result($stmt_stok_rendah);
$row_stok_rendah = mysqli_fetch_assoc($result_stok_rendah);
$total_stok_rendah = $row_stok_rendah['total'];
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
    <title>Pengaturan Pengiriman - Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
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
                            <a class="nav-link submenu" href="../pengaturan/pengaturan_toko.php">Pengaturan Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../pembayaran/pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
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
        <h1>Pengaturan Pengiriman</h1>
        <hr>

        <div class="card p-4">
            <h4 class="mb-4">Kelola Opsi Pengiriman Toko Anda</h4>
            <form action="" method="POST">
                <div class="form-group mb-4">
                    <label for="kebijakan_pengiriman_toko">Kebijakan Pengiriman Toko</label>
                    <textarea class="form-control" id="kebijakan_pengiriman_toko" name="kebijakan_pengiriman_toko" rows="5"><?= htmlspecialchars($penjual['kebijakan_pengiriman'] ?? '') ?></textarea>
                    <small class="form-text text-muted">Jelaskan kebijakan pengiriman Anda (misal: estimasi waktu, area tidak terjangkau, dll).</small>
                </div>

                <h5 class="mt-4 mb-3">Kurir yang Tersedia untuk Toko Anda</h5>
                <?php if (empty($kurir_platform)) : ?>
                    <div class="alert alert-info">Tidak ada kurir yang tersedia di platform. Silakan hubungi administrator.</div>
                <?php else : ?>
                    <?php foreach ($kurir_platform as $kurir) :
                        $current_pengaturan = $pengaturan_kurir_penjual[$kurir['id']] ?? null;
                        $is_aktif_penjual = $current_pengaturan ? $current_pengaturan['aktif_penjual'] : 0;
                        $biaya_default = $current_pengaturan ? $current_pengaturan['biaya_default'] : 0;
                        $tipe_biaya = $current_pengaturan ? $current_pengaturan['tipe_biaya'] : 'flat_rate';
                    ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($kurir['foto']) : // Menggunakan 'foto' sebagai logo ?>
                                        <img src="../../../img/kurir/<?= htmlspecialchars($kurir['foto']) ?>" alt="<?= htmlspecialchars($kurir['nama']) ?>" width="50" class="mr-3">
                                    <?php endif; ?>
                                    <h6 class="mb-0"><?= htmlspecialchars($kurir['nama']) ?></h6>
                                    <div class="form-check form-switch ml-auto">
                                        <input class="form-check-input" type="checkbox" id="kurir_aktif_<?= $kurir['id'] ?>" name="kurir_aktif_<?= $kurir['id'] ?>" value="1" <?= $is_aktif_penjual ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="kurir_aktif_<?= $kurir['id'] ?>">Aktifkan</label>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="biaya_default_<?= $kurir['id'] ?>">Biaya Default (Rp)</label>
                                            <input type="number" step="0.01" class="form-control" id="biaya_default_<?= $kurir['id'] ?>" name="biaya_default_<?= $kurir['id'] ?>" value="<?= htmlspecialchars($biaya_default) ?>">
                                            <small class="form-text text-muted">Masukkan biaya dasar jika menggunakan flat rate.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tipe_biaya_<?= $kurir['id'] ?>">Tipe Perhitungan Biaya</label>
                                            <select class="form-control" id="tipe_biaya_<?= $kurir['id'] ?>" name="tipe_biaya_<?= $kurir['id']?>">
                                                <option value="flat_rate" <?= ($tipe_biaya == 'flat_rate') ? 'selected' : '' ?>>Flat Rate</option>
                                                <option value="per_berat" <?= ($tipe_biaya == 'per_berat') ? 'selected' : '' ?>>Per Berat Barang</option>
                                                <option value="dinamis_api" <?= ($tipe_biaya == 'dinamis_api') ? 'selected' : '' ?>>Dinamis (API Eksternal)</option>
                                            </select>
                                            <small class="form-text text-muted">Pilih cara biaya pengiriman dihitung.</small>
                                        </div>
                                    </div>
                                </div>
                                </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <button type="submit" name="simpan_pengiriman" class="btn btn-primary mt-4">Simpan Pengaturan Pengiriman</button>
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
        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });

        // Bagian AJAX chart ini tidak relevan untuk halaman pengaturan pengiriman
        // Disarankan untuk menghapusnya agar kode lebih bersih
        $.ajax({
            url: '../../../get_sales_data.php', // Sesuaikan path ini
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    console.error('Gagal mengambil data penjualan:', data.error);
                    return;
                }
                var salesChartCanvas = document.getElementById('salesChart');
                if (salesChartCanvas) {
                    var ctx = salesChartCanvas.getContext('2d');
                    var salesChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Total Penjualan (Rp)',
                                data: data.data,
                                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Terjadi kesalahan saat mengambil data penjualan:', status, error);
            }
        });
    });
</script>

</body>
</html>