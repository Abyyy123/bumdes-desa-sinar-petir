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

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='diskon.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Tambah/Edit Diskon ---
if (isset($_POST['simpan_diskon'])) {
    $id = $_POST['diskon_id'] ?? null;
    $nama_diskon = $_POST['nama_diskon'];
    $kode_diskon = $_POST['kode_diskon'];
    $jenis_diskon = $_POST['jenis_diskon']; // Akan berisi 'persen' atau 'fixed'
    $nilai_diskon = $_POST['nilai_diskon'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_berakhir = $_POST['tanggal_berakhir'];
    $status = $_POST['status'];

    // Validasi nilai diskon disesuaikan dengan 'persen' dan 'fixed'
    if ($jenis_diskon === 'persen' && ($nilai_diskon < 0 || $nilai_diskon > 100)) {
        echo "<script>alert('Untuk diskon persentase, nilai diskon harus antara 0 dan 100.');</script>";
    } elseif ($jenis_diskon === 'fixed' && $nilai_diskon < 0) {
        echo "<script>alert('Untuk diskon tetap, nilai diskon tidak boleh negatif.');</script>";
    } else {
        // Cek kode diskon duplikat (saat tambah atau edit tapi kode diubah)
        $check_kode_query = "SELECT id FROM diskon WHERE kode_diskon = ? AND id != ?";
        $stmt_check_kode = mysqli_prepare($conn, $check_kode_query);
        mysqli_stmt_bind_param($stmt_check_kode, 'si', $kode_diskon, $id);
        mysqli_stmt_execute($stmt_check_kode);
        $result_check_kode = mysqli_stmt_get_result($stmt_check_kode);

        if (mysqli_num_rows($result_check_kode) > 0) {
            echo "<script>alert('Kode diskon sudah ada. Silakan gunakan kode lain.');</script>";
            mysqli_stmt_close($stmt_check_kode);
        } else {
            mysqli_stmt_close($stmt_check_kode); // Tutup statement cek kode

            if ($id) {
                // Update data
                $update_query = "UPDATE diskon SET nama_diskon = ?, kode_diskon = ?, jenis_diskon = ?, nilai_diskon = ?, tanggal_mulai = ?, tanggal_berakhir = ?, status = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $update_query);
                if ($stmt_update) {
                    mysqli_stmt_bind_param($stmt_update, 'sssdsssi', $nama_diskon, $kode_diskon, $jenis_diskon, $nilai_diskon, $tanggal_mulai, $tanggal_berakhir, $status, $id);
                    if (mysqli_stmt_execute($stmt_update)) {
                        echo "<script>alert('Diskon berhasil diperbarui!');window.location.href='diskon.php';</script>";
                    } else {
                        echo "<script>alert('Gagal memperbarui diskon: " . mysqli_error($conn) . "');</script>";
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    echo "<script>alert('Gagal menyiapkan statement update: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                // Tambah data
                $insert_query = "INSERT INTO diskon (nama_diskon, kode_diskon, jenis_diskon, nilai_diskon, tanggal_mulai, tanggal_berakhir, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $insert_query);
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, 'sssdsss', $nama_diskon, $kode_diskon, $jenis_diskon, $nilai_diskon, $tanggal_mulai, $tanggal_berakhir, $status);
                    if (mysqli_stmt_execute($stmt_insert)) {
                        echo "<script>alert('Diskon berhasil ditambahkan!');window.location.href='diskon.php';</script>";
                    } else {
                        echo "<script>alert('Gagal menambahkan diskon: " . mysqli_error($conn) . "');</script>";
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    echo "<script>alert('Gagal menyiapkan statement tambah: " . mysqli_error($conn) . "');</script>";
                }
            }
        }
    }
}

// --- Logika Hapus Diskon ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    $delete_query = "DELETE FROM diskon WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            echo "<script>alert('Diskon berhasil dihapus!');window.location.href='diskon.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus diskon: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Query untuk mengambil semua data diskon ---
$sql_diskon = "SELECT * FROM diskon ORDER BY created_at DESC";
$result_diskon = mysqli_query($conn, $sql_diskon);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Diskon</title>
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

        /* Status Badge Styling */
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            white-space: nowrap;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }
        .status-aktif { background-color: #28a745; } /* Success */
        .status-nonaktif { background-color: #6c757d; } /* Secondary */

        /* --- STYLING UTAMA UNTUK TABEL DISKON --- */
        .table-diskon {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-diskon thead { display: table-header-group !important; }
        .table-diskon tbody { display: table-row-group !important; }
        .table-diskon tr { display: table-row !important; }
        .table-diskon th,
        .table-diskon td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-diskon th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, Nama Diskon, Kode, Jenis, Nilai, Mulai, Berakhir, Status, Aksi */
        .table-diskon td:nth-child(1), .table-diskon th:nth-child(1) { /* No. */
            width: 4% !important;
            min-width: 35px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(2), .table-diskon th:nth-child(2) { /* ID */
            width: 5% !important;
            min-width: 50px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(3), .table-diskon th:nth-child(3) { /* Nama Diskon */
            width: 15% !important;
            min-width: 120px !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(4), .table-diskon th:nth-child(4) { /* Kode */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(5), .table-diskon th:nth-child(5) { /* Jenis */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(6), .table-diskon th:nth-child(6) { /* Nilai */
            width: 8% !important;
            min-width: 70px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(7), .table-diskon th:nth-child(7) { /* Mulai */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(8), .table-diskon th:nth-child(8) { /* Berakhir */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(9), .table-diskon th:nth-child(9) { /* Status */
            width: 8% !important;
            min-width: 70px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-diskon td:nth-child(10), .table-diskon th:nth-child(10) { /* Aksi */
            width: 10% !important;
            min-width: 80px !important;
            text-align: center !important;
            white-space: normal !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 3px !important;
        }

        /* Penyesuaian tombol di kolom Aksi */
        .table-diskon .btn-sm {
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
            .table-diskon th,
            .table-diskon td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-diskon th,
            .table-diskon td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-diskon td:nth-child(3), .table-diskon th:nth-child(3) { /* Nama Diskon */
                min-width: 100px !important;
            }
            .table-diskon td:nth-child(10) { /* Aksi */
                width: 12% !important;
                min-width: 90px !important;
            }
            .table-diskon .btn-sm {
                max-width: 50px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-diskon th,
            .table-diskon td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-diskon td:nth-child(3), .table-diskon th:nth-child(3) { /* Nama Diskon */
                max-width: 80px !important;
            }
            .table-diskon td:nth-child(10) { /* Aksi */
                width: 15% !important;
                min-width: 70px !important;
            }
            .table-diskon .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 40px !important;
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
                <a class="nav-link submenu" href="pengguna.php?type=penjual_pelanggan">Data Pengguna</a>
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
                <a class="nav-link submenu active" href="diskon.php">Diskon</a>
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
        <h2 class="mb-4">Manajemen Diskon</h2>

        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#diskonModal" data-mode="tambah">
                <i class="fas fa-plus"></i> Tambah Diskon
            </button>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Diskon
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-diskon">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Nama Diskon</th>
                                <th>Kode</th>
                                <th>Jenis</th>
                                <th>Nilai</th>
                                <th>Tanggal Mulai</th>
                                <th>Tanggal Berakhir</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($result_diskon) > 0) {
                                while ($row = mysqli_fetch_assoc($result_diskon)) {
                                    $status_class = '';
                                    if ($row['status'] === 'aktif') {
                                        $status_class = 'status-aktif';
                                    } else {
                                        $status_class = 'status-nonaktif';
                                    }

                                    $nilai_diskon_formatted = $row['nilai_diskon'];
                                    // Menampilkan nilai diskon sesuai jenis
                                    if ($row['jenis_diskon'] === 'persen') { // Menggunakan 'persen'
                                        $nilai_diskon_formatted .= '%';
                                    } else { // Menggunakan 'fixed'
                                        $nilai_diskon_formatted = 'Rp ' . number_format($nilai_diskon_formatted, 0, ',', '.');
                                    }
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_diskon'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['kode_diskon'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            // Menampilkan teks yang lebih deskriptif untuk jenis diskon di tabel
                                            $display_jenis = htmlspecialchars($row['jenis_diskon'] ?? '-');
                                            if ($display_jenis === 'persen') {
                                                echo 'Persen (%)';
                                            } elseif ($display_jenis === 'fixed') {
                                                echo 'Fixed (Rp)';
                                            } else {
                                                echo ucwords($display_jenis);
                                            }
                                            ?>
                                        </td>
                                        <td><?= $nilai_diskon_formatted; ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal_mulai'] ?? ''))); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal_berakhir'] ?? ''))); ?></td>
                                        <td><span class="status-badge <?= $status_class; ?>"><?= htmlspecialchars(ucwords($row['status'] ?? '-')); ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#diskonModal"
                                                data-mode="edit"
                                                data-id="<?= $row['id']; ?>"
                                                data-nama_diskon="<?= htmlspecialchars($row['nama_diskon'] ?? ''); ?>"
                                                data-kode_diskon="<?= htmlspecialchars($row['kode_diskon'] ?? ''); ?>"
                                                data-jenis_diskon="<?= htmlspecialchars($row['jenis_diskon'] ?? ''); ?>"
                                                data-nilai_diskon="<?= htmlspecialchars($row['nilai_diskon'] ?? ''); ?>"
                                                data-tanggal_mulai="<?= htmlspecialchars($row['tanggal_mulai'] ?? ''); ?>"
                                                data-tanggal_berakhir="<?= htmlspecialchars($row['tanggal_berakhir'] ?? ''); ?>"
                                                data-status="<?= htmlspecialchars($row['status'] ?? ''); ?>">
                                                Edit
                                            </button>
                                            <a href="diskon.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus diskon ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada data diskon.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="diskonModal" tabindex="-1" aria-labelledby="diskonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="diskon.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="diskonModalLabel">Tambah Diskon Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="diskon_id" id="diskon_id">

                    <div class="form-group">
                        <label for="nama_diskon">Nama Diskon</label>
                        <input type="text" class="form-control" id="nama_diskon" name="nama_diskon" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="kode_diskon">Kode Diskon</label>
                        <input type="text" class="form-control" id="kode_diskon" name="kode_diskon" required maxlength="50">
                        <small class="form-text text-muted">Kode harus unik, misalnya: NEWUSER20, HEMAT10RIBU</small>
                    </div>
                    <div class="form-group">
                        <label for="jenis_diskon">Jenis Diskon</label>
                        <select class="form-control" id="jenis_diskon" name="jenis_diskon" required>
                            <option value="persen">Persen (%)</option> <option value="fixed">Fixed (Rp)</option>   </select>
                    </div>
                    <div class="form-group">
                        <label for="nilai_diskon">Nilai Diskon</label>
                        <input type="number" step="0.01" class="form-control" id="nilai_diskon" name="nilai_diskon" required min="0">
                        <small class="form-text text-muted" id="nilai_diskon_hint">Untuk persentase, masukkan angka 0-100. Untuk tetap, masukkan nilai rupiah.</small>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_mulai">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" required>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_berakhir">Tanggal Berakhir</label>
                        <input type="date" class="form-control" id="tanggal_berakhir" name="tanggal_berakhir" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_diskon" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
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

    // JavaScript untuk mengisi modal tambah/edit diskon
    $('#diskonModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var mode = button.data('mode'); // 'tambah' or 'edit'

        var modal = $(this);
        var modalTitle = modal.find('.modal-title');
        var diskonIdInput = modal.find('#diskon_id');
        var namaDiskonInput = modal.find('#nama_diskon');
        var kodeDiskonInput = modal.find('#kode_diskon');
        var jenisDiskonSelect = modal.find('#jenis_diskon');
        var nilaiDiskonInput = modal.find('#nilai_diskon');
        var tanggalMulaiInput = modal.find('#tanggal_mulai');
        var tanggalBerakhirInput = modal.find('#tanggal_berakhir');
        var statusSelect = modal.find('#status');
        var nilaiDiskonHint = modal.find('#nilai_diskon_hint');

        // Reset form
        modal.find('form').trigger('reset');
        diskonIdInput.val('');

        if (mode === 'tambah') {
            modalTitle.text('Tambah Diskon Baru');
            // Set tanggal_mulai dan tanggal_berakhir ke tanggal sekarang secara default
            var today = new Date().toISOString().slice(0, 10);
            tanggalMulaiInput.val(today);
            tanggalBerakhirInput.val(today); // Bisa juga diatur untuk 1 bulan ke depan atau semacamnya

            // Atur hint awal dan batasan nilai_diskon untuk 'persen' (default)
            updateNilaiDiskonHint('persen', nilaiDiskonHint); // Tetapkan ke 'persen' sebagai default
            jenisDiskonSelect.val('persen'); // Pastikan dropdown disetel ke 'persen' saat tambah

        } else if (mode === 'edit') {
            modalTitle.text('Edit Diskon');
            var id = button.data('id');
            var nama_diskon = button.data('nama_diskon');
            var kode_diskon = button.data('kode_diskon');
            var jenis_diskon = button.data('jenis_diskon'); // Akan berisi 'persen' atau 'fixed' dari database
            var nilai_diskon = button.data('nilai_diskon');
            var tanggal_mulai = button.data('tanggal_mulai');
            var tanggal_berakhir = button.data('tanggal_berakhir');
            var status = button.data('status');

            diskonIdInput.val(id);
            namaDiskonInput.val(nama_diskon);
            kodeDiskonInput.val(kode_diskon);
            jenisDiskonSelect.val(jenis_diskon); // Ini akan memilih opsi yang tepat ('persen' atau 'fixed')
            nilaiDiskonInput.val(nilai_diskon);
            tanggalMulaiInput.val(tanggal_mulai); // date input expects 'YYYY-MM-DD'
            tanggalBerakhirInput.val(tanggal_berakhir); // date input expects 'YYYY-MM-DD'
            statusSelect.val(status);

            // Update hint berdasarkan jenis diskon saat edit
            updateNilaiDiskonHint(jenis_diskon, nilaiDiskonHint);
        }

        // Event listener untuk perubahan jenis diskon
        jenisDiskonSelect.off('change').on('change', function() {
            updateNilaiDiskonHint($(this).val(), nilaiDiskonHint);
        });
    });

    // Fungsi untuk mengupdate hint nilai diskon
    function updateNilaiDiskonHint(jenis, hintElement) {
        if (jenis === 'persen') { // Disinkronkan dengan nilai database 'persen'
            hintElement.text('Masukkan angka antara 0-100 (misal: 10 untuk 10%).');
            $('#nilai_diskon').attr('min', '0').attr('max', '100'); // Batasi input
        } else { // jenis === 'fixed' (Disinkronkan dengan nilai database 'fixed')
            hintElement.text('Masukkan nilai diskon dalam Rupiah (misal: 10000).');
            $('#nilai_diskon').attr('min', '0').removeAttr('max'); // Hanya positif
        }
    }
</script>
</body>
</html>