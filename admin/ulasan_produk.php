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
// Anda bisa menyalin kode ini dari pengiriman.php jika ingin fitur edit profil di navbar berfungsi
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

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='ulasan_produk.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}


// --- Logika Tambah/Edit/Hapus Ulasan Produk ---

// Logika Hapus Ulasan
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    $delete_query = "DELETE FROM ulasan_produk WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            // Dapatkan kembali halaman saat ini setelah penghapusan
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            echo "<script>alert('Ulasan berhasil dihapus!');window.location.href='ulasan_produk.php?page=" . $current_page . "';</script>";
        } else {
            echo "<script>alert('Gagal menghapus ulasan: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
    exit; // Penting untuk menghentikan eksekusi setelah redirect
}

// --- Logika Pagination ---
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Hitung total data ulasan produk
$sql_total_ulasan = "SELECT COUNT(id) AS total FROM ulasan_produk";
$result_total_ulasan = mysqli_query($conn, $sql_total_ulasan);
$row_total_ulasan = mysqli_fetch_assoc($result_total_ulasan);
$total_ulasan = $row_total_ulasan['total'];
$total_pages = ceil($total_ulasan / $limit);

// Query untuk mengambil ulasan produk dengan pagination
$sql_ulasan = "SELECT
                    up.id,
                    up.rating,
                    up.komentar,
                    up.tanggal_ulasan,
                    up.created_at,
                    up.updated_at,
                    p.nama AS nama_produk,
                    pel.nama AS nama_pelanggan
                FROM
                    ulasan_produk up
                LEFT JOIN
                    produk p ON up.produk_id = p.id
                LEFT JOIN
                    pengguna pel ON up.pelanggan_id = pel.id
                ORDER BY
                    up.tanggal_ulasan DESC, up.created_at DESC
                LIMIT ?, ?";
