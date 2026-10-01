<?php
session_start();
include('../koneksi/koneksi.php');

// Pastikan pengguna sudah login dan memiliki role 'ketua'
if (!isset($_SESSION['pengguna_id']) || $_SESSION['role'] !== 'ketua') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];
$query = "SELECT * FROM pengguna WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Proses update profil
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'] != '' ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password'];
    $foto_lama = $user['foto'];
    $foto_baru = $foto_lama; // Inisialisasi dengan foto lama

    if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../img/foto/';
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Hapus foto lama jika ada dan berbeda dengan yang baru
            if ($foto_lama && file_exists($upload_dir . $foto_lama) && $foto_lama != $foto_name) {
                unlink($upload_dir . $foto_lama);
            }
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                $foto_baru = $foto_name; // Update dengan nama file baru
            } else {
                echo "<script>alert('Gagal mengunggah foto baru.');</script>";
            }
        } else {
            echo "<script>alert('Jenis file foto tidak diizinkan (hanya jpg, jpeg, png, gif).');</script>";
        }
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi error saat mengunggah foto: " . $_FILES['foto']['error'] . "');</script>";
    }

    $update = "UPDATE pengguna SET nama=?, username=?, email=?, password=?, foto=? WHERE id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt_update, 'sssssi', $nama, $username, $email, $password, $foto_baru, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        // Perbarui data $user setelah update
        $user['nama'] = $nama;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['foto'] = $foto_baru;

        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='dashboard_ketua.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// Ambil total pendapatan
