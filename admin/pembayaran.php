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

// --- Logika Edit Profil Admin (dari navbar) ---
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
            $query_refetch = "SELECT * FROM pengguna WHERE id = ?";
            $stmt_refetch = mysqli_prepare($conn, $query_refetch);
            mysqli_stmt_bind_param($stmt_refetch, 'i', $user_id);
            mysqli_stmt_execute($stmt_refetch);
            $result_refetch = mysqli_stmt_get_result($stmt_refetch);
            $user = mysqli_fetch_assoc($result_refetch);
            mysqli_stmt_close($stmt_refetch);

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='pembayaran.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Tambah/Edit Pembayaran ---
if (isset($_POST['simpan_pembayaran'])) {
    $id = $_POST['pembayaran_id'] ?? null;
    $pesanan_id = $_POST['pesanan_id'];
    $tanggal_pembayaran = $_POST['tanggal_pembayaran'];
    $metode_pembayaran = $_POST['metode_pembayaran']; // Ini akan menjadi nama metode, bukan ID
    $jumlah_bayar = $_POST['jumlah_bayar'];
    $status_pembayaran = $_POST['status_pembayaran'];
    $keterangan = $_POST['keterangan'];

    // Logika upload bukti_transfer
    $bukti_transfer_name = $_POST['old_bukti_transfer'] ?? null;
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
        $target_dir = "../img/bukti_pembayaran/"; // Buat folder ini jika belum ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $new_bukti_transfer_name = uniqid() . '_' . basename($_FILES['bukti_transfer']['name']);
        $target_file = $target_dir . $new_bukti_transfer_name;
        $fileExtension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (in_array($fileExtension, $allowed_types) && $_FILES['bukti_transfer']['size'] < 10000000) { // Max 10MB
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $target_file)) {
                if ($bukti_transfer_name && file_exists($target_dir . $bukti_transfer_name)) {
                    unlink($target_dir . $bukti_transfer_name);
                }
                $bukti_transfer_name = $new_bukti_transfer_name;
            } else {
                echo "<script>alert('Gagal mengunggah bukti transfer.');</script>";
                exit;
            }
        } else {
            echo "<script>alert('Bukti transfer tidak valid (format atau ukuran).');</script>";
            exit;
        }
    }

    if ($id) {
        // Update data
        $update_query = "UPDATE pembayaran SET pesanan_id = ?, tanggal_pembayaran = ?, metode_pembayaran = ?, jumlah_bayar = ?, status_pembayaran = ?, bukti_transfer = ?, keterangan = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, 'isssdssi', $pesanan_id, $tanggal_pembayaran, $metode_pembayaran, $jumlah_bayar, $status_pembayaran, $bukti_transfer_name, $keterangan, $id);
            if (mysqli_stmt_execute($stmt_update)) {
                echo "<script>alert('Pembayaran berhasil diperbarui!');window.location.href='pembayaran.php';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui pembayaran: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt_update);
        } else {
            echo "<script>alert('Gagal menyiapkan statement update: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        // Tambah data
        $insert_query = "INSERT INTO pembayaran (pesanan_id, tanggal_pembayaran, metode_pembayaran, jumlah_bayar, status_pembayaran, bukti_transfer, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        if ($stmt_insert) {
            mysqli_stmt_bind_param($stmt_insert, 'isssdss', $pesanan_id, $tanggal_pembayaran, $metode_pembayaran, $jumlah_bayar, $status_pembayaran, $bukti_transfer_name, $keterangan);
            if (mysqli_stmt_execute($stmt_insert)) {
                echo "<script>alert('Pembayaran berhasil ditambahkan!');window.location.href='pembayaran.php';</script>";
            } else {
                echo "<script>alert('Gagal menambahkan pembayaran: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            echo "<script>alert('Gagal menyiapkan statement tambah: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- Logika Hapus Pembayaran ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    // Ambil nama file bukti_transfer sebelum dihapus
    $get_bukti_query = "SELECT bukti_transfer FROM pembayaran WHERE id = ?";
    $stmt_get_bukti = mysqli_prepare($conn, $get_bukti_query);
    mysqli_stmt_bind_param($stmt_get_bukti, 'i', $id_hapus);
    mysqli_stmt_execute($stmt_get_bukti);
    $result_bukti = mysqli_stmt_get_result($stmt_get_bukti);
    $row_bukti = mysqli_fetch_assoc($result_bukti);
    $bukti_file = $row_bukti['bukti_transfer'] ?? null;
    mysqli_stmt_close($stmt_get_bukti);

    $delete_query = "DELETE FROM pembayaran WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            // Hapus file bukti fisik jika ada
            // Bersihkan path untuk penghapusan file
            $clean_bukti_file = str_replace('bukti_pembayaran/', '', $bukti_file);
            if ($clean_bukti_file && file_exists("../img/bukti_pembayaran/" . $clean_bukti_file)) {
                unlink("../img/bukti_pembayaran/" . $clean_bukti_file);
            }
            echo "<script>alert('Pembayaran berhasil dihapus!');window.location.href='pembayaran.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus pembayaran: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Paginasi ---
$limit = 10; // Jumlah item per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total baris (tanpa LIMIT untuk paginasi)
$sql_count = "SELECT COUNT(*) AS total FROM pembayaran";
$result_count = mysqli_query($conn, $sql_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_records = $row_count['total'];
$total_pages = ceil($total_records / $limit);

// --- Query untuk mengambil semua transaksi pembayaran dengan paginasi ---
$sql_pembayaran = "SELECT
                        p.*,
                        ps.kode_unik,
                        us.nama AS nama_pelanggan
                    FROM
                        pembayaran p
                    LEFT JOIN
                        pesanan ps ON p.pesanan_id = ps.id
                    LEFT JOIN
                        pengguna us ON ps.pelanggan_id = us.id
                    ORDER BY
                        p.tanggal_pembayaran DESC, p.created_at DESC
                    LIMIT ? OFFSET ?";
$stmt_pembayaran = mysqli_prepare($conn, $sql_pembayaran);
mysqli_stmt_bind_param($stmt_pembayaran, 'ii', $limit, $offset);
mysqli_stmt_execute($stmt_pembayaran);
$result_pembayaran = mysqli_stmt_get_result($stmt_pembayaran);
mysqli_stmt_close($stmt_pembayaran);


// --- Query untuk mengambil daftar pesanan yang belum lunas (untuk dropdown di modal tambah) ---
// Asumsi 'belum_bayar' adalah status untuk pesanan yang belum lunas
$sql_pesanan_blm_lunas = "SELECT id, kode_unik, total_harga
                          FROM pesanan
                          WHERE status_pesanan != 'Lunas' -- Asumsi ada kolom status_pesanan di tabel pesanan
                          ORDER BY kode_unik DESC";
$result_pesanan_blm_lunas = mysqli_query($conn, $sql_pesanan_blm_lunas);
$pesanan_list = [];
if ($result_pesanan_blm_lunas) {
    while ($row_pesanan = mysqli_fetch_assoc($result_pesanan_blm_lunas)) {
        $pesanan_list[] = $row_pesanan;
    }
}

// --- Query untuk mengambil daftar metode pembayaran dari tabel metode_pembayaran ---
$sql_metode_pembayaran = "SELECT id, nama_metode FROM metode_pembayaran WHERE aktif_platform = 1 ORDER BY nama_metode ASC";
$result_metode_pembayaran = mysqli_query($conn, $sql_metode_pembayaran);
$metode_list = [];
if ($result_metode_pembayaran) {
    while ($row_metode = mysqli_fetch_assoc($result_metode_pembayaran)) {
        $metode_list[] = $row_metode;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pembayaran</title>
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
            min-width: 90px;
            text-align: center;
        }
        .status-belum_bayar { background-color: #ffc107; color: #343a40; } /* Warning - Dark text */
        .status-menunggu_konfirmasi { background-color: #17a2b8; } /* Info */
        .status-sudah_bayar { background-color: #28a745; } /* Success */
        .status-gagal { background-color: #dc3545; } /* Danger */
        .status-dibatalkan { background-color: #6c757d; } /* Secondary */


        /* --- STYLING UTAMA UNTUK TABEL PEMBAYARAN --- */
        .table-pembayaran {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-pembayaran thead { display: table-header-group !important; }
        .table-pembayaran tbody { display: table-row-group !important; }
        .table-pembayaran tr { display: table-row !important; }
        .table-pembayaran th,
        .table-pembayaran td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-pembayaran th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, No. Pesanan, Pelanggan, Tgl Bayar, Metode, Jumlah, Status, Bukti, Aksi */
        .table-pembayaran td:nth-child(1), .table-pembayaran th:nth-child(1) { /* No. */
            width: 4% !important;
            min-width: 35px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(2), .table-pembayaran th:nth-child(2) { /* ID */
            width: 5% !important;
            min-width: 50px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(3), .table-pembayaran th:nth-child(3) { /* No. Pesanan */
            width: 10% !important;
            min-width: 80px !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(4), .table-pembayaran th:nth-child(4) { /* Pelanggan */
            width: 12% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(5), .table-pembayaran th:nth-child(5) { /* Tgl Bayar */
            width: 12% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(6), .table-pembayaran th:nth-child(6) { /* Metode */
            width: 12% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(7), .table-pembayaran th:nth-child(7) { /* Jumlah */
            width: 9% !important;
            min-width: 80px !important;
            white-space: nowrap !important;
            text-align: right !important;
        }
        .table-pembayaran td:nth-child(8), .table-pembayaran th:nth-child(8) { /* Status */
            width: 12% !important;
            min-width: 110px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(9), .table-pembayaran th:nth-child(9) { /* Bukti */
            width: 8% !important;
            min-width: 70px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pembayaran td:nth-child(10), .table-pembayaran th:nth-child(10) { /* Aksi */
            width: 14% !important; /* Cukup untuk Edit, Hapus, Lihat */
            min-width: 120px !important;
            text-align: center !important;
            white-space: normal !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 3px !important;
        }

        /* Penyesuaian tombol di kolom Aksi */
        .table-pembayaran .btn-sm {
            padding: 0.1rem 0.3rem !important;
            font-size: 0.7rem !important;
            white-space: nowrap !important;
            width: 100% !important;
            max-width: 80px !important;
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
            .table-pembayaran th,
            .table-pembayaran td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-pembayaran th,
            .table-pembayaran td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-pembayaran td:nth-child(6) { /* Metode */
                max-width: 90px !important;
            }
            .table-pembayaran td:nth-child(10) { /* Aksi */
                width: 18% !important;
                min-width: 110px !important;
            }
            .table-pembayaran .btn-sm {
                max-width: 70px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-pembayaran th,
            .table-pembayaran td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-pembayaran td:nth-child(6) { /* Metode */
                max-width: 70px !important;
            }
            .table-pembayaran td:nth-child(10) { /* Aksi */
                width: 25% !important;
                min-width: 90px !important;
            }
            .table-pembayaran .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 60px !important;
            }
        }
        /* Penyesuaian untuk modal */
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
        <i class="fas fa-cart-shopping"></i>
        <span class="ml-2"> E-commerce</span>
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
                <a class="nav-link submenu active" href="pembayaran.php">Data Pembayaran</a>
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
        <h2 class="mb-4">Manajemen Pembayaran</h2>

        <div class="mb-3">
            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#pembayaranModal" data-mode="tambah">
                <i class="fas fa-plus"></i> Tambah Pembayaran
            </button>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Pembayaran
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-pembayaran">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>No. Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Tgl Pembayaran</th>
                                <th>Metode</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Bukti</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sesuaikan nomor awal berdasarkan halaman saat ini
                            $no = ($page - 1) * $limit + 1;
                            if (mysqli_num_rows($result_pembayaran) > 0) {
                                while ($row = mysqli_fetch_assoc($result_pembayaran)) {
                                    $status_class = '';
                                    switch ($row['status_pembayaran']) {
                                        case 'belum_bayar':
                                            $status_class = 'status-belum_bayar';
                                            break;
                                        case 'menunggu_konfirmasi':
                                            $status_class = 'status-menunggu_konfirmasi';
                                            break;
                                        case 'sudah_bayar':
                                            $status_class = 'status-sudah_bayar';
                                            break;
                                        // Tambahkan kasus untuk status lain jika ada di masa depan
                                        default:
                                            $status_class = 'status-secondary'; // Default styling
                                            break;
                                    }
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['kode_unik'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_pembayaran'] ?? ''))); ?></td>
                                        <td><?= htmlspecialchars($row['metode_pembayaran'] ?? '-'); ?></td>
                                        <td>Rp<?= number_format($row['jumlah_bayar'] ?? 0, 0, ',', '.'); ?></td>
                                        <td><span class="status-badge <?= $status_class; ?>"><?= htmlspecialchars(str_replace('_', ' ', ucwords($row['status_pembayaran'] ?? '-'))); ?></span></td>
                                        <td>
                                            <?php if (!empty($row['bukti_transfer'])) : ?>
                                                <?php
                                                // Bersihkan path: hapus "bukti_pembayaran/" jika sudah menjadi bagian dari nilai yang tersimpan
                                                $clean_bukti_transfer = str_replace('bukti_pembayaran/', '', $row['bukti_transfer']);
                                                ?>
                                                <a href="../img/bukti_pembayaran/<?= htmlspecialchars($clean_bukti_transfer); ?>" target="_blank" class="btn btn-sm btn-info" title="Lihat Bukti"><i class="fas fa-eye"></i></a>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-secondary mb-1" data-toggle="modal" data-target="#pembayaranModal"
                                                data-mode="edit"
                                                data-id="<?= $row['id']; ?>"
                                                data-pesanan_id="<?= $row['pesanan_id']; ?>"
                                                data-tanggal_pembayaran="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($row['tanggal_pembayaran'] ?? ''))); ?>"
                                                data-metode_pembayaran="<?= htmlspecialchars($row['metode_pembayaran'] ?? ''); ?>"
                                                data-jumlah_bayar="<?= htmlspecialchars($row['jumlah_bayar'] ?? ''); ?>"
                                                data-status_pembayaran="<?= htmlspecialchars($row['status_pembayaran'] ?? ''); ?>"
                                                data-bukti_transfer="<?= htmlspecialchars($row['bukti_transfer'] ?? ''); ?>"
                                                data-keterangan="<?= htmlspecialchars($row['keterangan'] ?? ''); ?>">
                                                Edit
                                            </button>
                                            <a href="pembayaran.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus pembayaran ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada data pembayaran.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="pembayaranModal" tabindex="-1" aria-labelledby="pembayaranModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="pembayaran.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="pembayaranModalLabel">Tambah Pembayaran Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="pembayaran_id" id="pembayaran_id">
                    <input type="hidden" name="old_bukti_transfer" id="old_bukti_transfer">

                    <div class="form-group">
                        <label for="pesanan_id">Pesanan</label>
                        <select class="form-control" id="pesanan_id" name="pesanan_id" required>
                            <option value="">Pilih Pesanan</option>
                            <?php foreach ($pesanan_list as $pesanan) : ?>
                                <option value="<?= htmlspecialchars($pesanan['id'] ?? ''); ?>">
                                    <?= htmlspecialchars($pesanan['kode_unik'] ?? 'N/A'); ?> (Total: Rp<?= number_format($pesanan['total_harga'] ?? 0, 0, ',', '.'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_pembayaran">Tanggal & Waktu Pembayaran</label>
                        <input type="datetime-local" class="form-control" id="tanggal_pembayaran" name="tanggal_pembayaran" required>
                    </div>
                    <div class="form-group">
                        <label for="metode_pembayaran">Metode Pembayaran</label>
                        <select class="form-control" id="metode_pembayaran" name="metode_pembayaran" required>
                            <option value="">Pilih Metode Pembayaran</option>
                            <?php foreach ($metode_list as $metode) : ?>
                                <option value="<?= htmlspecialchars($metode['nama_metode'] ?? ''); ?>"><?= htmlspecialchars($metode['nama_metode'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="jumlah_bayar">Jumlah Bayar (Rp)</label>
                        <input type="number" class="form-control" id="jumlah_bayar" name="jumlah_bayar" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="status_pembayaran">Status Pembayaran</label>
                        <select class="form-control" id="status_pembayaran" name="status_pembayaran" required>
                            <option value="belum_bayar">Belum Bayar</option>
                            <option value="menunggu_konfirmasi">Menunggu Konfirmasi</option>
                            <option value="sudah_bayar">Sudah Bayar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bukti_transfer">Bukti Transfer</label>
                        <input type="file" class="form-control-file" id="bukti_transfer" name="bukti_transfer">
                        <small class="form-text text-muted">Format: JPG, JPEG, PNG, GIF, PDF. Maksimal 10MB.</small>
                        <div id="current_bukti_preview" class="mt-2">
                            </div>
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_pembayaran" class="btn btn-primary">Simpan</button>
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

    // JavaScript untuk mengisi modal tambah/edit pembayaran
    $('#pembayaranModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var mode = button.data('mode'); // 'tambah' or 'edit'

        var modal = $(this);
        var modalTitle = modal.find('.modal-title');
        var pembayaranIdInput = modal.find('#pembayaran_id');
        var pesananIdSelect = modal.find('#pesanan_id');
        var tanggalPembayaranInput = modal.find('#tanggal_pembayaran');
        var metodePembayaranSelect = modal.find('#metode_pembayaran');
        var jumlahBayarInput = modal.find('#jumlah_bayar');
        var statusPembayaranSelect = modal.find('#status_pembayaran');
        var oldBuktiTransferInput = modal.find('#old_bukti_transfer');
        var currentBuktiPreview = modal.find('#current_bukti_preview');
        var buktiTransferFileInput = modal.find('#bukti_transfer');
        var keteranganInput = modal.find('#keterangan');


        // Reset form
        modal.find('form').trigger('reset');
        pembayaranIdInput.val('');
        oldBuktiTransferInput.val('');
        currentBuktiPreview.html('');
        buktiTransferFileInput.val(''); // Clear selected file

        if (mode === 'tambah') {
            modalTitle.text('Tambah Pembayaran Baru');
            // Set tanggal_pembayaran ke tanggal dan waktu sekarang secara default
            var now = new Date();
            var year = now.getFullYear();
            var month = (now.getMonth() + 1).toString().padStart(2, '0');
            var day = now.getDate().toString().padStart(2, '0');
            var hours = now.getHours().toString().padStart(2, '0');
            var minutes = now.getMinutes().toString().padStart(2, '0');
            tanggalPembayaranInput.val(year + '-' + month + '-' + day + 'T' + hours + ':' + minutes);

        } else if (mode === 'edit') {
            modalTitle.text('Edit Pembayaran');
            var id = button.data('id');
            var pesanan_id = button.data('pesanan_id');
            var tanggal_pembayaran = button.data('tanggal_pembayaran');
            var metode_pembayaran = button.data('metode_pembayaran');
            var jumlah_bayar = button.data('jumlah_bayar');
            var status_pembayaran = button.data('status_pembayaran');
            var bukti_transfer = button.data('bukti_transfer');
            var keterangan = button.data('keterangan');

            pembayaranIdInput.val(id);
            pesananIdSelect.val(pesanan_id);
            tanggalPembayaranInput.val(tanggal_pembayaran); // datetime-local format 'YYYY-MM-DDTHH:mm'
            metodePembayaranSelect.val(metode_pembayaran);
            jumlahBayarInput.val(jumlah_bayar);
            statusPembayaranSelect.val(status_pembayaran);
            keteranganInput.val(keterangan);
            oldBuktiTransferInput.val(bukti_transfer);

            // Tampilkan preview bukti transfer lama
            if (bukti_transfer) {
                // Bersihkan path: hapus "bukti_pembayaran/" jika sudah menjadi bagian dari nilai yang tersimpan
                var clean_bukti_transfer = bukti_transfer.replace('bukti_pembayaran/', '');

                var fileExtension = clean_bukti_transfer.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    currentBuktiPreview.html('<p>Bukti lama:</p><img src="../img/bukti_pembayaran/' + clean_bukti_transfer + '" class="img-fluid" style="max-height: 100px; object-fit: contain;">');
                } else if (fileExtension === 'pdf') {
                    currentBuktiPreview.html('<p>Bukti lama:</p><a href="../img/bukti_pembayaran/' + clean_bukti_transfer + '" target="_blank">Lihat PDF Bukti</a>');
                } else {
                     currentBuktiPreview.html('<p>Bukti lama: ' + clean_bukti_transfer + '</p>');
                }

            } else {
                currentBuktiPreview.html('<p>Tidak ada bukti transfer lama.</p>');
            }
        }
    });
</script>
</body>
</html>