<?php
session_start();
include('../koneksi/koneksi.php');

// Security check: redirect if user not logged in
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];

// Fetch user data for profile display
$query_user = "SELECT * FROM pengguna WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $query_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);

// --- Handle Profile Update (moved from general PHP block) ---
if (isset($_POST['simpan_profil'])) { // Changed name to avoid conflict with product 'simpan'
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password'];
    $foto = $user['foto']; // Keep existing photo by default

    // Handle photo upload
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../img/foto/';
        $foto_name = basename($_FILES['foto']['name']);
        $target_file = $upload_dir . $foto_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_extensions)) {
            // Delete old photo if it exists
            if ($user['foto'] && file_exists($upload_dir . $user['foto'])) {
                unlink($upload_dir . $user['foto']);
            }
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $foto_name;
            } else {
                $_SESSION['pesan_error'] = "Gagal mengunggah foto.";
            }
        } else {
            $_SESSION['pesan_error'] = "Ekstensi foto tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
        }
    }

    // Update user data in the database
    $update_query = "UPDATE pengguna SET nama = ?, username = ?, email = ?, password = ?, foto = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt_update, 'sssssi', $nama, $username, $email, $password, $foto, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        $_SESSION['pesan_sukses'] = "Profil berhasil diperbarui!";
        // Re-fetch user data after update to reflect changes
        $query_user = "SELECT * FROM pengguna WHERE id = ?";
        $stmt_user = mysqli_prepare($conn, $query_user);
        mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        $user = mysqli_fetch_assoc($result_user);
    } else {
        $_SESSION['pesan_error'] = "Gagal memperbarui profil: " . mysqli_error($conn);
    }
}
// --- End Handle Profile Update ---

// Pagination variables
$limit = 10; // Number of products per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Ambil total data barang untuk pagination
$query_total_barang = "SELECT COUNT(*) AS total FROM produk";
$result_total_barang = mysqli_query($conn, $query_total_barang);
$total_barang = mysqli_fetch_assoc($result_total_barang)['total'];
$total_pages = ceil($total_barang / $limit);

// Ambil data barang (produk) beserta kategori dengan pagination
$query_barang = "
    SELECT 
        produk.*, 
        penjual.nama_toko,
        kategori_produk.nama_kategori AS nama_kategori_produk
    FROM 
        produk 
    LEFT JOIN 
        penjual ON produk.penjual_id = penjual.pengguna_id 
    LEFT JOIN 
        kategori_produk ON produk.kategori_id = kategori_produk.id
    ORDER BY 
        produk.created_at DESC
    LIMIT ?, ?";
$stmt_barang = mysqli_prepare($conn, $query_barang);
mysqli_stmt_bind_param($stmt_barang, 'ii', $offset, $limit);
mysqli_stmt_execute($stmt_barang);
$result_barang = mysqli_stmt_get_result($stmt_barang);

if (!$result_barang) {
    die("Query gagal: " . mysqli_error($conn));
}

$barang = [];
while ($row = mysqli_fetch_assoc($result_barang)) {
    $barang[] = $row;
}

