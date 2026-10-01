<?php
session_start();
include('../koneksi/koneksi.php');

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
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='manajemen.php?type=" . ($_GET['type'] ?? 'informasi') . "';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// Konfigurasi Pagination
$limit = 5; // Jumlah item per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Logika untuk Informasi BUMDes (menggunakan tabel 'bumdes')
if (isset($_GET['type']) && $_GET['type'] == 'informasi') {
    // Tambah Informasi BUMDes
    if (isset($_POST['tambah_informasi'])) {
        $nama_bumdes = $_POST['nama_bumdes'];
        $alamat = $_POST['alamat'];
        $tahun_berdiri = $_POST['tahun_berdiri'];
        $visi = $_POST['visi'];
        $misi = $_POST['misi'];
        $kontak = $_POST['kontak'];
        $logo = null;

        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../img/logo/'; // Direktori untuk logo BUMDes
            $logo_name = basename($_FILES['logo']['name']);
            $target_logo = $upload_dir . $logo_name;
            $ext_logo = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));
            $allowed_logo = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($ext_logo, $allowed_logo)) {
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_logo)) {
                    $logo = $logo_name;
                } else {
                    echo "<script>alert('Gagal mengunggah logo.');</script>";
                }
            } else {
                echo "<script>alert('Jenis file logo tidak diizinkan (hanya jpg, jpeg, png, gif).');</script>";
            }
        } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            echo "<script>alert('Terjadi error saat mengunggah logo: " . $_FILES['logo']['error'] . "');</script>";
        }

        $insert_informasi = "INSERT INTO bumdes (nama, alamat, tahun_berdiri, visi, misi, logo, kontak, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt_informasi = mysqli_prepare($conn, $insert_informasi);
        mysqli_stmt_bind_param($stmt_informasi, 'sssssss', $nama_bumdes, $alamat, $tahun_berdiri, $visi, $misi, $logo, $kontak);
        if (mysqli_stmt_execute($stmt_informasi)) {
            echo "<script>alert('Informasi BUMDes berhasil ditambahkan');window.location.href='manajemen.php?type=informasi';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan informasi BUMDes: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Edit Informasi BUMDes
    if (isset($_POST['edit_informasi'])) {
        $id = $_POST['id_informasi'];
        $nama_bumdes = $_POST['nama_bumdes'];
        $alamat = $_POST['alamat'];
        $tahun_berdiri = $_POST['tahun_berdiri'];
        $visi = $_POST['visi'];
        $misi = $_POST['misi'];
        $kontak = $_POST['kontak'];

        // Ambil data bumdes saat ini untuk logo
        $current_bumdes_query = "SELECT logo FROM bumdes WHERE id = ?";
        $stmt_current_bumdes = mysqli_prepare($conn, $current_bumdes_query);
        mysqli_stmt_bind_param($stmt_current_bumdes, 'i', $id);
        mysqli_stmt_execute($stmt_current_bumdes);
        $result_current_bumdes = mysqli_stmt_get_result($stmt_current_bumdes);
        $current_bumdes = mysqli_fetch_assoc($result_current_bumdes);
        $logo = $current_bumdes['logo']; // Logo default adalah logo yang sudah ada

        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../img/logo/';
            $logo_name = basename($_FILES['logo']['name']);
            $target_logo = $upload_dir . $logo_name;
            $ext_logo = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));
            $allowed_logo = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($ext_logo, $allowed_logo)) {
                // Hapus logo lama jika ada dan berbeda dengan yang baru
                if ($logo && file_exists($upload_dir . $logo) && $logo != $logo_name) {
                    unlink($upload_dir . $logo);
                }
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_logo)) {
                    $logo = $logo_name;
                } else {
                    echo "<script>alert('Gagal mengunggah logo baru.');</script>";
                }
            } else {
                echo "<script>alert('Jenis file logo tidak diizinkan (hanya jpg, jpeg, png, gif).');</script>";
            }
        } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            echo "<script>alert('Terjadi error saat mengunggah logo: " . $_FILES['logo']['error'] . "');</script>";
        }

        $update_informasi = "UPDATE bumdes SET nama=?, alamat=?, tahun_berdiri=?, visi=?, misi=?, logo=?, kontak=?, updated_at=NOW() WHERE id=?";
        $stmt_informasi = mysqli_prepare($conn, $update_informasi);
        mysqli_stmt_bind_param($stmt_informasi, 'sssssssi', $nama_bumdes, $alamat, $tahun_berdiri, $visi, $misi, $logo, $kontak, $id);
        if (mysqli_stmt_execute($stmt_informasi)) {
            echo "<script>alert('Informasi BUMDes berhasil diperbarui');window.location.href='manajemen.php?type=informasi';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui informasi BUMDes: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Hapus Informasi BUMDes
    if (isset($_GET['action']) && $_GET['action'] == 'hapus_informasi' && isset($_GET['id'])) {
        $id = $_GET['id'];
        // Ambil nama file logo sebelum dihapus dari database
        $get_logo_query = "SELECT logo FROM bumdes WHERE id = ?";
        $stmt_get_logo = mysqli_prepare($conn, $get_logo_query);
        mysqli_stmt_bind_param($stmt_get_logo, 'i', $id);
        mysqli_stmt_execute($stmt_get_logo);
        $result_get_logo = mysqli_stmt_get_result($stmt_get_logo);
        $bumdes_data = mysqli_fetch_assoc($result_get_logo);

        $delete_informasi = "DELETE FROM bumdes WHERE id=?";
        $stmt_informasi = mysqli_prepare($conn, $delete_informasi);
        mysqli_stmt_bind_param($stmt_informasi, 'i', $id);
        if (mysqli_stmt_execute($stmt_informasi)) {
            // Hapus file logo dari server jika ada
            if ($bumdes_data['logo'] && file_exists('../img/logo/' . $bumdes_data['logo'])) {
                unlink('../img/logo/' . $bumdes_data['logo']);
            }
            echo "<script>alert('Informasi BUMDes berhasil dihapus');window.location.href='manajemen.php?type=informasi';</script>";
        } else {
            echo "<script>alert('Gagal menghapus informasi BUMDes: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Query untuk pagination Informasi BUMDes
    $sql_total_informasi = "SELECT COUNT(*) AS total FROM bumdes";
    $result_total_informasi = mysqli_query($conn, $sql_total_informasi);
    $row_total_informasi = mysqli_fetch_assoc($result_total_informasi);
    $total_informasi = $row_total_informasi['total'];
    $total_pages_informasi = ceil($total_informasi / $limit);

    $sql_informasi = "SELECT * FROM bumdes LIMIT $start, $limit";
    $result_informasi = mysqli_query($conn, $sql_informasi);
}

// Logika untuk Unit Usaha (tetap menggunakan tabel 'unit_usaha')
if (isset($_GET['type']) && $_GET['type'] == 'unit') {
    // Tambah Unit Usaha
    if (isset($_POST['tambah_unit'])) {
        $id_bumdes = $_POST['bumdes_id'];
        $nama_unit = $_POST['nama'];
        $deskripsi_unit = $_POST['deskripsi'];
        $jenis_usaha = $_POST['jenis_usaha']; // Perhatikan: nama input di form adalah 'jenis_usaha'

        $insert_unit = "INSERT INTO unit_usaha (bumdes_id, nama, deskripsi, slug) VALUES (?, ?, ?, ?)";
        $stmt_unit = mysqli_prepare($conn, $insert_unit);
        mysqli_stmt_bind_param($stmt_unit, 'isss', $id_bumdes, $nama_unit, $deskripsi_unit, $jenis_usaha);
        if (mysqli_stmt_execute($stmt_unit)) {
            echo "<script>alert('Unit Usaha berhasil ditambahkan');window.location.href='manajemen.php?type=unit';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan unit usaha: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Edit Unit Usaha
    if (isset($_POST['edit_unit'])) {
        $id = $_POST['id_unit'];
        $id_bumdes = $_POST['bumdes_id'];
        $nama_unit = $_POST['nama'];
        $deskripsi_unit = $_POST['deskripsi'];
        $jenis_usaha = $_POST['slug'];

        $update_unit = "UPDATE unit_usaha SET bumdes_id=?, nama=?, deskripsi=?, slug=? WHERE id=?";
        $stmt_unit = mysqli_prepare($conn, $update_unit);
        mysqli_stmt_bind_param($stmt_unit, 'isssi', $id_bumdes, $nama_unit, $deskripsi_unit, $jenis_usaha, $id);
        if (mysqli_stmt_execute($stmt_unit)) {
            echo "<script>alert('Unit Usaha berhasil diperbarui');window.location.href='manajemen.php?type=unit';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui unit usaha: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Hapus Unit Usaha
    if (isset($_GET['action']) && $_GET['action'] == 'hapus_unit' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $delete_unit = "DELETE FROM unit_usaha WHERE id=?";
        $stmt_unit = mysqli_prepare($conn, $delete_unit);
        mysqli_stmt_bind_param($stmt_unit, 'i', $id);
        if (mysqli_stmt_execute($stmt_unit)) {
            echo "<script>alert('Unit Usaha berhasil dihapus');window.location.href='manajemen.php?type=unit';</script>";
        } else {
            echo "<script>alert('Gagal menghapus unit usaha: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Query untuk pagination Unit Usaha
    $sql_total_unit = "SELECT COUNT(*) AS total FROM unit_usaha";
    $result_total_unit = mysqli_query($conn, $sql_total_unit);
    $row_total_unit = mysqli_fetch_assoc($result_total_unit);
    $total_unit = $row_total_unit['total'];
    $total_pages_unit = ceil($total_unit / $limit);

    $sql_unit = "SELECT u.*, b.nama AS nama_bumdes FROM unit_usaha u JOIN bumdes b ON u.bumdes_id = b.id LIMIT $start, $limit";
    $result_unit = mysqli_query($conn, $sql_unit);

    // Mengambil data dari tabel `bumdes` untuk dropdown di modal tambah/edit unit usaha
    $sql_bumdes_option = "SELECT id, nama FROM bumdes";
    $result_bumdes_option = mysqli_query($conn, $sql_bumdes_option);
    $bumdes_options = [];
    while ($option = mysqli_fetch_assoc($result_bumdes_option)) {
        $bumdes_options[] = $option;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen BUMDes dan Unit Usaha</title>
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
            width: 280px; /* Lebar sidebar disinkronkan */
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
        .content.ml-collapsed { /* Tambahkan ini untuk sinkronisasi */
            margin-left: 80px;
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
<nav class="navbar navbar-expand-lg" style="background-color: #E0F2F7;">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#" style="color: #343A40;">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline" style="color: #343A40;">Profil</span>
        </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Profile">
                </div>
                <h5 class="mb-1" style="color: #343A40;"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small" style="color: #343A40;">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small" style="color: #343A40;">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                <a class="btn btn-link p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal" style="color: #A7D9ED;">Edit Profil</a>
                <a class="btn btn-link p-0 d-block" href="../logout.php" style="color: #FF6347;">Logout</a>
                </div>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #F8FBFD; color: #343A40;">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel" style="color: #343A40;">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="color: #343A40;">Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label style="color: #343A40;">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label style="color: #343A40;">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label style="color: #343A40;">Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label style="color: #343A40;">Foto Profil</label><br>
                        <?php if ($user['foto']) : ?>
                            <img src="../img/foto/<?= $user['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file" style="color: #343A40;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn" style="background-color: #A7D9ED; color: #343A40;">Simpan</button>
                    <button type="button" class="btn" data-dismiss="modal" style="background-color: #D1C4E9; color: #343A40;">Batal</button>
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
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenBUMDes" role="button" aria-expanded="true" aria-controls="manajemenBUMDes">
        <i class="fas fa-building-columns"></i>
        <span class="ml-2">Manajemen BUMDes</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse show" id="manajemenBUMDes">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu <?= ($_GET['type'] ?? '') == 'informasi' ? 'active' : '' ?>" href="manajemen.php?type=informasi">Informasi BUMDes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu <?= ($_GET['type'] ?? '') == 'unit' ? 'active' : '' ?>" href="manajemen.php?type=unit">Unit Usaha</a>
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

        <a href="../logout.php" class="text-danger" style="color: #FF6347 !important;"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>
<div class="content">
    <?php if (isset($_GET['type']) && $_GET['type'] == 'informasi') : ?>
        <h2 class="mb-4" style="color: #343A40;">Manajemen Informasi BUMDes</h2>
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahInformasiModal">Tambah Informasi BUMDes</button>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Informasi BUMDes
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama BUMDes</th>
                                <th>Alamat</th>
                                <th>Tahun Berdiri</th>
                                <th>Visi</th>
                                <th>Misi</th>
                                <th>Logo</th>
                                <th>Kontak</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $start + 1; // Sesuaikan nomor awal dengan halaman saat ini
                            if (mysqli_num_rows($result_informasi) > 0) {
                                while ($row = mysqli_fetch_assoc($result_informasi)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['nama']); ?></td>
                                        <td><?= htmlspecialchars($row['alamat']); ?></td>
                                        <td><?= htmlspecialchars($row['tahun_berdiri']); ?></td>
                                        <td><?= htmlspecialchars($row['visi']); ?></td>
                                        <td><?= htmlspecialchars($row['misi']); ?></td>
                                        <td>
                                            <?php if ($row['logo']) : ?>
                                                <img src="../img/logo/<?= $row['logo'] ?>" alt="Logo BUMDes" width="50">
                                            <?php else : ?>
                                                Tidak ada logo
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['kontak']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#editInformasiModal<?= $row['id']; ?>">Edit</button>
                                            <a href="manajemen.php?type=informasi&action=hapus_informasi&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus informasi ini? Ini juga akan menghapus unit usaha terkait!');">Hapus</a>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editInformasiModal<?= $row['id']; ?>" tabindex="-1" aria-labelledby="editInformasiModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="manajemen.php?type=informasi" method="POST" enctype="multipart/form-data">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editInformasiModalLabel<?= $row['id']; ?>" style="color: #343A40;">Edit Informasi BUMDes</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_informasi" value="<?= $row['id']; ?>">
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Nama BUMDes</label>
                                                            <input type="text" name="nama_bumdes" class="form-control" value="<?= htmlspecialchars($row['nama']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Alamat</label>
                                                            <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($row['alamat']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Tahun Berdiri</label>
                                                            <input type="number" name="tahun_berdiri" class="form-control" value="<?= htmlspecialchars($row['tahun_berdiri']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Visi</label>
                                                            <textarea name="visi" class="form-control" rows="3"><?= htmlspecialchars($row['visi']); ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Misi</label>
                                                            <textarea name="misi" class="form-control" rows="3"><?= htmlspecialchars($row['misi']); ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Kontak</label>
                                                            <input type="text" name="kontak" class="form-control" value="<?= htmlspecialchars($row['kontak']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Logo BUMDes</label><br>
                                                            <?php if ($row['logo']) : ?>
                                                                <img src="../img/logo/<?= $row['logo'] ?>" width="80" class="mb-2 rounded"><br>
                                                            <?php endif; ?>
                                                            <input type="file" name="logo" class="form-control-file">
                                                            <small class="form-text text-muted" style="color: #343A40;">Kosongkan jika tidak ingin mengubah logo.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="edit_informasi" class="btn btn-primary">Simpan Perubahan</button>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center'>Tidak ada informasi BUMDes.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation for Informasi BUMDes">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page > 1) ? 'manajemen.php?type=informasi&page=' . ($page - 1) : '#'; ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_informasi; $i++) : ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="manajemen.php?type=informasi&page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages_informasi) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page < $total_pages_informasi) ? 'manajemen.php?type=informasi&page=' . ($page + 1) : '#'; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="modal fade" id="tambahInformasiModal" tabindex="-1" aria-labelledby="tambahInformasiModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="manajemen.php?type=informasi" method="POST" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tambahInformasiModalLabel" style="color: #343A40;">Tambah Informasi BUMDes</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="color: #343A40;">Nama BUMDes</label>
                                <input type="text" name="nama_bumdes" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Alamat</label>
                                <input type="text" name="alamat" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Tahun Berdiri</label>
                                <input type="number" name="tahun_berdiri" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Visi</label>
                                <textarea name="visi" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Misi</label>
                                <textarea name="misi" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Kontak</label>
                                <input type="text" name="kontak" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Logo BUMDes</label>
                                <input type="file" name="logo" class="form-control-file">
                                <small class="form-text text-muted" style="color: #343A40;">Opsional. Format: JPG, JPEG, PNG, GIF.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="tambah_informasi" class="btn btn-primary">Tambah</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif (isset($_GET['type']) && $_GET['type'] == 'unit') : ?>
        <h2 class="mb-4" style="color: #343A40;">Manajemen Unit Usaha</h2>
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahUnitUsahaModal">Tambah Unit Usaha</button>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Unit Usaha
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama BUMDes</th>
                                <th>Nama Unit</th>
                                <th>Deskripsi Unit</th>
                                <th>Jenis Usaha</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $start + 1; // Sesuaikan nomor awal dengan halaman saat ini
                            if (mysqli_num_rows($result_unit) > 0) {
                                while ($row = mysqli_fetch_assoc($result_unit)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['nama_bumdes']); ?></td>
                                        <td><?= htmlspecialchars($row['nama']); ?></td>
                                        <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                                        <td><?= htmlspecialchars($row['slug']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#editUnitUsahaModal<?= $row['id']; ?>">Edit</button>
                                            <a href="manajemen.php?type=unit&action=hapus_unit&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus unit usaha ini?');">Hapus</a>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editUnitUsahaModal<?= $row['id']; ?>" tabindex="-1" aria-labelledby="editUnitUsahaModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="manajemen.php?type=unit" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editUnitUsahaModalLabel<?= $row['id']; ?>" style="color: #343A40;">Edit Unit Usaha</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_unit" value="<?= $row['id']; ?>">
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Nama BUMDes</label>
                                                            <select name="bumdes_id" class="form-control" required>
                                                                <?php
                                                                foreach ($bumdes_options as $bumdes_option) {
                                                                    $selected = ($bumdes_option['id'] == $row['bumdes_id']) ? 'selected' : '';
                                                                    echo "<option value='" . $bumdes_option['id'] . "' " . $selected . ">" . htmlspecialchars($bumdes_option['nama']) . "</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Nama Unit</label>
                                                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($row['nama']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Deskripsi Unit</label>
                                                            <textarea name="deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($row['deskripsi']); ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label style="color: #343A40;">Jenis Usaha</label>
                                                            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($row['slug']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="edit_unit" class="btn btn-primary">Simpan Perubahan</button>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada unit usaha.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation for Unit Usaha">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page > 1) ? 'manajemen.php?type=unit&page=' . ($page - 1) : '#'; ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages_unit; $i++) : ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="manajemen.php?type=unit&page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages_unit) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page < $total_pages_unit) ? 'manajemen.php?type=unit&page=' . ($page + 1) : '#'; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="modal fade" id="tambahUnitUsahaModal" tabindex="-1" aria-labelledby="tambahUnitUsahaModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="manajemen.php?type=unit" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tambahUnitUsahaModalLabel" style="color: #343A40;">Tambah Unit Usaha</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="color: #343A40;">Nama BUMDes</label>
                                <select name="bumdes_id" class="form-control" required>
                                    <option value="">Pilih BUMDes</option>
                                    <?php
                                    foreach ($bumdes_options as $bumdes_option) {
                                        echo "<option value='" . $bumdes_option['id'] . "'>" . htmlspecialchars($bumdes_option['nama']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Nama Unit</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Deskripsi Unit</label>
                                <textarea name="deskripsi" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label style="color: #343A40;">Jenis Usaha</label>
                                <input type="text" name="jenis_usaha" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="tambah_unit" class="btn btn-primary">Tambah</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php else : ?>
        <div class="alert alert-info" role="alert">
            Silakan pilih "**Informasi BUMDes**" atau "**Unit Usaha**" dari menu samping untuk mengelola data.
        </div>
    <?php endif; ?>
</div>

</div>
    </div>
</div>
</div>
<footer class="text-center py-3 mt-auto" style="background-color: #E0F2F7; color: #343A40; position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" style="color: #343A40;">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("collapsed");
        document.querySelector(".content").classList.toggle('ml-collapsed'); // Tambahkan ini
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</body>
</html>