$stmt_ulasan = mysqli_prepare($conn, $sql_ulasan);
mysqli_stmt_bind_param($stmt_ulasan, 'ii', $start, $limit);
mysqli_stmt_execute($stmt_ulasan);
$result_ulasan = mysqli_stmt_get_result($stmt_ulasan);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Ulasan Produk</title>
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

        /* Specific card colors from dashboard, apply to general elements */
        .bg-primary { background-color: #A7D9ED !important; } /* Kartu (Aksen Utama - Biru Pastel) */
        .text-primary { color: #A7D9ED !important; }
        .btn-primary { background-color: #A7D9ED !important; border-color: #A7D9ED !important; color: #343A40 !important;}
        .btn-primary:hover { background-color: #92C6DE !important; border-color: #92C6DE !important;}

        .bg-success { background-color: #C8E6C9 !important; } /* Kartu (Aksen Hijau Pastel) */
        .text-success { color: #C8E6C9 !important; }

        .bg-info { background-color: #D1C4E9 !important; } /* Kartu (Aksen Ungu Pastel) */
        .text-info { color: #D1C4E9 !important; }
        .btn-info { background-color: #D1C4E9 !important; border-color: #D1C4E9 !important; color: #343A40 !important;}
        .btn-info:hover { background-color: #B9A5D6 !important; border-color: #B9A5D6 !important;}


        .bg-warning { background-color: #FFD700 !important; } /* Standard gold for warning */
        .text-warning { color: #FFD700 !important; }

        .bg-danger { background-color: #FF6347 !important; } /* Standard tomato for danger */
        .text-danger { color: #FF6347 !important; }
        .btn-danger { background-color: #FF6347 !important; border-color: #FF6347 !important; color: #F8FBFD !important;}
        .btn-danger:hover { background-color: #E0523C !important; border-color: #E0523C !important;}


        .bg-secondary { background-color: #A9A9A9 !important; } /* Dark grey for secondary */
        .text-secondary { color: #A9A9A9 !important; }
        .btn-secondary { background-color: #D1C4E9 !important; border-color: #D1C4E9 !important; color: #343A40 !important;}
        .btn-secondary:hover { background-color: #B9A5D6 !important; border-color: #B9A5D6 !important;}


        .bg-dark { background-color: #343A40 !important; } /* Teks: Dark Grayish Black for dark card */
        .text-white { color: #F8FBFD !important; } /* Text on dark backgrounds */


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

        /* --- STYLING UTAMA UNTUK TABEL ULASAN --- */
        .table-ulasan {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-ulasan thead { display: table-header-group !important; }
        .table-ulasan tbody { display: table-row-group !important; }
        .table-ulasan tr { display: table-row !important; }
        .table-ulasan th,
        .table-ulasan td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important; /* Border color for tables */
            text-overflow: ellipsis !important;
            overflow: hidden !important;
            color: #343A40; /* Text color for table cells */
        }

        .table-ulasan th {
            background-color: #E0F2F7 !important; /* Soft Sky Blue for table headers */
            color: #343A40 !important; /* Dark Grayish Black for table header text */
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, Produk, Pelanggan, Rating, Komentar, Tanggal Ulasan, Aksi */
        .table-ulasan td:nth-child(1), .table-ulasan th:nth-child(1) { /* No. */
            width: 4% !important;
            min-width: 35px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(2), .table-ulasan th:nth-child(2) { /* ID */
            width: 5% !important;
            min-width: 50px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(3), .table-ulasan th:nth-child(3) { /* Produk */
            width: 15% !important;
            min-width: 120px !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(4), .table-ulasan th:nth-child(4) { /* Pelanggan */
            width: 15% !important;
            min-width: 120px !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(5), .table-ulasan th:nth-child(5) { /* Rating */
            width: 8% !important;
            min-width: 60px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(6), .table-ulasan th:nth-child(6) { /* Komentar */
            width: 35% !important;
            min-width: 200px !important;
        }
        .table-ulasan td:nth-child(7), .table-ulasan th:nth-child(7) { /* Tanggal Ulasan */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-ulasan td:nth-child(8), .table-ulasan th:nth-child(8) { /* Aksi */
            width: 8% !important;
            min-width: 70px !important;
            text-align: center !important;
            white-space: normal !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 3px !important;
        }

        /* Penyesuaian tombol di kolom Aksi */
        .table-ulasan .btn-sm {
            padding: 0.1rem 0.3rem !important;
            font-size: 0.7rem !important;
            white-space: nowrap !important;
            width: 100% !important;
            max-width: 60px !important;
        }

        /* Styling untuk rating bintang */
        .rating-stars .fas.fa-star {
            color: #FFD700; /* Warna kuning dari dashboard warning color */
        }
        .rating-stars .far.fa-star {
            color: #e0e0e0; /* Warna abu-abu untuk bintang kosong */
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
            .table-ulasan th,
            .table-ulasan td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-ulasan th,
            .table-ulasan td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-ulasan td:nth-child(6) { /* Komentar */
                max-width: 150px !important;
            }
            .table-ulasan td:nth-child(8) { /* Aksi */
                width: 12% !important;
                min-width: 90px !important;
            }
            .table-ulasan .btn-sm {
                max-width: 50px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-ulasan th,
            .table-ulasan td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-ulasan td:nth-child(6) { /* Komentar */
                max-width: 100px !important;
            }
            .table-ulasan td:nth-child(8) { /* Aksi */
                width: 15% !important;
                min-width: 70px !important;
            }
            .table-ulasan .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 40px !important;
            }
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
<nav class="navbar navbar-expand-lg" style="background-color: #E0F2F7;">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#" style="color: #343A40;">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline" style="color: #343A40;">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="Profile">
                </div>
                <h5 class="mb-1" style="color: #343A40;"><?= htmlspecialchars($user['nama'] ?? 'Pengguna'); ?></h5>
                <p class="mb-0 small" style="color: #343A40;">Username: <?= htmlspecialchars($user['username'] ?? '-'); ?></p>
                <p class="mb-0 small" style="color: #343A40;">Email: <?= htmlspecialchars($user['email'] ?? '-'); ?></p>
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
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama'] ?? ''); ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? ''); ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if (!empty($user['foto'])) : ?>
                            <img src="../img/foto/<?= htmlspecialchars($user['foto']); ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file" style="color: #343A40;">
                        <small class="form-text text-muted" style="color: #343A40 !important;">Maksimal 5MB, format JPG, JPEG, PNG, GIF.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_profil" class="btn" style="background-color: #A7D9ED; color: #343A40;">Simpan</button>
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
        <a class="nav-link submenu active" href="ulasan_produk.php">Ulasan Produk</a>
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
</ul>
<a href="../logout.php" class="text-danger" style="color: #FF6347 !important;"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <h2 class="mb-4" style="color: #343A40;">Manajemen Ulasan Produk</h2>

        <div class="card shadow-sm mb-4" style="background-color: #F8FBFD; color: #343A40;">
            <div class="card-header" style="background-color: #A7D9ED; color: #343A40;">
                Daftar Ulasan Produk
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-ulasan">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Produk</th>
                                <th>Pelanggan</th>
                                <th>Rating</th>
                                <th>Komentar</th>
                                <th>Tgl Ulasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $start + 1; // Sesuaikan nomor urut untuk pagination
                            if (mysqli_num_rows($result_ulasan) > 0) {
                                while ($row = mysqli_fetch_assoc($result_ulasan)) {
                                    // Tampilkan bintang rating
                                    $rating_html = '<div class="rating-stars">';
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $row['rating']) {
                                            $rating_html .= '<i class="fas fa-star"></i>'; // Bintang terisi
                                        } else {
                                            $rating_html .= '<i class="far fa-star"></i>'; // Bintang kosong
                                        }
                                    }
                                    $rating_html .= '</div>';
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_produk'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></td>
                                        <td><?= $rating_html; ?></td>
                                        <td><?= htmlspecialchars($row['komentar'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_ulasan'] ?? ''))); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info mb-1" style="background-color: #D1C4E9; color: #343A40;" data-toggle="modal" data-target="#detailUlasanModal"
                                                data-produk="<?= htmlspecialchars($row['nama_produk'] ?? 'N/A'); ?>"
                                                data-pelanggan="<?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?>"
                                                data-rating="<?= htmlspecialchars($row['rating'] ?? 0); ?>"
                                                data-komentar="<?= htmlspecialchars($row['komentar'] ?? '-'); ?>"
                                                data-tanggal="<?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_ulasan'] ?? ''))); ?>">
                                                Detail
                                            </button>
                                            <a href="ulasan_produk.php?action=hapus&id=<?= $row['id']; ?>&page=<?= $page; ?>" class="btn btn-sm btn-danger" style="background-color: #FF6347; color: #F8FBFD;" onclick="return confirm('Anda yakin ingin menghapus ulasan ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center' style='color: #343A40;'>Tidak ada data ulasan produk.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation for reviews">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page <= 1) ? '#' : '?page=' . ($page - 1); ?>" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?= ($page >= $total_pages) ? '#' : '?page=' . ($page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailUlasanModal" tabindex="-1" aria-labelledby="detailUlasanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #F8FBFD; color: #343A40;">
            <div class="modal-header">
                <h5 class="modal-title" id="detailUlasanModalLabel" style="color: #343A40;">Detail Ulasan Produk</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Produk:</strong> <span id="detail_produk"></span></p>
                <p><strong>Pelanggan:</strong> <span id="detail_pelanggan"></span></p>
                <p><strong>Rating:</strong> <span id="detail_rating_stars"></span></p>
                <p><strong>Tanggal Ulasan:</strong> <span id="detail_tanggal"></span></p>
                <hr>
                <p><strong>Komentar:</strong></p>
                <p id="detail_komentar" style="white-space: pre-wrap;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-dismiss="modal" style="background-color: #D1C4E9; color: #343A40;">Tutup</button>
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
    }

    // JavaScript untuk mengisi modal detail ulasan
    $('#detailUlasanModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal

        var produk = button.data('produk');
        var pelanggan = button.data('pelanggan');
        var rating = button.data('rating');
        var komentar = button.data('komentar');
        var tanggal = button.data('tanggal');

        var modal = $(this);
        modal.find('#detail_produk').text(produk);
        modal.find('#detail_pelanggan').text(pelanggan);
        modal.find('#detail_tanggal').text(tanggal);
        modal.find('#detail_komentar').text(komentar);

        // Render stars for rating
        var ratingStarsHtml = '';
        for (var i = 1; i <= 5; i++) {
            if (i <= rating) {
                ratingStarsHtml += '<i class="fas fa-star" style="color: #FFD700;"></i>'; // Filled star with warning color
            } else {
                ratingStarsHtml += '<i class="far fa-star" style="color: #e0e0e0;"></i>'; // Empty star
            }
        }
        modal.find('#detail_rating_stars').html(ratingStarsHtml);
    });
</script>
</body>
</html>