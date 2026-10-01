<?php
session_start();
include('../koneksi/koneksi.php'); // Pastikan path ke koneksi.php benar

// Pastikan pengguna sudah login
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['pengguna_id'];
$query_user = "SELECT * FROM pengguna WHERE id = ?";
$stmt_user = mysqli_prepare($conn, $query_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!($user['role'] === 'admin')) {
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.');window.location.href='dashboard_admin.php';</script>";
    exit;
}

// --- Logika Edit Profil Admin (dari navbar), jika ada, sama seperti di halaman lain ---
if (isset($_POST['simpan_profil'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ? WHERE id = ?";
    $params_profil = [$nama, $username, $email, $user_id];
    $types_profil = 'sssi';

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ?, password = ? WHERE id = ?";
        $params_profil = [$nama, $username, $email, $hashed_password, $user_id];
        $types_profil = 'ssssi';
    }

    $foto_name = $user['foto'] ?? 'default.png';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "../img/foto/";
        $new_foto_name = uniqid() . '_' . basename($_FILES['foto']['name']);
        $target_file = $target_dir . $new_foto_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types) && $_FILES['foto']['size'] < 5000000) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                if ($user['foto'] && $user['foto'] !== 'default.png' && file_exists($target_dir . $user['foto'])) {
                    unlink($target_dir . $user['foto']);
                }
                $foto_name = $new_foto_name;
                $update_profil_query = str_replace(" WHERE id = ?", ", foto = ? WHERE id = ?", $update_profil_query);
                array_splice($params_profil, count($params_profil) - 1, 0, [$foto_name]);
                $types_profil = substr_replace($types_profil, 's', strlen($types_profil) - 1, 0);
            } else {
                echo "<script>alert('Gagal mengunggah foto profil.');</script>";
            }
        } else {
            echo "<script>alert('Foto profil tidak valid (format atau ukuran).');</script>";
        }
    }

    $stmt_profil = mysqli_prepare($conn, $update_profil_query);
    if ($stmt_profil) {
        mysqli_stmt_bind_param($stmt_profil, $types_profil, ...$params_profil);
        if (mysqli_stmt_execute($stmt_profil)) {
            $_SESSION['nama'] = $nama;
            $_SESSION['foto'] = $foto_name;
            // Refetch user data to update $user variable
            $query_refetch = "SELECT * FROM pengguna WHERE id = ?";
            $stmt_refetch = mysqli_prepare($conn, $query_refetch);
            mysqli_stmt_bind_param($stmt_refetch, 'i', $user_id);
            mysqli_stmt_execute($stmt_refetch);
            $result_refetch = mysqli_stmt_get_result($stmt_refetch);
            $user = mysqli_fetch_assoc($result_refetch);
            mysqli_stmt_close($stmt_refetch);

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='wishlist.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Hapus Item Wishlist ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    $delete_query = "DELETE FROM wishlist WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            echo "<script>alert('Item wishlist berhasil dihapus!');window.location.href='wishlist.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus item wishlist: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Pagination ---
$records_per_page = 10; // Jumlah item per halaman
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Query untuk menghitung total item wishlist
$count_query = "SELECT COUNT(*) AS total_records FROM wishlist";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total_records'];
$total_pages = ceil($total_records / $records_per_page);

// --- Query untuk mengambil item wishlist dengan pagination ---
$sql_wishlist = "SELECT
                    w.id,
                    w.tanggal_ditambahkan,
                    p.nama,
                    u.nama AS nama_pelanggan
                FROM
                    wishlist w
                LEFT JOIN
                    produk p ON w.produk_id = p.id
                LEFT JOIN
                    pengguna u ON w.pelanggan_id = u.id
                ORDER BY
                    w.tanggal_ditambahkan DESC
                LIMIT ?, ?";
$stmt_wishlist = mysqli_prepare($conn, $sql_wishlist);
mysqli_stmt_bind_param($stmt_wishlist, 'ii', $offset, $records_per_page);
mysqli_stmt_execute($stmt_wishlist);
$result_wishlist = mysqli_stmt_get_result($stmt_wishlist);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Wishlist</title>
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

        /* --- STYLING UTAMA UNTUK TABEL WISHLIST --- */
        .table-wishlist {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-wishlist thead { display: table-header-group !important; }
        .table-wishlist tbody { display: table-row-group !important; }
        .table-wishlist tr { display: table-row !important; }
        .table-wishlist th,
        .table-wishlist td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-wishlist th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, Pelanggan, Produk, Tanggal Ditambahkan, Aksi */
        .table-wishlist td:nth-child(1), .table-wishlist th:nth-child(1) { /* No. */
            width: 5% !important;
            min-width: 35px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-wishlist td:nth-child(2), .table-wishlist th:nth-child(2) { /* ID */
            width: 8% !important;
            min-width: 50px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-wishlist td:nth-child(3), .table-wishlist th:nth-child(3) { /* Pelanggan */
            width: 30% !important;
            min-width: 150px !important;
            white-space: nowrap !important;
        }
        .table-wishlist td:nth-child(4), .table-wishlist th:nth-child(4) { /* Produk */
            width: 30% !important;
            min-width: 150px !important;
            white-space: nowrap !important;
        }
        .table-wishlist td:nth-child(5), .table-wishlist th:nth-child(5) { /* Tanggal Ditambahkan */
            width: 17% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-wishlist td:nth-child(6), .table-wishlist th:nth-child(6) { /* Aksi */
            width: 10% !important;
            min-width: 60px !important;
            text-align: center !important;
            white-space: normal !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 3px !important;
        }

        /* Penyesuaian tombol di kolom Aksi */
        .table-wishlist .btn-sm {
            padding: 0.1rem 0.3rem !important;
            font-size: 0.7rem !important;
            white-space: nowrap !important;
            width: 100% !important;
            max-width: 60px !important;
        }

        /* Pastikan div table-responsive bekerja dengan baik */
        .table-responsive {
            overflow-x: auto !important;
            width: 100% !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 0.25rem !important;
        }

        /* Aturan media queries untuk responsivitas */
        @media (max-width: 1200px) { /* Untuk layar laptop kecil */
            .table-wishlist th,
            .table-wishlist td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-wishlist th,
            .table-wishlist td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-wishlist td:nth-child(3), .table-wishlist th:nth-child(3) { /* Pelanggan */
                min-width: 120px !important;
            }
            .table-wishlist td:nth-child(4), .table-wishlist th:nth-child(4) { /* Produk */
                min-width: 120px !important;
            }
            .table-wishlist td:nth-child(6) { /* Aksi */
                width: 15% !important;
                min-width: 70px !important;
            }
            .table-wishlist .btn-sm {
                max-width: 50px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-wishlist th,
            .table-wishlist td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-wishlist td:nth-child(3), .table-wishlist th:nth-child(3) { /* Pelanggan */
                max-width: 100px !important;
            }
            .table-wishlist td:nth-child(4), .table-wishlist th:nth-child(4) { /* Produk */
                max-width: 100px !important;
            }
            .table-wishlist td:nth-child(6) { /* Aksi */
                width: 20% !important;
                min-width: 60px !important;
            }
            .table-wishlist .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 40px !important;
            }
            .sidebar .nav-link.active {
                background-color: #CCEEF5; /* Sidebar Hover color for active link */
                color: #343A40;
            }
        }
        /* Pagination Styling */
        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px 0;
        }

        .page-item .page-link {
            color: #343A40; /* Dark Grayish Black */
            background-color: #F0F4F7; /* Kartu (Aksen Putih Kebiruan) */
            border: 1px solid #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) */
            margin: 0 3px;
            border-radius: 5px;
        }

        .page-item.active .page-link {
            background-color: #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) */
            border-color: #A7D9ED;
            color: #343A40; /* Dark Grayish Black */
        }

        .page-item .page-link:hover {
            background-color: #CCEEF5; /* Sidebar Hover: Slightly Darker Soft Sky Blue */
            border-color: #CCEEF5;
            color: #343A40;
        }

        .page-item.disabled .page-link {
            color: #6C757D; /* Muted gray for disabled */
            background-color: #E9ECEF; /* Lighter background for disabled */
            border-color: #dee2e6;
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
            <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline" style="color: #343A40;">Profil</span> </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama'] ?? 'Pengguna'); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username'] ?? '-'); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email'] ?? '-'); ?></p>
                <div class="dropdown-divider my-2"></div>
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
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if (!empty($user['foto'])) : ?>
                            <img src="../img/foto/<?= htmlspecialchars($user['foto']); ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                        <small class="form-text text-muted">Maksimal 5MB, format JPG, JPEG, PNG, GIF.</small>
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
    <a class="nav-link" href="anggota.php">
        <i class="fas fa-people-group"></i>
        <span class="ml-2">Manajemen Anggota</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="true" aria-controls="manajemenEcommerce">
        <i class="fas fa-shopping-cart"></i>
        <span class="ml-2">E-commerce</span>
        <i class="fas fa-caret-down float-right"></i>
    </a>
    <div class="collapse show" id="manajemenEcommerce">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link submenu" href="kategori_produk.php">Kategori Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="produk.php">Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pesanan.php">Pesanan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pembayaran.php">Pembayaran</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pengiriman.php">Pengiriman</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="ulasan_produk.php">Ulasan Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="diskon.php">Diskon</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu active" href="wishlist.php">Wishlist</a>
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
        <h2 class="mb-4">Manajemen Wishlist</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Wishlist Pelanggan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-wishlist">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th>Tanggal Ditambahkan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $offset + 1; // Sesuaikan nomor urut dengan halaman saat ini
                            if (mysqli_num_rows($result_wishlist) > 0) {
                                while ($row = mysqli_fetch_assoc($result_wishlist)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['nama'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['tanggal_ditambahkan'] ?? ''))); ?></td>
                                        <td>
                                            <a href="wishlist.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus item wishlist ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data wishlist.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page - 1; ?>" aria-label="Previous">
                                    <span aria-disabled="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page + 1; ?>" aria-label="Next">
                                    <span aria-disabled="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
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