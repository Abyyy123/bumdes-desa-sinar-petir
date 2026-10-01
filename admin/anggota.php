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

// Handle profile update
if (isset($_POST['simpan'])) {
    $nama_baru = $_POST['nama'];
    $username_baru = $_POST['username'];
    $email_baru = $_POST['email'];
    $password_baru = $_POST['password'];
    $foto_baru = $_FILES['foto'];

    $update_query = "UPDATE pengguna SET nama=?, username=?, email=? WHERE id=?";
    $bind_params = 'sssi';
    $bind_values = [&$nama_baru, &$username_baru, &$email_baru, &$user_id];

    if (!empty($password_baru)) {
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
        $update_query = "UPDATE pengguna SET nama=?, username=?, email=?, password=? WHERE id=?";
        $bind_params = 'ssssi';
        $bind_values = [&$nama_baru, &$username_baru, &$email_baru, &$hashed_password, &$user_id];
    }

    // Handle photo upload
    if ($foto_baru['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../img/foto/';
        $file_extension = pathinfo($foto_baru['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid('profile_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($foto_baru['tmp_name'], $upload_path)) {
            // Delete old photo if it's not the default
            if ($user['foto'] && $user['foto'] != 'default.png' && file_exists($upload_dir . $user['foto'])) {
                unlink($upload_dir . $user['foto']);
            }
            $user['foto'] = $new_file_name; // Update current user session data
            $update_query = str_replace('WHERE id=?', ', foto=? WHERE id=?', $update_query);
            $bind_params .= 's';
            $bind_values[] = &$new_file_name;
        } else {
            echo "<script>alert('Gagal mengunggah foto.');</script>";
        }
    }

    $stmt_update_profile = mysqli_prepare($conn, $update_query);
    if ($stmt_update_profile) {
        mysqli_stmt_bind_param($stmt_update_profile, $bind_params, ...$bind_values);
        if (mysqli_stmt_execute($stmt_update_profile)) {
            $_SESSION['user_nama'] = $nama_baru; // Update session with new name
            $_SESSION['user_username'] = $username_baru; // Update session with new username
            $_SESSION['user_email'] = $email_baru; // Update session with new email

            // Re-fetch user data to get the latest info including new photo name
            $query_updated_user = "SELECT * FROM pengguna WHERE id = ?";
            $stmt_updated_user = mysqli_prepare($conn, $query_updated_user);
            mysqli_stmt_bind_param($stmt_updated_user, 'i', $user_id);
            mysqli_stmt_execute($stmt_updated_user);
            $result_updated_user = mysqli_stmt_get_result($stmt_updated_user);
            $user = mysqli_fetch_assoc($result_updated_user);

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='anggota.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_update_profile);
    } else {
        echo "<script>alert('Error preparing statement: " . mysqli_error($conn) . "');</script>";
    }
}


// Function to generate new member number
function generateNewMemberNumber($conn) {
    $prefix = "ANG";
    $query = "SELECT nomor_anggota FROM anggota ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $last_member = mysqli_fetch_assoc($result);
        $last_number = (int) substr($last_member['nomor_anggota'], strlen($prefix));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

// Logika Penambahan Anggota
if (isset($_POST['tambah_anggota'])) {
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $tanggal_bergabung = $_POST['tanggal_bergabung'];
    $nomor_anggota = generateNewMemberNumber($conn); // Auto-generate the member number

    $insert_query = "INSERT INTO anggota (nama, alamat, nomor_anggota, tanggal_bergabung) VALUES (?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt_insert, 'ssss', $nama, $alamat, $nomor_anggota, $tanggal_bergabung);

    if (mysqli_stmt_execute($stmt_insert)) {
        echo "<script>alert('Anggota berhasil ditambahkan!');window.location.href='anggota.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan anggota: " . mysqli_error($conn) . "');</script>";
    }
}

// Logika Edit Anggota
if (isset($_POST['edit_anggota'])) {
    $id_edit = $_POST['id_anggota'];
    $nama_edit = $_POST['nama'];
    $alamat_edit = $_POST['alamat'];
    $nomor_anggota_edit = $_POST['nomor_anggota']; // This is kept for manual editing if needed, but not auto-generated
    $tanggal_bergabung_edit = $_POST['tanggal_bergabung'];

    // Cek apakah nomor_anggota sudah ada untuk ID lain
    $check_query = "SELECT COUNT(*) FROM anggota WHERE nomor_anggota = ? AND id != ?";
    $stmt_check = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt_check, 'si', $nomor_anggota_edit, $id_edit);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_bind_result($stmt_check, $count);
    mysqli_stmt_fetch($stmt_check);
    mysqli_stmt_close($stmt_check);

    if ($count > 0) {
        echo "<script>alert('Nomor Anggota sudah terdaftar untuk anggota lain. Mohon gunakan nomor lain.');</script>";
    } else {
        $update_query = "UPDATE anggota SET nama=?, alamat=?, nomor_anggota=?, tanggal_bergabung=? WHERE id=?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt_update, 'ssssi', $nama_edit, $alamat_edit, $nomor_anggota_edit, $tanggal_bergabung_edit, $id_edit);

        if (mysqli_stmt_execute($stmt_update)) {
            echo "<script>alert('Data anggota berhasil diperbarui!');window.location.href='anggota.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui data anggota: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Logika Hapus Anggota
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    $delete_query = "DELETE FROM anggota WHERE id=?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);

    if (mysqli_stmt_execute($stmt_delete)) {
        echo "<script>alert('Anggota berhasil dihapus!');window.location.href='anggota.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus anggota: " . mysqli_error($conn) . "');</script>";
    }
}

// Pagination setup
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of records for pagination
$total_rows_query = "SELECT COUNT(*) AS total FROM anggota";
$total_rows_result = mysqli_query($conn, $total_rows_query);
$total_rows = mysqli_fetch_assoc($total_rows_result)['total'];
$total_pages = ceil($total_rows / $limit);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Anggota</title>
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
        /* Mengubah warna tombol warning menjadi ungu pastel */
        .btn-warning {
            background-color: #D1C4E9; /* Ungu pastel */
            border-color: #D1C4E9;
            color: #343A40; /* Warna teks untuk kontras */
        }
        .btn-warning:hover {
            background-color: #BCAFE0; /* Ungu pastel sedikit lebih gelap */
            border-color: #BCAFE0;
            color: #343A40;
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
        /* Styles for pagination */
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .page-item .page-link {
            color: #343A40;
            background-color: #E0F2F7;
            border: 1px solid #A7D9ED;
        }
        .page-item.active .page-link {
            background-color: #A7D9ED;
            border-color: #A7D9ED;
            color: #343A40;
        }
        .page-item.disabled .page-link {
            color: #6C757D;
            background-color: #F0F4F7;
            border-color: #CED4DA;
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
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan</button> <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button> </div>
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
                <a class="nav-link submenu" href="pengguna.php?type=penjual_pelanggan">Data Penjual & Pelanggan</a>
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
    <a class="nav-link active" href="anggota.php">
        <i class="fas fa-people-group"></i>
        <span class="ml-2">Manajemen Anggota</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="false" aria-controls="manajemenEcommerce">
        <i class="fas fa-cart-shopping"></i>
        <span class="ml-2">E-commerce</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse" id="manajemenEcommerce">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="kategori_produk.php">Kategori Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="produk.php"> Data Produk</a>
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
        <h2 class="mb-4">Manajemen Anggota BUMDes</h2>
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahAnggotaModal">Tambah Anggota Baru</button>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Anggota
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nomor Anggota</th>
                                <th>Nama</th>
                                <th>Alamat</th>
                                <th>Tanggal Bergabung</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Mengambil data anggota dengan limit dan offset untuk pagination
                            $sql_anggota = "SELECT * FROM anggota ORDER BY nomor_anggota ASC LIMIT $limit OFFSET $offset";
                            $result_anggota = mysqli_query($conn, $sql_anggota);
                            $no = ($page - 1) * $limit + 1; // Correct starting number for each page
                            if (mysqli_num_rows($result_anggota) > 0) {
                                while ($row = mysqli_fetch_assoc($result_anggota)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['nomor_anggota']); ?></td>
                                        <td><?= htmlspecialchars($row['nama']); ?></td>
                                        <td><?= htmlspecialchars($row['alamat'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($row['tanggal_bergabung'] ? date('d-m-Y', strtotime($row['tanggal_bergabung'])) : '-'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editAnggotaModal<?= $row['id']; ?>">Edit</button>
                                            <a href="anggota.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus anggota ini?');">Hapus</a>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editAnggotaModal<?= $row['id']; ?>" tabindex="-1" aria-labelledby="editAnggotaModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="anggota.php" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editAnggotaModalLabel<?= $row['id']; ?>">Edit Data Anggota</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id_anggota" value="<?= $row['id']; ?>">
                                                        <div class="form-group">
                                                            <label>Nama</label>
                                                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($row['nama']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Nomor Anggota</label>
                                                            <input type="text" name="nomor_anggota" class="form-control" value="<?= htmlspecialchars($row['nomor_anggota']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Alamat</label>
                                                            <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($row['alamat']); ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Tanggal Bergabung</label>
                                                            <input type="date" name="tanggal_bergabung" class="form-control" value="<?= htmlspecialchars($row['tanggal_bergabung']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="edit_anggota" class="btn btn-primary">Simpan Perubahan</button> <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button> </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data anggota.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="anggota.php?page=<?= $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="anggota.php?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="anggota.php?page=<?= $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="modal fade" id="tambahAnggotaModal" tabindex="-1" aria-labelledby="tambahAnggotaModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="anggota.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tambahAnggotaModalLabel">Tambah Anggota Baru</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Alamat</label>
                                <textarea name="alamat" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Bergabung</label>
                                <input type="date" name="tanggal_bergabung" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="tambah_anggota" class="btn btn-primary">Tambah</button> <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button> </div>
                    </form>
                </div>
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