// Fetch all categories for the "Tambah Barang" and "Edit Barang" modals
$query_kategori = "SELECT * FROM kategori_produk ORDER BY nama_kategori ASC";
$result_kategori = mysqli_query($conn, $query_kategori);
$kategori_list = [];
while ($cat = mysqli_fetch_assoc($result_kategori)) {
    $kategori_list[] = $cat;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Produk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
        .btn-edit-purple { /* New class for the pastel purple edit button */
            background-color: #D1C4E9; /* Pastel purple */
            border-color: #D1C4E9;
            color: #343A40;
        }
        .btn-edit-purple:hover {
            background-color: #BCAFE0; /* Darker pastel purple on hover */
            border-color: #BCAFE0;
            color: #343A40;
        }
        .btn-warning { /* Retained for other warning buttons if any, but edit button uses custom class now */
            background-color: #FFD700; /* Standard gold for warning */
            border-color: #FFD700;
            color: #343A40;
        }
        .btn-warning:hover {
            background-color: #E6C200;
            border-color: #E6C200;
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
            color: #343A40;
            border-color: #BCAFE0;
        }
        .sidebar .nav-link.active {
            background-color: #CCEEF5; /* Sidebar Hover color for active link */
            color: #343A40;
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
            <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline" style="color: #343A40;">Profil</span> </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Profile">
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
                            <img src="../img/foto/<?= htmlspecialchars($user['foto']) ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_profil" class="btn btn-primary">Simpan</button>
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
                            <a class="nav-link submenu" href="pengguna.php?type=user">Data Pengguna</a>
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
                <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenKurir" role="button" aria-expanded="false" aria-controls="manajemenKurir">
                    <i class="fas fa-truck"></i>
                    <span class="ml-2">Manajemen Kurir</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="manajemenKurir">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="kurir.php">Daftar Kurir</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="kurir_persetujuan.php">Persetujuan Kurir</a>
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
                <a class="nav-link active collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="true" aria-controls="manajemenEcommerce">
                    <i class="fas fa-cart-shopping"></i>
                    <span class="ml-2">E-commerce</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="manajemenEcommerce">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="kategori_produk.php">Kategori Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu active" href="produk.php">Data Produk</a>
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
            <li class="nav-item">
                <a href="../logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="content">
        <h3 class="mb-4">Manajemen Produk</h3>

        <?php if (isset($_SESSION['pesan_sukses'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['pesan_sukses']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['pesan_sukses']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['pesan_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['pesan_error']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['pesan_error']); ?>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahBarangModal">
            <i class="fas fa-plus"></i> Tambah Barang
        </button>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Deskripsi</th>
                                <th>Gambar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($barang)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Tidak ada data produk.</td>
                                </tr>
                            <?php else: ?>
                                <?php $counter = ($page - 1) * $limit + 1; // Start counter from correct number ?>
                                <?php foreach ($barang as $item): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($item['nama']) ?></td>
                                    <td><?= htmlspecialchars($item['nama_kategori_produk'] ?? 'Tanpa Kategori') ?></td>
                                    <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                                    <td><?= $item['stok'] ?></td>
                                    <td><?= htmlspecialchars(substr($item['deskripsi'], 0, 50)) . (strlen($item['deskripsi']) > 50 ? '...' : '') ?></td>
                                    <td>
                                        <?php if($item['gambar']): ?>
                                        <img src="../img/barang/<?= htmlspecialchars($item['gambar']) ?>" width="50" class="img-thumbnail">
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-edit-purple" data-toggle="modal" data-target="#editBarangModal<?= $item['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus_barang.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editBarangModal<?= $item['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="update_barang.php" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Barang</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label>Nama Barang</label>
                                                        <input type="text" name="nama_barang" class="form-control" value="<?= htmlspecialchars($item['nama']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Kategori</label>
                                                        <select name="kategori_id" class="form-control">
                                                            <option value="">Pilih Kategori</option>
                                                            <?php foreach ($kategori_list as $cat): ?>
                                                                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $cat['id'] == $item['kategori_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Harga</label>
                                                        <input type="number" name="harga" class="form-control" value="<?= htmlspecialchars($item['harga']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Stok</label>
                                                        <input type="number" name="stok" class="form-control" value="<?= htmlspecialchars($item['stok']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Deskripsi</label>
                                                        <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($item['deskripsi']) ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Gambar</label>
                                                        <?php if($item['gambar']): ?>
                                                        <div class="mb-2">
                                                            <img src="../img/barang/<?= htmlspecialchars($item['gambar']) ?>" width="100">
                                                        </div>
                                                        <?php endif; ?>
                                                        <input type="file" name="gambar" class="form-control-file">
                                                        <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah gambar.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="update_barang" class="btn btn-primary">Simpan Perubahan</button>
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>

            </div>
        </div>
    </div>

    <div class="modal fade" id="tambahBarangModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="tambah_barang.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Barang</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori_id" class="form-control">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori_list as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Harga</label>
                            <input type="number" name="harga" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Stok</label>
                            <input type="number" name="stok" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Gambar</label>
                            <input type="file" name="gambar" class="form-control-file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="tambah_barang" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    </div>
                </form>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }

    // Optional: Close collapsed sidebar on larger screens if it was opened on mobile
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            $('#sidebar').removeClass('collapsed');
        }
    });

    // Handle dropdown toggle for sidebar (Bootstrap 4 requires jQuery)
    $('.sidebar .nav-link.collapsed').on('click', function() {
        $(this).find('.fa-caret-down').toggleClass('fa-rotate-180');
    });

    // Auto-hide alerts
    $(document).ready(function() {
        setTimeout(function() {
            $(".alert").alert('close');
        }, 5000); // Close after 5 seconds
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Your chart scripts here if you have any charts on this page.
</script>
</body>
</html>