<?php
session_start();
include('../koneksi/koneksi.php'); // Pastikan path ke koneksi.php benar

if (!isset($_SESSION['pengguna_id'])) {
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

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($user['role'] !== 'admin') {
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.');window.location.href='dashboard_admin.php';</script>";
    exit;
}

// Logika Persetujuan / Penolakan / Nonaktifkan Kurir
if (isset($_GET['action']) && isset($_GET['id'])) {
    $kurir_id = $_GET['id'];
    $new_status = '';
    $message = '';

    if ($_GET['action'] == 'setujui') {
        $new_status = 'aktif';
        $message = 'Kurir berhasil diaktifkan!';
    } elseif ($_GET['action'] == 'tolak') {
        $new_status = 'nonaktif'; // Menolak bisa berarti menonaktifkan
        $message = 'Kurir berhasil ditolak/dinonaktifkan!';
    } elseif ($_GET['action'] == 'nonaktifkan') {
        $new_status = 'nonaktif';
        $message = 'Kurir berhasil dinonaktifkan!';
    } elseif ($_GET['action'] == 'aktifkan') {
        $new_status = 'aktif';
        $message = 'Kurir berhasil diaktifkan kembali!';
    }

    if (!empty($new_status)) {
        $update_query = "UPDATE kurir SET status = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt_update, 'si', $new_status, $kurir_id);

        if (mysqli_stmt_execute($stmt_update)) {
            echo "<script>alert('" . $message . "');window.location.href='kurir_persetujuan.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui status kurir: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Logika Hapus Kurir (opsional, jika ingin bisa langsung hapus dari sini)
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    // Ambil nama file foto sebelum dihapus dari database
    $get_foto_query = "SELECT foto FROM kurir WHERE id = ?";
    $stmt_get_foto = mysqli_prepare($conn, $get_foto_query);
    mysqli_stmt_bind_param($stmt_get_foto, 'i', $id_hapus);
    mysqli_stmt_execute($stmt_get_foto);
    $result_get_foto = mysqli_stmt_get_result($stmt_get_foto);
    $kurir_data = mysqli_fetch_assoc($result_get_foto);

    if ($kurir_data) { // Pastikan data kurir ditemukan
        $delete_query = "DELETE FROM kurir WHERE id=?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);

        if (mysqli_stmt_execute($stmt_delete)) {
            // Hapus file foto dari server jika ada
            if ($kurir_data['foto'] && file_exists('../img/kurir/' . $kurir_data['foto'])) {
                unlink('../img/kurir/' . $kurir_data['foto']);
            }
            echo "<script>alert('Kurir berhasil dihapus!');window.location.href='kurir_persetujuan.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus kurir: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('Kurir tidak ditemukan.');window.location.href='kurir_persetujuan.php';</script>";
    }
}

// Logika Pagination untuk Kurir Menunggu Persetujuan
$limit_pending = 5; // Jumlah data per halaman untuk pending kurir
$page_pending = isset($_GET['page_pending']) && is_numeric($_GET['page_pending']) ? (int)$_GET['page_pending'] : 1;
$start_pending = ($page_pending - 1) * $limit_pending;

// Hitung total data kurir 'pending'
$sql_total_pending = "SELECT COUNT(id) AS total FROM kurir WHERE status = 'pending'";
$result_total_pending = mysqli_query($conn, $sql_total_pending);
$row_total_pending = mysqli_fetch_assoc($result_total_pending);
$total_kurir_pending = $row_total_pending['total'];
$total_pages_pending = ceil($total_kurir_pending / $limit_pending);

// Mengambil data kurir 'pending' dengan pagination
$sql_pending_kurir = "SELECT * FROM kurir WHERE status = 'pending' LIMIT ?, ?";
$stmt_pending_kurir = mysqli_prepare($conn, $sql_pending_kurir);
mysqli_stmt_bind_param($stmt_pending_kurir, 'ii', $start_pending, $limit_pending);
mysqli_stmt_execute($stmt_pending_kurir);
$result_pending_kurir = mysqli_stmt_get_result($stmt_pending_kurir);


// Logika Pagination untuk Kurir Aktif / Nonaktif
$limit_processed = 10; // Jumlah data per halaman untuk aktif/nonaktif kurir
$page_processed = isset($_GET['page_processed']) && is_numeric($_GET['page_processed']) ? (int)$_GET['page_processed'] : 1;
$start_processed = ($page_processed - 1) * $limit_processed;

// Hitung total data kurir 'aktif' atau 'nonaktif'
$sql_total_processed = "SELECT COUNT(id) AS total FROM kurir WHERE status IN ('aktif', 'nonaktif')";
$result_total_processed = mysqli_query($conn, $sql_total_processed);
$row_total_processed = mysqli_fetch_assoc($result_total_processed);
$total_kurir_processed = $row_total_processed['total'];
$total_pages_processed = ceil($total_kurir_processed / $limit_processed);

// Mengambil data kurir 'aktif' atau 'nonaktif' dengan pagination
$sql_processed_kurir = "SELECT * FROM kurir WHERE status IN ('aktif', 'nonaktif') ORDER BY status DESC LIMIT ?, ?";
$stmt_processed_kurir = mysqli_prepare($conn, $sql_processed_kurir);
mysqli_stmt_bind_param($stmt_processed_kurir, 'ii', $start_processed, $limit_processed);
mysqli_stmt_execute($stmt_processed_kurir);
$result_processed_kurir = mysqli_stmt_get_result($stmt_processed_kurir);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Persetujuan Kurir</title>
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
            background-color: #F8FBFD; /* Latar Belakang Utama: Very Light Blueish White */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #343A40; /* Teks pada Latar Belakang Terang: Dark Grayish Black */
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
            background-color: #E0F2F7; /* Sidebar & Navbar: Soft Sky Blue */
            min-height: 100vh;
            padding: 20px 0;
            color: #343A40; /* Teks pada Latar Belakang Gelap (Sidebar): Dark Grayish Black */
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: #343A40; /* Teks: Dark Grayish Black */
            margin-bottom: 30px;
        }
        .sidebar a {
            color: #343A40; /* Teks: Dark Grayish Black untuk link */
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #CCEEF5; /* Sidebar Hover: Slightly Darker Soft Sky Blue */
            color: #343A40; /* Teks: Dark Grayish Black untuk hover text */
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #343A40; /* Teks: Dark Grayish Black untuk submenu text */
        }
        .sidebar .submenu:hover {
            color: #343A40; /* Teks: Dark Grayish Black untuk submenu hover text */
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
            color: #343A40; /* Teks: Dark Grayish Black untuk toggle button */
            margin-left: 20px;
            font-size: 20px;
        }

        .card-dashboard {
            border-radius: 10px;
            /* Default card background, will be overridden by specific classes */
            background-color: #F8FBFD; /* Latar Belakang Utama: Very Light Blueish White */
            color: #343A40; /* Teks: Dark Grayish Black text */
        }
        .card {
            background-color: #F0F4F7; /* Kartu (Aksen Putih Kebiruan) */
            color: #343A40; /* Teks pada Latar Belakang Terang: Dark Grayish Black */
            border: none;
            border-radius: 8px;
        }
        .card-header {
            background-color: #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) */
            color: #343A40; /* Teks pada Latar Belakang Gelap (card-header): Dark Grayish Black */
            font-weight: bold;
        }

        .btn-primary {
            background-color: #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) */
            border-color: #A7D9ED;
            color: #343A40;
        }
        .btn-primary:hover {
            background-color: #8FD1E8; /* Sedikit lebih gelap dari Light Cerulean */
            border-color: #8FD1E8;
            color: #343A40;
        }
        .btn-warning {
            background-color: #FFD700; /* Standard gold for warning */
            border-color: #FFD700;
            color: #343A40;
        }
        .btn-warning:hover {
            background-color: #E6C200;
            border-color: #E6C200;
        }
        .btn-success { /* Added for pagination active state */
            background-color: #28a745; /* Bootstrap default green */
            border-color: #28a745;
            color: #fff;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-danger {
            background-color: #FF6347; /* Standard tomato for danger */
            border-color: #FF6347;
            color: #F8FBFD; /* Very Light Blueish White untuk kontras */
        }
        .btn-danger:hover {
            background-color: #E0523C;
            border-color: #E0523C;
        }
        .btn-secondary {
            background-color: #D1C4E9; /* Kartu (Aksen Ungu Pastel) */
            border-color: #D1C4E9;
            color: #343A40;
        }
        .btn-secondary:hover {
            background-color: #BCAFE0;
            border-color: #BCAFE0;
        }
        .form-control {
            background-color: #FFFFFF; /* Putih bersih untuk input */
            border-color: #A7D9ED; /* Border biru pastel */
            color: #343A40; /* Teks input Dark Grayish Black */
        }
        .form-control:focus {
            border-color: #8FD1E8; /* Border sedikit lebih gelap saat fokus */
            box-shadow: 0 0 0 0.25rem rgba(167, 217, 237, 0.25); /* Shadow biru pastel */
        }
        .table thead th {
            background-color: #E0F2F7; /* Biru pastel lembut untuk header tabel */
            color: #343A40;
            border-bottom: 2px solid #A7D9ED; /* Border biru pastel */
        }
        .table tbody tr:nth-of-type(odd) {
            background-color: #F8FBFD; /* Putih kebiruan untuk baris ganjil */
        }
        .table tbody tr:nth-of-type(even) {
            background-color: #F0F4F7; /* Putih kebiruan yang sedikit lebih gelap untuk baris genap */
        }

        .modal-content {
            background-color: #F8FBFD; /* Background modal: Very Light Blueish White */
            color: #343A40; /* Teks modal: Dark Grayish Black */
        }
        .modal-header {
            background-color: #E0F2F7; /* Header modal: Soft Sky Blue */
            border-bottom: 1px solid #CCEEF5;
        }
        .modal-title {
            color: #343A40; /* Title modal: Dark Grayish Black */
        }
        .modal-footer {
            border-top: 1px solid #CCEEF5;
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
            background: #F8FBFD; /* Background: Very Light Blueish White */
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            color: #343A40; /* Text: Dark Grayish Black */
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
            color: #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) for links */
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: #F8FBFD; /* Background: Very Light Blueish White */
            padding: 20px;
            border-radius: 10px;
            color: #343A40; /* Text: Dark Grayish Black */
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .custom-card {
        height: 80px;         /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;   /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;     /* jarak luar card */
        }
        #salesChart { /* Ini mungkin tidak digunakan di halaman ini, tapi tetap jaga konsistensi */
            width: 100% !important;
            max-width: 600px !important;
            height: 300px !important;
        }
        .alert-info {
            background-color: #D1C4E9; /* Ungu pastel */
            color: #343A40; /* Teks Dark Grayish Black */
            border-color: #BCAFE0;
        }
        .sidebar .nav-link.active {
            background-color: #CCEEF5; /* Sidebar Hover color for active link */
            color: #343A40;
        }
        /* Styles for pagination links */
        .pagination .page-item .page-link {
            color: #343A40; /* Warna teks link pagination */
        }
        .pagination .page-item.active .page-link {
            background-color: #A7D9ED; /* Warna background untuk halaman aktif */
            border-color: #A7D9ED;
            color: #343A40; /* Warna teks untuk halaman aktif */
        }
        .pagination .page-item.disabled .page-link {
            color: #6C757D; /* Warna teks untuk link pagination yang dinonaktifkan */
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background-color: #E0F2F7;"> <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline" style="color: #343A40;">Profil</span> </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                <div class="text-left">
                <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                <a class="btn btn-link text-danger p-0 d-block" href="../logout.php">Logout</a>
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
                        <span>&times;</span>
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
            <li class="nav-item">
                <a class="nav-link" href="dashboard_admin.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenBUMDes" role="button" aria-expanded="false" aria-controls="manajemenBUMDes">
        <i class="fas fa-building-columns"></i>
        <span class="ml-2">Manajemen BUMDes</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="manajemenBUMDes">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="manajemen.php?type=informasi">Informasi BUMDes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="manajemen.php?type=unit">Unit Usaha</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPengguna" role="button" aria-expanded="false" aria-controls="manajemenPengguna">
        <i class="fas fa-user-gear"></i>
        <span class="ml-2">Manajemen Pengguna</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="manajemenPengguna">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="pengguna.php?type=admin">Data Admin</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pengguna.php?type=penjual_pelanggan">Data Pegguna</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPelanggan" role="button" aria-expanded="false" aria-controls="manajemenPelanggan">
        <i class="fas fa-user-friends"></i>
        <span class="ml-2">Manajemen Pelanggan</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="manajemenPelanggan">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="pelanggan.php">Daftar Pelanggan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pelanggan_persetujuan.php">Persetujuan Pelanggan</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenPenjual" role="button" aria-expanded="false" aria-controls="manajemenPenjual">
        <i class="fas fa-store"></i>
        <span class="ml-2">Manajemen Penjual</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="manajemenPenjual">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="penjual.php">Daftar Penjual</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="penjual_persetujuan.php">Persetujuan Penjual</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenKurir" role="button" aria-expanded="true" aria-controls="manajemenKurir">
        <i class="fas fa-truck"></i>
        <span class="ml-2">Manajemen Kurir</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse show" id="manajemenKurir">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="kurir.php">Daftar Kurir</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu active" href="kurir_persetujuan.php">Persetujuan Kurir</a>
            </li>
        </ul>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link" href="anggota.php">
        <i class="fas fa-people-group"></i>
        <span class="ml-2">Manajemen Anggota</span>
    </a>
</li>
<li class="nav-item">
  <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="false" aria-controls="manajemenEcommerce">
    <i class="fas fa-shopping-cart"></i>
    <span class="ml-2">E-commerce</span>
    <i class="fas fa-caret-down float-right"></i>
  </a>
  <div class="collapse" id="manajemenEcommerce">
    <ul class="nav flex-column pl-4">
      <li class="nav-item">
        <a class="nav-link submenu" href="kategori_produk.php">Kategori Produk</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="produk.php">Data Produk</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="pesanan.php">Data Pesanan</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="pembayaran.php">Data Pembayaran</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="pengiriman.php">Data Pengiriman</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="ulasan_produk.php">Ulasan Produk</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="diskon.php">Diskon</a>
      </li>
      <li class="nav-item">
        <a class="nav-link submenu" href="wishlist.php">Wishlist</a>
      </li>
    </ul>
  </div>
</li>
<li class="nav-item">
  <a class="nav-link" href="pengembalian_barang.php">
    <i class="fas fa-undo-alt"></i>
    <span class="ml-2">Pengembalian Barang</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link" href="artikel.php">
    <i class="fas fa-newspaper"></i>
    <span class="ml-2">Manajemen Artikel</span>
  </a>
</li>

        <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>
    <div class="content">
        <h2 class="mb-4">Persetujuan Kurir Baru</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Kurir Menunggu Persetujuan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Nomor Telepon</th>
                                <th>Alamat</th>
                                <th>Area Pengiriman</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no_pending = $start_pending + 1; // Sesuaikan nomor urut untuk pagination
                            if (mysqli_num_rows($result_pending_kurir) > 0) {
                                while ($row = mysqli_fetch_assoc($result_pending_kurir)) {
                            ?>
                                    <tr>
                                        <td><?= $no_pending++; ?></td>
                                        <td>
                                            <?php if ($row['foto']) : ?>
                                                <img src="../img/kurir/<?= $row['foto'] ?>" alt="Foto Kurir" width="50" class="rounded-circle">
                                            <?php else : ?>
                                                <img src="../img/kurir/default.png" alt="Foto Default" width="50" class="rounded-circle">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['nama']); ?></td>
                                        <td><?= htmlspecialchars($row['username']); ?></td>
                                        <td><?= htmlspecialchars($row['nomor_telepon'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($row['alamat'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($row['area_pengiriman'] ?: '-'); ?></td>
                                        <td><span class="badge badge-warning"><?= htmlspecialchars(ucfirst($row['status'])); ?></span></td>
                                        <td>
                                            <a href="kurir_persetujuan.php?action=setujui&id=<?= $row['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-success" onclick="return confirm('Aktifkan kurir ini?');">Setujui</a>
                                            <a href="kurir_persetujuan.php?action=tolak&id=<?= $row['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-danger mt-1" onclick="return confirm('Tolak kurir ini? Akun akan dinonaktifkan.');">Tolak</a>
                                            <a href="kurir_persetujuan.php?action=hapus&id=<?= $row['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-secondary mt-1" onclick="return confirm('Hapus kurir ini secara permanen?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center'>Tidak ada kurir yang menunggu persetujuan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation for pending couriers">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page_pending <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page_pending <= 1) ? '#' : '?page_pending=' . ($page_pending - 1) . '&page_processed=' . $page_processed; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_pending; $i++) : ?>
                            <li class="page-item <?= ($page_pending == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page_pending=<?= $i; ?>&page_processed=<?= $page_processed; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page_pending >= $total_pages_pending) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page_pending >= $total_pages_pending) ? '#' : '?page_pending=' . ($page_pending + 1) . '&page_processed=' . $page_processed; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="card shadow-sm mt-5">
            <div class="card-header">
                Kurir Aktif / Nonaktif
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Nomor Telepon</th>
                                <th>Area Pengiriman</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no_processed = $start_processed + 1; // Sesuaikan nomor urut untuk pagination
                            if (mysqli_num_rows($result_processed_kurir) > 0) {
                                while ($row_p = mysqli_fetch_assoc($result_processed_kurir)) {
                                    $badge_class = ($row_p['status'] == 'aktif') ? 'badge-success' : 'badge-danger';
                            ?>
                                    <tr>
                                        <td><?= $no_processed++; ?></td>
                                        <td>
                                            <?php if ($row_p['foto']) : ?>
                                                <img src="../img/kurir/<?= $row_p['foto'] ?>" alt="Foto Kurir" width="50" class="rounded-circle">
                                            <?php else : ?>
                                                <img src="../img/kurir/default.png" alt="Foto Default" width="50" class="rounded-circle">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row_p['nama']); ?></td>
                                        <td><?= htmlspecialchars($row_p['username']); ?></td>
                                        <td><?= htmlspecialchars($row_p['nomor_telepon'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($row_p['area_pengiriman'] ?: '-'); ?></td>
                                        <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($row_p['status'])); ?></span></td>
                                        <td>
                                            <?php if ($row_p['status'] == 'nonaktif') : ?>
                                                <a href="kurir_persetujuan.php?action=aktifkan&id=<?= $row_p['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-success" onclick="return confirm('Aktifkan kembali kurir ini?');">Aktifkan</a>
                                            <?php elseif ($row_p['status'] == 'aktif') : ?>
                                                <a href="kurir_persetujuan.php?action=nonaktifkan&id=<?= $row_p['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Nonaktifkan kurir ini?');">Nonaktifkan</a>
                                            <?php endif; ?>
                                            <a href="kurir_persetujuan.php?action=hapus&id=<?= $row_p['id']; ?>&page_pending=<?= $page_pending; ?>&page_processed=<?= $page_processed; ?>" class="btn btn-sm btn-secondary mt-1" onclick="return confirm('Hapus kurir ini secara permanen?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center'>Tidak ada kurir aktif atau nonaktif.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation for processed couriers">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page_processed <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page_processed <= 1) ? '#' : '?page_processed=' . ($page_processed - 1) . '&page_pending=' . $page_pending; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_processed; $i++) : ?>
                            <li class="page-item <?= ($page_processed == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page_processed=<?= $i; ?>&page_pending=<?= $page_pending; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page_processed >= $total_pages_processed) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page_processed >= $total_pages_processed) ? '#' : '?page_processed=' . ($page_processed + 1) . '&page_pending=' . $page_pending; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>
<footer class="text-center py-3 mt-auto" style="background-color: #E0F2F7; color: #343A40; position: relative; bottom: 0; width: 100%;"> <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. | 
        <a href="https://www.bumdes.id" style="color: #343A40;">www.bumdes.id</a></small> </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }
</script>
</body>
</html>