$sql_pendapatan = "SELECT SUM(jumlah) AS total FROM transaksi_keuangan WHERE jenis_transaksi = 'pemasukan'";
$result_pendapatan = mysqli_query($conn, $sql_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total'] ?? 0;

// Ambil total pengeluaran
$sql_pengeluaran = "SELECT SUM(jumlah) AS total FROM transaksi_keuangan WHERE jenis_transaksi = 'pengeluaran'";
$result_pengeluaran = mysqli_query($conn, $sql_pengeluaran);
$row_pengeluaran = mysqli_fetch_assoc($result_pengeluaran);
$total_pengeluaran = $row_pengeluaran['total'] ?? 0;

// Hitung keuntungan bersih
$keuntungan_bersih = $total_pendapatan - $total_pengeluaran;

// Query untuk mendapatkan data penjualan per bulan
$query_penjualan_bulanan = "SELECT
                            MONTH(tanggal_transaksi) AS bulan,
                            SUM(jumlah) AS total_penjualan
                         FROM transaksi_keuangan
                         WHERE jenis_transaksi = 'pemasukan'
                         GROUP BY MONTH(tanggal_transaksi)
                         ORDER BY bulan ASC";

$result_penjualan_bulanan = mysqli_query($conn, $query_penjualan_bulanan);
$bulan = [];
$total_penjualan_arr = []; // Ubah nama variabel untuk menghindari konflik
while ($row_penjualan = mysqli_fetch_assoc($result_penjualan_bulanan)) {
    $bulan[] = $row_penjualan['bulan'];
    $total_penjualan_arr[] = $row_penjualan['total_penjualan'];
}

// Ringkasan data (Laporan Sekilas untuk Ketua)
$total_bumdes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bumdes"))['total'];
$total_unit_usaha = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM unit_usaha"))['total'];
$total_anggota = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM anggota"))['total'];
$total_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna"))['total']; // Termasuk admin, ketua, dll
$total_pelanggan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan"))['total'];
$total_penjual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM penjual"))['total'];
$total_kurir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kurir"))['total'];
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$total_pesanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan"))['total'];
$total_pembayaran = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pembayaran"))['total'];
$total_transaksi_keuangan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi_keuangan"))['total']; // Ganti nama variabel
$pesanan_menunggu_pembayaran = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pesanan WHERE status_pesanan = 'menunggu_pembayaran'"));
$pembayaran_menunggu_konfirmasi = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pembayaran WHERE status_pembayaran = 'menunggu_konfirmasi'"));
$ulasan_baru = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM ulasan_produk")); // Menghitung total ulasan produk
$pengembalian_menunggu = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengembalian_barang WHERE status_pengembalian = 'menunggu_persetujuan'")); // Contoh
$stok_rendah = mysqli_query($conn, "SELECT nama, stok FROM produk WHERE stok <= 5 ORDER BY stok ASC LIMIT 5");

// Data untuk grafik ringkasan status pesanan
$status_pesanan_data = [];
$query_status_pesanan = "SELECT status_pesanan, COUNT(*) as total FROM pesanan GROUP BY status_pesanan";
$result_status_pesanan = mysqli_query($conn, $query_status_pesanan);
while($row_status = mysqli_fetch_assoc($result_status_pesanan)){
    $status_pesanan_data[$row_status['status_pesanan']] = $row_status['total'];
}
$labels_status_pesanan = json_encode(array_keys($status_pesanan_data));
$data_status_pesanan = json_encode(array_values($status_pesanan_data));


// Data untuk grafik ringkasan transaksi keuangan (pemasukan vs pengeluaran)
$labels_keuangan = json_encode(['Pemasukan', 'Pengeluaran']);
$data_keuangan = json_encode([$total_pendapatan, $total_pengeluaran]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Ketua BUMDes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Warna Identik Lampung */
        :root {
            --lampung-gold: #FFD700; /* Kuning Emas */
            --lampung-red: #DC143C; /* Merah menyala, lebih dekat ke Crimson */
            --lampung-green: #228B22; /* Hijau (Forest Green) */
            --lampung-blue: #4682B4; /* Biru (Steel Blue) */
            --lampung-white: #FFFFFF; /* Putih */
            --lampung-dark-gray: #343a40; /* Untuk teks atau latar belakang gelap */
            --lampung-light-bg: #f8f9fa; /* Latar belakang terang */
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: var(--lampung-light-bg); /* Latar belakang terang */
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
            background-color: var(--lampung-dark-gray); /* Tetap gelap agar kontras dengan teks */
            min-height: 100vh;
            padding: 20px 0;
            color: var(--lampung-white);
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: var(--lampung-gold); /* Judul sidebar dengan kuning emas */
            margin-bottom: 30px;
        }
        .sidebar a {
            color: var(--lampung-white);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: var(--lampung-gold); /* Hover pada kuning emas */
            color: var(--lampung-dark-gray); /* Teks jadi gelap saat hover */
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: var(--lampung-white);
        }
        .sidebar .submenu:hover {
            color: var(--lampung-dark-gray); /* Teks submenu jadi gelap saat hover */
            background-color: var(--lampung-gold); /* Latar submenu jadi kuning emas saat hover */
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
            color: var(--lampung-white);
            margin-left: 20px;
            font-size: 20px;
        }

        /* Card Dashboard - Warna Lampung */
        .card-dashboard {
            border-radius: 10px;
            color: var(--lampung-white); /* Teks putih untuk card berwarna */
        }
        .card-dashboard.bg-primary { /* Ganti primary ke kuning emas */
            background-color: var(--lampung-gold) !important;
        }
        .card-dashboard.bg-success { /* Untuk elemen positif/growth */
            background-color: var(--lampung-green) !important;
        }
        .card-dashboard.bg-info { /* Untuk info umum */
            background-color: var(--lampung-blue) !important;
        }
        .card-dashboard.bg-warning { /* Untuk perhatian/peringatan */
            background-color: orange !important; /* Warna kuning standar tetap karena warning */
        }
        .card-dashboard.bg-danger { /* Untuk masalah/urgent */
            background-color: var(--lampung-red) !important;
        }
        .card-dashboard.bg-secondary { /* Untuk warna netral/lainnya */
            background-color: #6c757d !important; /* Abu-abu standar */
        }
        .card-dashboard.bg-dark { /* Untuk warna gelap lainnya */
            background-color: #343a40 !important;
        }
        .card-dashboard .card-title {
            color: var(--lampung-white);
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
            background: var(--lampung-white);
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .profile-menu h5 {
            margin-top: 0;
            color: var(--lampung-dark-gray);
        }
        .profile-menu p {
            margin: 0;
            color: var(--lampung-dark-gray);
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: var(--lampung-blue); /* Gunakan biru untuk link */
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: var(--lampung-white);
            padding: 20px;
            border-radius: 10px;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .custom-card {
        height: 80px;        /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;    /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;      /* jarak luar card */
    }
    #salesChart, #statusPesananChart, #keuanganChart {
        width: 100% !important;
        max-width: 600px !important; /* Sesuaikan sesuai kebutuhan */
        height: 300px !important;    /* Sesuaikan sesuai kebutuhan */
    }
    .badge.bg-danger { /* Untuk stok rendah */
        background-color: var(--lampung-red) !important;
        color: var(--lampung-white) !important;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#" style="color: var(--lampung-gold);">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                <a class="btn btn-link p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal" style="color: var(--lampung-blue);">Edit Profil</a>
                <a class="btn btn-link p-0 d-block" href="../logout.php" style="color: var(--lampung-red);">Logout</a>
                </div>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($user['foto']) : ?>
                            <img src="../img/foto/<?= $user['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-primary" style="background-color: var(--lampung-gold); border-color: var(--lampung-gold); color: var(--lampung-dark-gray);">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="wrapper">
    <div class="sidebar" id="sidebar">
    <h4>&nbsp;</h4>
            <li class="nav-item">
                <a class="nav-link" href="dashboard_ketua.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#laporanUmum" role="button" aria-expanded="false" aria-controls="laporanUmum">
        <i class="fas fa-file-alt"></i>
        <span class="ml-2">Laporan Umum</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="laporanUmum">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=bumdes">Laporan BUMDes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=unit_usaha">Laporan Unit Usaha</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=anggota">Laporan Anggota</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=pengguna">Laporan Pengguna</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#laporanEcom" role="button" aria-expanded="false" aria-controls="laporanEcom">
        <i class="fas fa-file-invoice-dollar"></i>
        <span class="ml-2">Laporan E-commerce</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="laporanEcom">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=pelanggan">Laporan Pelanggan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=penjual">Laporan Penjual</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=kurir">Laporan Kurir</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=produk">Laporan Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=pesanan">Laporan Pesanan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=pembayaran">Laporan Pembayaran</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=ulasan">Laporan Ulasan Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="laporan.php?type=pengembalian">Laporan Pengembalian Barang</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
  <a class="nav-link" href="laporan.php?type=artikel">
    <i class="fas fa-newspaper"></i>
    <span class="ml-2">Laporan Artikel</span>
  </a>
</li>
</ul>
<a href="../logout.php" class="text-danger" style="color: var(--lampung-red) !important;"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
</div>

<div class="content">
    <h1>Dashboard Ketua BUMDes</h1>
    <h3>Selamat datang, <?= htmlspecialchars($user['nama']); ?>!</h3>
    <p>Ini adalah ringkasan semua laporan penting untuk Anda.</p>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-building mr-2"></i> Total BUMDes</h5>
                        <p class="card-text">Jumlah: <?= $total_bumdes ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-success text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-store mr-2"></i> Total Unit Usaha</h5>
                        <p class="card-text">Jumlah: <?= $total_unit_usaha ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-info text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users mr-2"></i> Total Anggota BUMDes</h5>
                        <p class="card-text">Jumlah: <?= $total_anggota ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-warning text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-cog mr-2"></i> Total Pengguna Sistem</h5>
                        <p class="card-text">Jumlah: <?= $total_pengguna ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-danger text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tag mr-2"></i> Total Pelanggan E-commerce</h5>
                        <p class="card-text">Jumlah: <?= $total_pelanggan ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-secondary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tie mr-2"></i> Total Penjual E-commerce</h5>
                        <p class="card-text">Jumlah: <?= $total_penjual ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-dark text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shipping-fast mr-2"></i> Total Kurir</h5>
                        <p class="card-text">Jumlah: <?= $total_kurir ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-box-open mr-2"></i> Total Produk Terdaftar</h5>
                        <p class="card-text">Jumlah: <?= $total_produk ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-success text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shopping-cart mr-2"></i> Total Pesanan</h5>
                        <p class="card-text">Jumlah: <?= $total_pesanan ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-info text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i> Total Pembayaran</h5>
                        <p class="card-text">Jumlah: <?= $total_pembayaran ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line mr-2"></i> Total Transaksi Keuangan</h5>
                        <p class="card-text">Jumlah: <?= $total_transaksi_keuangan ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-danger text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i> Pesanan Menunggu Pembayaran</h5>
                        <p class="card-text">Jumlah: <?= $pesanan_menunggu_pembayaran ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-warning text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clock mr-2"></i> Pembayaran Menunggu Konfirmasi</h5>
                        <p class="card-text">Jumlah: <?= $pembayaran_menunggu_konfirmasi ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-info text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-comments mr-2"></i> Ulasan Produk Baru (Pending)</h5>
                        <p class="card-text">Jumlah: <?= $ulasan_baru ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-danger text-white shadow"> <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-undo-alt mr-2"></i> Pengembalian Menunggu Persetujuan</h5>
                        <p class="card-text">Jumlah: <?= $pengembalian_menunggu ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Ringkasan Keuangan (Pendapatan & Pengeluaran)</h5>
                        <canvas id="keuanganChart"></canvas>
                        <hr>
                        <p class="card-text">Total Pendapatan: **Rp. <?= number_format($total_pendapatan, 0, ',', '.') ?>**</p>
                        <p class="card-text">Total Pengeluaran: **Rp. <?= number_format($total_pengeluaran, 0, ',', '.') ?>**</p>
                        <p class="card-text">**Keuntungan Bersih: Rp. <?= number_format($keuntungan_bersih, 0, ',', '.') ?>**</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Grafik Penjualan Bulanan (Pemasukan E-commerce)</h5>
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Ringkasan Status Pesanan</h5>
                        <canvas id="statusPesananChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Produk dengan Stok Rendah (Perhatian!)</h5>
                        <?php if (mysqli_num_rows($stok_rendah) > 0) : ?>
                            <ul class="list-group list-group-flush">
                                <?php while ($row_stok = mysqli_fetch_assoc($stok_rendah)) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($row_stok['nama']) ?>
                                        <span class="badge bg-danger text-white rounded-pill">Stok: <?= htmlspecialchars($row_stok['stok']) ?></span>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else : ?>
                            <p class="text-success">Semua produk memiliki stok yang aman.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
        $('.nav-link.collapsed').click(function() {
            var targetCollapse = $(this).attr('href');
            $(targetCollapse).collapse('toggle');
        });
    });

    // Grafik Penjualan Bulanan
    var bulan = <?php echo json_encode($bulan); ?>;
    var total_penjualan_arr = <?php echo json_encode($total_penjualan_arr); ?>;
    var ctxSales = document.getElementById('salesChart').getContext('2d');
    var mySalesChart = new Chart(ctxSales, {
        type: 'line', // Mengganti ke line chart untuk tren penjualan
        data: {
            labels: bulan.map(function(m){
                const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
                  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
                ];
                return monthNames[m - 1];
            }),
            datasets: [{
                label: 'Total Penjualan (Pemasukan E-commerce)',
                data: total_penjualan_arr,
                backgroundColor: 'rgba(255, 215, 0, 0.4)', // Kuning Emas Transparan
                borderColor: 'rgb(255, 215, 0)', // Kuning Emas Solid
                borderWidth: 2,
                fill: true, // Area di bawah garis akan terisi
                tension: 0.3 // Memberikan sedikit lengkungan pada garis
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp. ' + value.toLocaleString('id-ID'); // Format mata uang Indonesia
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp. ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

    // Grafik Ringkasan Status Pesanan (Pie Chart)
    var labelsStatusPesanan = <?php echo $labels_status_pesanan; ?>;
    var dataStatusPesanan = <?php echo $data_status_pesanan; ?>;
    var ctxStatusPesanan = document.getElementById('statusPesananChart').getContext('2d');
    var myStatusPesananChart = new Chart(ctxStatusPesanan, {
        type: 'pie', // Pie chart untuk ringkasan
        data: {
            labels: labelsStatusPesanan,
            datasets: [{
                label: 'Jumlah Pesanan',
                data: dataStatusPesanan,
                backgroundColor: [
                    'rgba(220, 20, 60, 0.7)', // Merah Lampung (Crimson)
                    'rgba(70, 130, 180, 0.7)', // Biru Lampung (Steel Blue)
                    'rgba(255, 215, 0, 0.7)', // Kuning Emas Lampung
                    'rgba(34, 139, 34, 0.7)', // Hijau Lampung (Forest Green)
                    'rgba(128, 0, 128, 0.7)', // Ungu (contoh untuk status lain)
                    'rgba(255, 165, 0, 0.7)'  // Oranye (contoh untuk status lain)
                ],
                borderColor: [
                    'rgb(220, 20, 60)',
                    'rgb(70, 130, 180)',
                    'rgb(255, 215, 0)',
                    'rgb(34, 139, 34)',
                    'rgb(128, 0, 128)',
                    'rgb(255, 165, 0)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed;
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Grafik Keuangan (Pemasukan vs Pengeluaran - Bar Chart)
    var labelsKeuangan = <?php echo $labels_keuangan; ?>;
    var dataKeuangan = <?php echo $data_keuangan; ?>;
    var ctxKeuangan = document.getElementById('keuanganChart').getContext('2d');
    var myKeuanganChart = new Chart(ctxKeuangan, {
        type: 'bar',
        data: {
            labels: labelsKeuangan,
            datasets: [{
                label: 'Jumlah',
                data: dataKeuangan,
                backgroundColor: [
                    'rgba(34, 139, 34, 0.7)', // Hijau Lampung (Forest Green) untuk Pemasukan
                    'rgba(220, 20, 60, 0.7)'  // Merah Lampung (Crimson) untuk Pengeluaran
                ],
                borderColor: [
                    'rgb(34, 139, 34)',
                    'rgb(220, 20, 60)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp. ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false // Sembunyikan legenda karena sudah jelas
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': Rp. ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>