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

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='pengiriman.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Tambah/Edit Pengiriman ---
if (isset($_POST['simpan_pengiriman'])) {
    $id = $_POST['pengiriman_id'] ?? null;
    $pesanan_id = $_POST['pesanan_id'];
    $kurir_id = $_POST['kurir_id']; // ID kurir dari tabel 'kurir'
    $tanggal_pengiriman = $_POST['tanggal_pengiriman'];
    $status_pengiriman = $_POST['status_pengiriman'];
    $nomor_resi = $_POST['nomor_resi'];
    $keterangan = $_POST['keterangan'];

    if ($id) {
        // Update data
        $update_query = "UPDATE pengiriman SET pesanan_id = ?, kurir_id = ?, tanggal_pengiriman = ?, status_pengiriman = ?, nomor_resi = ?, keterangan = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, 'iissdsi', $pesanan_id, $kurir_id, $tanggal_pengiriman, $status_pengiriman, $nomor_resi, $keterangan, $id);
            if (mysqli_stmt_execute($stmt_update)) {
                echo "<script>alert('Pengiriman berhasil diperbarui!');window.location.href='pengiriman.php';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui pengiriman: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt_update);
        } else {
            echo "<script>alert('Gagal menyiapkan statement update: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        // Tambah data
        $insert_query = "INSERT INTO pengiriman (pesanan_id, kurir_id, tanggal_pengiriman, status_pengiriman, nomor_resi, keterangan) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        if ($stmt_insert) {
            // Perhatikan bahwa $kurir_id sekarang merujuk ke tabel 'kurir'
            mysqli_stmt_bind_param($stmt_insert, 'iissds', $pesanan_id, $kurir_id, $tanggal_pengiriman, $status_pengiriman, $nomor_resi, $keterangan);
            if (mysqli_stmt_execute($stmt_insert)) {
                echo "<script>alert('Pengiriman berhasil ditambahkan!');window.location.href='pengiriman.php';</script>";
            } else {
                echo "<script>alert('Gagal menambahkan pengiriman: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            echo "<script>alert('Gagal menyiapkan statement tambah: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- Logika Hapus Pengiriman ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    $delete_query = "DELETE FROM pengiriman WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            echo "<script>alert('Pengiriman berhasil dihapus!');window.location.href='pengiriman.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus pengiriman: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Pagination Configuration ---
$limit = 10; // Number of entries per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// --- Query untuk mengambil total data pengiriman untuk pagination ---
$count_query = "SELECT COUNT(*) FROM pengiriman";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_array($count_result)[0];
$total_pages = ceil($total_records / $limit);

// --- Query untuk mengambil semua transaksi pengiriman dengan LIMIT dan OFFSET ---
$sql_pengiriman = "SELECT
                        pg.*,
                        ps.kode_unik,
                        pel.nama AS nama_pelanggan,
                        kr.nama AS nama_kurir -- Mengambil nama dari tabel kurir
                    FROM
                        pengiriman pg
                    LEFT JOIN
                        pesanan ps ON pg.pesanan_id = ps.id
                    LEFT JOIN
                        pengguna pel ON ps.pelanggan_id = pel.id
                    LEFT JOIN
                        kurir kr ON pg.kurir_id = kr.id -- JOIN ke tabel kurir
                    ORDER BY
                        pg.tanggal_pengiriman DESC, pg.created_at DESC
                    LIMIT $start, $limit";
$result_pengiriman = mysqli_query($conn, $sql_pengiriman);

// --- Query untuk mengambil daftar pesanan yang statusnya 'diproses' atau 'menunggu_pengiriman' (atau yang belum ada di tabel pengiriman) ---
$sql_pesanan_siap_kirim = "SELECT
                                p.id,
                                p.kode_unik,
                                p.total_harga,
                                u.nama AS nama_pelanggan
                            FROM
                                pesanan p
                            LEFT JOIN
                                pengguna u ON p.pelanggan_id = u.id
                            WHERE
                                p.id NOT IN (SELECT pesanan_id FROM pengiriman)
                                AND (p.status_pesanan = 'diproses' OR p.status_pesanan = 'menunggu_pengiriman')
                            ORDER BY
                                p.kode_unik DESC";
$result_pesanan_siap_kirim = mysqli_query($conn, $sql_pesanan_siap_kirim);
$pesanan_list = [];
if ($result_pesanan_siap_kirim) {
    while ($row_pesanan = mysqli_fetch_assoc($result_pesanan_siap_kirim)) {
        $pesanan_list[] = $row_pesanan;
    }
}

// --- Query untuk mengambil daftar kurir dari tabel kurir ---
// Perubahan di sini: Mengambil dari tabel 'kurir'
$sql_kurir = "SELECT id, nama FROM kurir ORDER BY nama ASC";
$result_kurir = mysqli_query($conn, $sql_kurir);
$kurir_list = [];
if ($result_kurir) {
    while ($row_kurir = mysqli_fetch_assoc($result_kurir)) {
        $kurir_list[] = $row_kurir;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pengiriman</title>
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
        /* Style for the new pastel purple button */
        .btn-pastel-purple {
            background-color: #D1C4E9; /* Pastel purple */
            border-color: #D1C4E9;
            color: #343A40;
        }
        .btn-pastel-purple:hover {
            background-color: #BCAFE0; /* Slightly darker pastel purple on hover */
            border-color: #BCAFE0;
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
            color: #343A40;
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
        .status-menunggu_konfirmasi { background-color: #ffc107; color: #343a40; } /* Warning - Dark text */
        .status-diproses { background-color: #17a2b8; } /* Info */
        .status-dikirim { background-color: #007bff; } /* Primary */
        .status-terkirim { background-color: #28a745; } /* Success */
        .status-gagal { background-color: #dc3545; } /* Danger */


        /* --- STYLING UTAMA UNTUK TABEL PENGIRIMAN --- */
        .table-pengiriman {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-pengiriman thead { display: table-header-group !important; }
        .table-pengiriman tbody { display: table-row-group !important; }
        .table-pengiriman tr { display: table-row !important; }
        .table-pengiriman th,
        .table-pengiriman td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-pengiriman th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, No. Pesanan, Pelanggan, Kurir, Tgl Kirim, Status, No. Resi, Keterangan, Aksi */
        .table-pengiriman td:nth-child(1), .table-pengiriman th:nth-child(1) { /* No. */
            width: 4% !important;
            min-width: 35px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(2), .table-pengiriman th:nth-child(2) { /* ID */
            width: 5% !important;
            min-width: 50px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(3), .table-pengiriman th:nth-child(3) { /* No. Pesanan */
            width: 10% !important;
            min-width: 80px !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(4), .table-pengiriman th:nth-child(4) { /* Pelanggan */
            width: 12% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(5), .table-pengiriman th:nth-child(5) { /* Kurir */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(6), .table-pengiriman th:nth-child(6) { /* Tgl Pengiriman */
            width: 12% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(7), .table-pengiriman th:nth-child(7) { /* Status */
            width: 12% !important;
            min-width: 110px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(8), .table-pengiriman th:nth-child(8) { /* No. Resi */
            width: 10% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-pengiriman td:nth-child(9), .table-pengiriman th:nth-child(9) { /* Keterangan */
            width: 15% !important;
            min-width: 120px !important;
        }
        .table-pengiriman td:nth-child(10), .table-pengiriman th:nth-child(10) { /* Aksi */
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
        .table-pengiriman .btn-sm {
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
            .table-pengiriman th,
            .table-pengiriman td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-pengiriman th,
            .table-pengiriman td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-pengiriman td:nth-child(9) { /* Keterangan */
                max-width: 100px !important;
            }
            .table-pengiriman td:nth-child(10) { /* Aksi */
                width: 12% !important;
                min-width: 90px !important;
            }
            .table-pengiriman .btn-sm {
                max-width: 50px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-pengiriman th,
            .table-pengiriman td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-pengiriman td:nth-child(9) { /* Keterangan */
                max-width: 80px !important;
            }
            .table-pengiriman td:nth-child(10) { /* Aksi */
                width: 15% !important;
                min-width: 70px !important;
            }
            .table-pengiriman .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 40px !important;
            }
            .sidebar .nav-link.active {
                background-color: #CCEEF5; /* Sidebar Hover color for active link */
                color: #343A40;
            }
        }

        /* Pagination styles */
        .pagination .page-item .page-link {
            color: #343A40; /* Dark Grayish Black */
            background-color: #F0F4F7; /* Kartu (Aksen Putih Kebiruan) */
            border: 1px solid #A7D9ED; /* Border biru pastel */
        }

        .pagination .page-item.active .page-link {
            background-color: #A7D9ED; /* Kartu (Aksen Utama - Biru Pastel) */
            border-color: #A7D9ED;
            color: #343A40;
        }

        .pagination .page-item .page-link:hover {
            background-color: #CCEEF5; /* Sidebar Hover: Slightly Darker Soft Sky Blue */
            border-color: #A7D9ED;
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
                <a class="nav-link submenu" href="produk.php">Data Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pesanan.php">Data Pesanan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu" href="pembayaran.php">Data Pembayaran</a>
            </li>
            <li class="nav-item">
                <a class="nav-link submenu active" href="pengiriman.php">Data Pengiriman</a>
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
        <h2 class="mb-4">Manajemen Pengiriman</h2>

        <div class="mb-3">
            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#pengirimanModal" data-mode="tambah">
                <i class="fas fa-plus"></i> Tambah Pengiriman
            </button>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Pengiriman
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-pengiriman">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Kode</th>
                                <th>Pelanggan</th>
                                <th>Kurir</th>
                                <th>Tgl Pengiriman</th>
                                <th>Status</th>
                                <th>No. Resi</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = ($page - 1) * $limit + 1; // Adjust starting number for pagination
                            if (mysqli_num_rows($result_pengiriman) > 0) {
                                while ($row = mysqli_fetch_assoc($result_pengiriman)) {
                                    $status_class = '';
                                    switch ($row['status_pengiriman']) {
                                        case 'menunggu_konfirmasi':
                                            $status_class = 'status-menunggu_konfirmasi';
                                            break;
                                        case 'diproses':
                                            $status_class = 'status-diproses';
                                            break;
                                        case 'dikirim':
                                            $status_class = 'status-dikirim';
                                            break;
                                        case 'terkirim':
                                            $status_class = 'status-terkirim';
                                            break;
                                        case 'gagal':
                                            $status_class = 'status-gagal';
                                            break;
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
                                        <td><?= htmlspecialchars($row['nama_kurir'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_pengiriman'] ?? ''))); ?></td>
                                        <td><span class="status-badge <?= $status_class; ?>"><?= htmlspecialchars(str_replace('_', ' ', ucwords($row['status_pengiriman'] ?? '-'))); ?></span></td>
                                        <td><?= htmlspecialchars($row['nomor_resi'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-pastel-purple mb-1" data-toggle="modal" data-target="#pengirimanModal"
                                                data-mode="edit"
                                                data-id="<?= $row['id']; ?>"
                                                data-pesanan_id="<?= $row['pesanan_id']; ?>"
                                                data-kurir_id="<?= $row['kurir_id']; ?>"
                                                data-tanggal_pengiriman="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($row['tanggal_pengiriman'] ?? ''))); ?>"
                                                data-status_pengiriman="<?= htmlspecialchars($row['status_pengiriman'] ?? ''); ?>"
                                                data-nomor_resi="<?= htmlspecialchars($row['nomor_resi'] ?? ''); ?>"
                                                data-keterangan="<?= htmlspecialchars($row['keterangan'] ?? ''); ?>">
                                                Edit
                                            </button>
                                            <a href="pengiriman.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus pengiriman ini?');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>Tidak ada data pengiriman.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation example" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1) : ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1; ?>">Previous</a></li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages) : ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1; ?>">Next</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="pengirimanModal" tabindex="-1" aria-labelledby="pengirimanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="pengiriman.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="pengirimanModalLabel">Tambah Pengiriman Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="pengiriman_id" id="pengiriman_id">

                    <div class="form-group">
                        <label for="pesanan_id">Pesanan</label>
                        <select class="form-control" id="pesanan_id" name="pesanan_id" required>
                            <option value="">Pilih Pesanan</option>
                            <?php foreach ($pesanan_list as $pesanan) : ?>
                                <option value="<?= htmlspecialchars($pesanan['id'] ?? ''); ?>">
                                    <?= htmlspecialchars($pesanan['kode_unik'] ?? 'N/A'); ?> (Pelanggan: <?= htmlspecialchars($pesanan['nama_pelanggan'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="kurir_id">Kurir</label>
                        <select class="form-control" id="kurir_id" name="kurir_id" required>
                            <option value="">Pilih Kurir</option>
                            <?php foreach ($kurir_list as $kurir) : ?>
                                <option value="<?= htmlspecialchars($kurir['id'] ?? ''); ?>"><?= htmlspecialchars($kurir['nama'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_pengiriman">Tanggal & Waktu Pengiriman</label>
                        <input type="datetime-local" class="form-control" id="tanggal_pengiriman" name="tanggal_pengiriman" required>
                    </div>
                    <div class="form-group">
                        <label for="status_pengiriman">Status Pengiriman</label>
                        <select class="form-control" id="status_pengiriman" name="status_pengiriman" required>
                            <option value="menunggu_konfirmasi">Menunggu Konfirmasi</option>
                            <option value="diproses">Diproses</option>
                            <option value="dikirim">Dikirim</option>
                            <option value="terkirim">Terkirim</option>
                            <option value="gagal">Gagal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nomor_resi">Nomor Resi (Opsional)</label>
                        <input type="text" class="form-control" id="nomor_resi" name="nomor_resi" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan_pengiriman" class="btn btn-primary">Simpan</button>
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

    // JavaScript untuk mengisi modal tambah/edit pengiriman
    $('#pengirimanModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var mode = button.data('mode'); // 'tambah' or 'edit'

        var modal = $(this);
        var modalTitle = modal.find('.modal-title');
        var pengirimanIdInput = modal.find('#pengiriman_id');
        var pesananIdSelect = modal.find('#pesanan_id');
        var kurirIdSelect = modal.find('#kurir_id');
        var tanggalPengirimanInput = modal.find('#tanggal_pengiriman');
        var statusPengirimanSelect = modal.find('#status_pengiriman');
        var nomorResiInput = modal.find('#nomor_resi');
        var keteranganInput = modal.find('#keterangan');

        // Reset form
        modal.find('form').trigger('reset');
        pengirimanIdInput.val('');


        if (mode === 'tambah') {
            modalTitle.text('Tambah Pengiriman Baru');
            // Set tanggal_pengiriman ke tanggal dan waktu sekarang secara default
            var now = new Date();
            var year = now.getFullYear();
            var month = (now.getMonth() + 1).toString().padStart(2, '0');
            var day = now.getDate().toString().padStart(2, '0');
            var hours = now.getHours().toString().padStart(2, '0');
            var minutes = now.getMinutes().toString().padStart(2, '0');
            tanggalPengirimanInput.val(year + '-' + month + '-' + day + 'T' + hours + ':' + minutes);

        } else if (mode === 'edit') {
            modalTitle.text('Edit Pengiriman');
            var id = button.data('id');
            var pesanan_id = button.data('pesanan_id');
            var kurir_id = button.data('kurir_id');
            var tanggal_pengiriman = button.data('tanggal_pengiriman');
            var status_pengiriman = button.data('status_pengiriman');
            var nomor_resi = button.data('nomor_resi');
            var keterangan = button.data('keterangan');

            pengirimanIdInput.val(id);
            pesananIdSelect.val(pesanan_id);
            kurirIdSelect.val(kurir_id);
            tanggalPengirimanInput.val(tanggal_pengiriman); // datetime-local format 'YYYY-MM-DDTHH:mm'
            statusPengirimanSelect.val(status_pengiriman);
            nomorResiInput.val(nomor_resi);
            keteranganInput.val(keterangan);
        }
    });
</script>
</body>
</html>