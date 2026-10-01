<?php
session_start();
include('../koneksi/koneksi.php'); // Pastikan path ke koneksi.php benar

// Pastikan pengguna sudah login
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
if (!($user['role'] === 'admin')) {
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.');window.location.href='dashboard_admin.php';</script>";
    exit;
}

// --- Logika Edit Profil Admin (dari navbar) ---
if (isset($_POST['simpan_profil'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ? WHERE id = ?";
    $params_profil = [$nama, $username, $email, $user_id];
    $types_profil = 'sssi';

    // Handle password update if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_profil_query = "UPDATE pengguna SET nama = ?, username = ?, email = ?, password = ? WHERE id = ?";
        $params_profil = [$nama, $username, $email, $hashed_password, $user_id];
        $types_profil = 'ssssi';
    }

    // Handle foto profil update
    $foto_name = $user['foto'] ?? 'default.png'; // Default to current foto or default
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "../img/foto/"; // Pastikan folder ini ada
        // Buat nama file unik
        $new_foto_name = uniqid() . '_' . basename($_FILES['foto']['name']);
        $target_file = $target_dir . $new_foto_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageFileType, $allowed_types) && $_FILES['foto']['size'] < 5000000) { // Max 5MB
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                // Hapus foto lama jika bukan default
                if ($user['foto'] && $user['foto'] !== 'default.png' && file_exists($target_dir . $user['foto'])) {
                    unlink($target_dir . $user['foto']);
                }
                $foto_name = $new_foto_name;
                // Add foto to update query
                $update_profil_query = str_replace(" WHERE id = ?", ", foto = ? WHERE id = ?", $update_profil_query);
                array_splice($params_profil, count($params_profil) - 1, 0, [$foto_name]); // Insert foto_name before user_id
                $types_profil = substr_replace($types_profil, 's', strlen($types_profil) - 1, 0); // Insert 's' for foto_name
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
            // Perbarui data user di session dan variabel $user
            $_SESSION['nama'] = $nama;
            $_SESSION['foto'] = $foto_name;
            // Fetch ulang data user setelah update
            $user_id_refetch = $_SESSION['pengguna_id'];
            $query_refetch = "SELECT * FROM pengguna WHERE id = ?";
            $stmt_refetch = mysqli_prepare($conn, $query_refetch);
            mysqli_stmt_bind_param($stmt_refetch, 'i', $user_id_refetch);
            mysqli_stmt_execute($stmt_refetch);
            $result_refetch = mysqli_stmt_get_result($stmt_refetch);
            $user = mysqli_fetch_assoc($result_refetch);
            mysqli_stmt_close($stmt_refetch);

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='pesanan.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}


// Logika Ubah Status Pesanan
if (isset($_POST['update_status_pesanan'])) {
    $pesanan_id = $_POST['pesanan_id'];
    $new_status = $_POST['status_pesanan'];
    $kurir_id = !empty($_POST['kurir_id']) ? $_POST['kurir_id'] : null;

    // Pastikan status yang dikirim valid (dari enum)
    $valid_statuses = ['menunggu_pembayaran', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];
    if (!in_array($new_status, $valid_statuses)) {
        echo "<script>alert('Status pesanan tidak valid.');</script>";
    } else {
        // Query untuk update status dan kurir_id
        $update_query = "UPDATE pesanan SET status_pesanan = ?, kurir_id = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        if ($stmt_update) {
            // Menggunakan 'i' untuk kurir_id yang bisa NULL. mysqli_stmt_bind_param akan handle NULL dengan baik.
            mysqli_stmt_bind_param($stmt_update, 'sii', $new_status, $kurir_id, $pesanan_id);

            if (mysqli_stmt_execute($stmt_update)) {
                echo "<script>alert('Status pesanan berhasil diperbarui!');window.location.href='pesanan.php';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui status pesanan: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt_update);
        } else {
            echo "<script>alert('Gagal menyiapkan statement update status: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- Pagination Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Count total records for pagination
$count_query = "SELECT COUNT(*) AS total_records FROM pesanan";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total_records'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch orders with limit and offset for pagination
$sql_pesanan_paginated = "SELECT
                                p.*,
                                cust.nama AS nama_pelanggan,
                                kur.nama AS nama_kurir_obj
                            FROM
                                pesanan p
                            LEFT JOIN
                                pengguna cust ON p.pelanggan_id = cust.id AND cust.role = 'pelanggan'
                            LEFT JOIN
                                kurir kur ON p.kurir_id = kur.id
                            ORDER BY
                                p.tanggal_pesanan DESC
                            LIMIT ?, ?";
$stmt_pesanan_paginated = mysqli_prepare($conn, $sql_pesanan_paginated);
mysqli_stmt_bind_param($stmt_pesanan_paginated, 'ii', $offset, $records_per_page);
mysqli_stmt_execute($stmt_pesanan_paginated);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan_paginated);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pesanan</title>
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
        /* Adjusted button colors */
        .btn-info-custom { /* For "Detail" button (purple pastel) */
            background-color: #D1C4E9; /* Using btn-secondary's color for consistency */
            border-color: #D1C4E9;
            color: #343A40;
        }
        .btn-info-custom:hover {
            background-color: #BCAFE0;
            border-color: #BCAFE0;
            color: #343A40;
        }
        .btn-warning-custom { /* For "Ubah Status" button (tomato red) */
            background-color: #FF6347; /* Standard tomato for danger */
            border-color: #FF6347;
            color: #F8FBFD; /* Very Light Blueish White for contrast */
        }
        .btn-warning-custom:hover {
            background-color: #E0523C;
            border-color: #E0523C;
            color: #F8FBFD;
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

        /* Warna status pesanan */
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            white-space: nowrap; /* Mencegah teks terpotong */
            display: inline-block; /* Pastikan badge tidak memanjang */
            min-width: 80px; /* Lebar minimum untuk konsistensi */
            text-align: center;
        }
        .status-menunggu_pembayaran { background-color: #ffc107; color: #333; } /* Yellow */
        .status-diproses { background-color: #17a2b8; } /* Cyan */
        .status-dikirim { background-color: #007bff; } /* Blue */
        .status-selesai { background-color: #28a745; } /* Green */
        .status-dibatalkan { background-color: #dc3545; } /* Red */

        /* --- STYLING UTAMA UNTUK TABEL PESANAN --- */
        .table-pesanan {
            /* Jangan set width 100% di sini, biarkan table-responsive yang mengatur */
            /* Remove explicit table-layout and display properties that might interfere with responsiveness */
            width: auto; /* Allow table to be wider than its container */
            min-width: 100%; /* Ensure it takes at least 100% if content is small */
            border-collapse: collapse;
        }

        .table-pesanan th,
        .table-pesanan td {
            padding: 0.3rem 0.5rem;
            vertical-align: middle;
            font-size: 0.7rem;
            box-sizing: border-box;
            border: 1px solid #dee2e6;
            text-overflow: ellipsis;
            overflow: hidden; /* Keep overflow hidden for single line text */
            white-space: nowrap; /* Keep text on single line unless specified */
        }

        .table-pesanan th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: bold;
        }

        /* Atur lebar setiap kolom secara eksplisit, gunakan min-width for flexibility */
        /* Urutan kolom: No., ID Pesanan, Kode Pesanan, Pelanggan, Kurir, Tanggal, Status, Metode Pembelian, Metode Pengiriman, Alamat, Ongkir, Total Harga, Aksi */
        .table-pesanan td:nth-child(1), .table-pesanan th:nth-child(1) { /* No. */
            min-width: 40px;
            text-align: center;
        }
        .table-pesanan td:nth-child(2), .table-pesanan th:nth-child(2) { /* ID Pesanan */
            min-width: 70px;
            text-align: center;
        }
        .table-pesanan td:nth-child(3), .table-pesanan th:nth-child(3) { /* Kode Pesanan */
            min-width: 100px;
        }
        .table-pesanan td:nth-child(4), .table-pesanan th:nth-child(4) { /* Pelanggan */
            min-width: 120px;
        }
        .table-pesanan td:nth-child(5), .table-pesanan th:nth-child(5) { /* Kurir */
            min-width: 90px;
        }
        .table-pesanan td:nth-child(6), .table-pesanan th:nth-child(6) { /* Tanggal Pesanan */
            min-width: 130px;
        }
        .table-pesanan td:nth-child(7), .table-pesanan th:nth-child(7) { /* Status */
            min-width: 100px;
        }
        .table-pesanan td:nth-child(8), .table-pesanan th:nth-child(8) { /* Metode Pembelian */
            min-width: 100px;
        }
        .table-pesanan td:nth-child(9), .table-pesanan th:nth-child(9) { /* Metode Pengiriman */
            min-width: 100px;
        }
        .table-pesanan td:nth-child(10), .table-pesanan th:nth-child(10) { /* Alamat Pengiriman */
            min-width: 250px;
            white-space: normal; /* Allow text to wrap for address */
        }
        .table-pesanan td:nth-child(11), .table-pesanan th:nth-child(11) { /* Ongkos Kirim */
            min-width: 80px;
            text-align: right;
        }
        .table-pesanan td:nth-child(12), .table-pesanan th:nth-child(12) { /* Total Harga */
            min-width: 80px;
            text-align: right;
        }
        .table-pesanan td:nth-child(13), .table-pesanan th:nth-child(13) { /* Aksi */
            min-width: 150px;
            text-align: center;
            white-space: normal;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 3px;
        }

        /* Penyesuaian tombol di kolom Aksi */
        .table-pesanan .btn-sm {
            padding: 0.1rem 0.3rem;
            font-size: 0.65rem;
            white-space: nowrap;
            width: 100%;
            max-width: 100px;
        }

        /* Pastikan div table-responsive bekerja dengan baik */
        .table-responsive {
            overflow-x: auto; /* This is key for horizontal scroll */
            width: 100%; /* Ensures it takes full width of its parent */
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }

        /* Aturan media queries untuk responsivitas */
        @media (max-width: 1200px) {
            .table-pesanan th,
            .table-pesanan td {
                font-size: 0.68rem;
                padding: 0.25rem 0.45rem;
            }
        }

        @media (max-width: 992px) {
            .table-pesanan th,
            .table-pesanan td {
                font-size: 0.62rem;
                padding: 0.2rem 0.35rem;
            }
            .table-pesanan td:nth-child(10) {
                min-width: 180px;
            }
            .table-pesanan td:nth-child(13) {
                min-width: 130px;
            }
            .table-pesanan .btn-sm {
                max-width: 90px;
            }
        }

        @media (max-width: 767px) {
            .table-pesanan th,
            .table-pesanan td {
                font-size: 0.58rem;
                padding: 0.15rem 0.25rem;
            }
            .table-pesanan td:nth-child(10) {
                min-width: 150px;
            }
            .table-pesanan td:nth-child(13) {
                min-width: 110px;
            }
            .table-pesanan .btn-sm {
                font-size: 0.6rem;
                padding: 0.08rem 0.15rem;
                max-width: 80px;
            }
        }
        /* Penyesuaian khusus untuk modal detail pesanan */
        .modal-lg {
            max-width: 80% !important;
        }
        .modal-body p {
            margin-bottom: 0.5rem !important;
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
  <a class="nav-link active collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="false" aria-controls="manajemenEcommerce">
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
        <a class="nav-link submenu active" href="pesanan.php">Data Pesanan</a>
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
        <h2 class="mb-4">Manajemen Pesanan</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Pesanan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-pesanan">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Kode</th>
                                <th>Pelanggan</th>
                                <th>Kurir</th>
                                <th>Tgl Pesanan</th>
                                <th>Status</th>
                                <th>Metode Beli</th>
                                <th>Metode Kirim</th>
                                <th>Alamat Kirim</th>
                                <th>Ongkir</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = ($current_page - 1) * $records_per_page + 1;
                            if (mysqli_num_rows($result_pesanan) > 0) {
                                while ($row = mysqli_fetch_assoc($result_pesanan)) {
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['kode_unik'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_kurir_obj'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['tanggal_pesanan'] ?? ''))); ?></td>
                                        <td><span class="status-badge status-<?= strtolower(str_replace(' ', '_', $row['status_pesanan'] ?? '')); ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status_pesanan'] ?? '-'))); ?></span></td>
                                        <td><?= htmlspecialchars($row['metode_pembelian'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['metode_pengiriman'] ?? '-'))); ?></td>
                                        <td><?= htmlspecialchars($row['alamat_pengiriman'] ?? '-'); ?></td>
                                        <td>Rp<?= number_format($row['ongkos_kirim'] ?? 0, 0, ',', '.'); ?></td>
                                        <td>Rp<?= number_format($row['total_harga'] ?? 0, 0, ',', '.'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info-custom mb-1" data-toggle="modal" data-target="#detailPesananModal<?= $row['id']; ?>">Detail</button>
                                            <button type="button" class="btn btn-sm btn-warning-custom mb-1" data-toggle="modal" data-target="#editStatusPesananModal<?= $row['id']; ?>">Ubah Status</button>
                                            </td>
                                    </tr>

                                    <div class="modal fade" id="detailPesananModal<?= $row['id']; ?>" tabindex="-1" aria-labelledby="detailPesananModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="detailPesananModalLabel<?= $row['id']; ?>">Detail Pesanan #<?= htmlspecialchars($row['kode_unik'] ?? 'N/A'); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <h6>Informasi Umum:</h6>
                                                    <p><strong>ID Pesanan:</strong> <?= htmlspecialchars($row['id'] ?? '-'); ?></p>
                                                    <p><strong>Kode Pesanan:</strong> <?= htmlspecialchars($row['kode_unik'] ?? '-'); ?></p>
                                                    <p><strong>Pelanggan:</strong> <?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></p>
                                                    <p><strong>Kurir:</strong> <?= htmlspecialchars($row['nama_kurir_obj'] ?? '-'); ?></p>
                                                    <p><strong>Tanggal Pesanan:</strong> <?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['tanggal_pesanan'] ?? ''))); ?></p>
                                                    <p><strong>Status Pesanan:</strong> <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $row['status_pesanan'] ?? '')); ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status_pesanan'] ?? '-'))); ?></span></p>
                                                    <p><strong>Metode Pembelian:</strong> <?= htmlspecialchars($row['metode_pembelian'] ?? '-'); ?></p>
                                                    <p><strong>Metode Pengiriman:</strong> <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['metode_pengiriman'] ?? '-'))); ?></p>
                                                    <p><strong>Alamat Pengiriman:</strong> <?= htmlspecialchars($row['alamat_pengiriman'] ?? '-'); ?></p>
                                                    <p><strong>Ongkos Kirim:</strong> Rp<?= number_format($row['ongkos_kirim'] ?? 0, 0, ',', '.'); ?></p>
                                                    <p><strong>Total Harga (termasuk ongkir):</strong> Rp<?= number_format($row['total_harga'] ?? 0, 0, ',', '.'); ?></p>
                                                    <p><strong>Dibuat Pada:</strong> <?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['created_at'] ?? ''))); ?></p>
                                                    <p><strong>Terakhir Diperbarui:</strong> <?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['updated_at'] ?? ''))); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="editStatusPesananModal<?= $row['id']; ?>" tabindex="-1" aria-labelledby="editStatusPesananModalLabel<?= $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="pesanan.php" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editStatusPesananModalLabel<?= $row['id']; ?>">Ubah Status Pesanan #<?= htmlspecialchars($row['kode_unik'] ?? 'N/A'); ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="pesanan_id" value="<?= htmlspecialchars($row['id'] ?? ''); ?>">
                                                        <div class="form-group">
                                                            <label for="status_pesanan_<?= $row['id']; ?>">Status Pesanan</label>
                                                            <select class="form-control" id="status_pesanan_<?= $row['id']; ?>" name="status_pesanan" required>
                                                                <option value="menunggu_pembayaran" <?= (($row['status_pesanan'] ?? '') == 'menunggu_pembayaran') ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                                                                <option value="diproses" <?= (($row['status_pesanan'] ?? '') == 'diproses') ? 'selected' : ''; ?>>Diproses</option>
                                                                <option value="dikirim" <?= (($row['status_pesanan'] ?? '') == 'dikirim') ? 'selected' : ''; ?>>Dikirim</option>
                                                                <option value="selesai" <?= (($row['status_pesanan'] ?? '') == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                                                <option value="dibatalkan" <?= (($row['status_pesanan'] ?? '') == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="kurir_id_<?= $row['id']; ?>">Pilih Kurir (Opsional)</label>
                                                            <select class="form-control" id="kurir_id_<?= $row['id']; ?>" name="kurir_id">
                                                                <option value="">-- Tidak Ada Kurir --</option>
                                                                <?php
                                                                // Ambil daftar kurir dari tabel 'kurir'
                                                                $sql_kurir_list = "SELECT id, nama FROM kurir ORDER BY nama ASC";
                                                                $result_kurir_list = mysqli_query($conn, $sql_kurir_list);
                                                                if ($result_kurir_list) {
                                                                    while ($kurir_item = mysqli_fetch_assoc($result_kurir_list)) {
                                                                        $selected = (($kurir_item['id'] ?? '') == ($row['kurir_id'] ?? '')) ? 'selected' : '';
                                                                        echo "<option value='" . htmlspecialchars($kurir_item['id'] ?? '') . "' " . $selected . ">" . htmlspecialchars($kurir_item['nama'] ?? '') . "</option>";
                                                                    }
                                                                } else {
                                                                    echo "<option value=''>Error memuat kurir</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                            <small class="form-text text-muted">Hanya relevan untuk status 'Dikirim'.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="update_status_pesanan" class="btn btn-primary">Simpan Perubahan</button>
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='13' class='text-center'>Tidak ada data pesanan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $current_page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $current_page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
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