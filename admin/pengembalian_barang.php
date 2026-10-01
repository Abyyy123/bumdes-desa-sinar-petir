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

            echo "<script>alert('Profil berhasil diperbarui!');window.location.href='pengembalian_barang.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_profil);
    } else {
        echo "<script>alert('Gagal menyiapkan statement profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Update Status / Tindakan Pengembalian ---
if (isset($_POST['update_status'])) {
    $id_pengajuan = $_POST['pengajuan_id'];
    $new_status = $_POST['status_pengembalian'];
    $catatan_admin = $_POST['catatan_admin'];
    $tindakan_pengembalian = $_POST['tindakan_pengembalian'] ?? null;
    $detail_tindakan = $_POST['detail_tindakan'] ?? null;

    $set_tanggal = "";
    $params = [$new_status, $catatan_admin];
    $types = "ss";

    // Validasi status baru
    $valid_statuses = ['diajukan', 'disetujui', 'ditolak', 'diproses', 'selesai', 'dikembalikan_pembeli'];
    if (!in_array($new_status, $valid_statuses)) {
        echo "<script>alert('Status yang dipilih tidak valid.');window.location.href='pengembalian_barang.php';</script>";
        exit;
    }

    if ($new_status === 'disetujui') {
        $set_tanggal .= ", tanggal_persetujuan = NOW()";
        if ($tindakan_pengembalian) {
            $set_tanggal .= ", tindakan_pengembalian = ?, detail_tindakan = ?";
            $params[] = $tindakan_pengembalian;
            $params[] = $detail_tindakan;
            $types .= "ss";
        }
    } elseif ($new_status === 'ditolak') {
        $set_tanggal .= ", tanggal_penolakan = NOW()";
        // Reset tindakan dan detail jika ditolak
        $set_tanggal .= ", tindakan_pengembalian = NULL, detail_tindakan = NULL";
    } elseif ($new_status === 'selesai') {
        // Jika status menjadi selesai, pastikan tindakan dan detail sudah ada jika sebelumnya disetujui
        // atau set null jika belum ada. Asumsi 'selesai' adalah akhir dari proses yang disetujui.
        // Kita tidak mengubah tanggal persetujuan/penolakan di sini, hanya updated_at.
    } elseif ($new_status === 'dikembalikan_pembeli') {
         // Ini adalah status perantara ketika pembeli telah mengembalikan barang,
         // menunggu konfirmasi penjual/admin untuk diproses lebih lanjut
    } else {
        // Untuk status lain (diajukan, diproses), kita tidak mengubah tanggal persetujuan/penolakan
        // dan tidak mereset tindakan/detail tindakan jika sudah ada.
    }


    $update_query = "UPDATE pengembalian_barang SET status_pengembalian = ?, catatan_admin = ? $set_tanggal, updated_at = NOW() WHERE id = ?";
    $params[] = $id_pengajuan;
    $types .= "i";

    $stmt_update = mysqli_prepare($conn, $update_query);
    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, $types, ...$params);
        if (mysqli_stmt_execute($stmt_update)) {
            echo "<script>alert('Status pengajuan berhasil diperbarui!');window.location.href='pengembalian_barang.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui status pengajuan: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_update);
    } else {
        echo "<script>alert('Gagal menyiapkan statement update: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Logika Hapus Pengajuan ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = $_GET['id'];

    // Hapus bukti terkait terlebih dahulu (video dan foto)
    $query_get_bukti = "SELECT bukti_video_cacat, bukti_foto_cacat FROM pengembalian_barang WHERE id = ?";
    $stmt_get_bukti = mysqli_prepare($conn, $query_get_bukti);
    mysqli_stmt_bind_param($stmt_get_bukti, 'i', $id_hapus);
    mysqli_stmt_execute($stmt_get_bukti);
    $result_bukti = mysqli_stmt_get_result($stmt_get_bukti);
    $bukti = mysqli_fetch_assoc($result_bukti);
    mysqli_stmt_close($stmt_get_bukti);

    if ($bukti) {
        $upload_dir = '../img/bukti_pengembalian/';
        // --- PERUBAHAN DI SINI ---
        // Bersihkan path: hapus "bukti_pengembalian/" jika sudah menjadi bagian dari nilai yang tersimpan
        $clean_bukti_video = str_replace('bukti_pengembalian/', '', $bukti['bukti_video_cacat']);
        $clean_bukti_foto = str_replace('bukti_pengembalian/', '', $bukti['bukti_foto_cacat']);

        if (!empty($clean_bukti_video) && file_exists($upload_dir . $clean_bukti_video)) {
            unlink($upload_dir . $clean_bukti_video);
        }
        if (!empty($clean_bukti_foto) && file_exists($upload_dir . $clean_bukti_foto)) {
            unlink($upload_dir . $clean_bukti_foto);
        }
    }

    $delete_query = "DELETE FROM pengembalian_barang WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    if ($stmt_delete) {
        mysqli_stmt_bind_param($stmt_delete, 'i', $id_hapus);
        if (mysqli_stmt_execute($stmt_delete)) {
            echo "<script>alert('Pengajuan pengembalian barang berhasil dihapus!');window.location.href='pengembalian_barang.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus pengajuan: " . mysqli_error($conn) . "');</script>";
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo "<script>alert('Gagal menyiapkan statement hapus: " . mysqli_error($conn) . "');</script>";
    }
}

// --- Query untuk mengambil semua data pengajuan pengembalian barang ---
// Ditambahkan LEFT JOIN ke detail_pesanan dan produk untuk mendapatkan nama produk dan penjualnya.
// Tapi karena ini di admin, kita tidak memfilter berdasarkan penjual_id.
// Kolom kode_unik ada di tabel pesanan, bukan di pengembalian_barang.

$sql_pengembalian = "SELECT
                        pb.id,
                        pb.pesanan_id,
                        pb.pelanggan_id,
                        pb.tanggal_pengajuan,
                        pb.alasan_pengembalian,
                        pb.bukti_video_cacat,
                        pb.bukti_foto_cacat,
                        pb.status_pengembalian,
                        pb.tanggal_persetujuan,
                        pb.tanggal_penolakan,
                        pb.catatan_admin,
                        pb.tindakan_pengembalian,
                        pb.detail_tindakan,
                        p.kode_unik AS nomor_pesanan, -- Mengambil kode_unik dari tabel pesanan
                        u.nama AS nama_pelanggan,
                        GROUP_CONCAT(DISTINCT pr.nama SEPARATOR ', ') AS produk_terkait,
                        GROUP_CONCAT(DISTINCT penj.nama_toko SEPARATOR ', ') AS nama_penjual
                    FROM
                        pengembalian_barang pb
                    LEFT JOIN
                        pesanan p ON pb.pesanan_id = p.id
                    LEFT JOIN
                        pengguna u ON pb.pelanggan_id = u.id
                    LEFT JOIN
                        detail_pesanan dp ON p.id = dp.pesanan_id
                    LEFT JOIN
                        produk pr ON dp.produk_id = pr.id
                    LEFT JOIN
                        penjual penj ON pr.penjual_id = penj.pengguna_id
                    GROUP BY
                        pb.id, pb.pesanan_id, pb.pelanggan_id, pb.tanggal_pengajuan, pb.alasan_pengembalian, pb.bukti_video_cacat, pb.bukti_foto_cacat, pb.status_pengembalian, pb.tanggal_persetujuan, pb.tanggal_penolakan, pb.catatan_admin, pb.tindakan_pengembalian, pb.detail_tindakan, p.kode_unik, u.nama
                    ORDER BY
                        pb.tanggal_pengajuan DESC";
$result_pengembalian = mysqli_query($conn, $sql_pengembalian);

// Array untuk mapping status ke kelas CSS badge dan teks yang lebih mudah dibaca
$status_map = [
    'diajukan' => ['text' => 'Diajukan', 'class' => 'badge-info'],
    'disetujui' => ['text' => 'Disetujui', 'class' => 'badge-success'],
    'ditolak' => ['text' => 'Ditolak', 'class' => 'badge-danger'],
    'diproses' => ['text' => 'Diproses', 'class' => 'badge-warning'],
    'selesai' => ['text' => 'Selesai', 'class' => 'badge-primary'],
    'dikembalikan_pembeli' => ['text' => 'Dikembalikan Pembeli', 'class' => 'badge-secondary'],
];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pengembalian Barang</title>
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
        .custom-card {
        height: 80px;         /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;   /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;     /* jarak luar card */
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

        /* Status Badge Styling */
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            white-space: nowrap;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .badge-info { background-color: #17a2b8; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #333; } /* For better visibility */
        .badge-primary { background-color: #007bff; }
        .badge-secondary { background-color: #6c757d; }

        /* --- STYLING UTAMA UNTUK TABEL PENGEMBALIAN BARANG --- */
        .table-pengembalian {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
            overflow: hidden !important;
            display: table !important;
        }

        .table-pengembalian thead { display: table-header-group !important; }
        .table-pengembalian tbody { display: table-row-group !important; }
        .table-pengembalian tr { display: table-row !important; }
        .table-pengembalian th,
        .table-pengembalian td {
            display: table-cell !important;
            padding: 0.3rem 0.5rem !important;
            vertical-align: middle !important;
            font-size: 0.75rem !important;
            box-sizing: border-box !important;
            border: 1px solid #dee2e6 !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .table-pengembalian th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            white-space: nowrap !important;
            font-weight: bold !important;
        }

        /* Atur lebar setiap kolom secara eksplisit */
        /* Urutan kolom: No., ID, Pelanggan, No. Pesanan, Tgl Pengajuan, Alasan, Status, Aksi */
        .table-pengembalian td:nth-child(1), .table-pengembalian th:nth-child(1) { /* No. */
            width: 4% !important;
            min-width: 30px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengembalian td:nth-child(2), .table-pengembalian th:nth-child(2) { /* ID */
            width: 5% !important;
            min-width: 40px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengembalian td:nth-child(3), .table-pengembalian th:nth-child(3) { /* Pelanggan */
            width: 15% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
        }
        .table-pengembalian td:nth-child(4), .table-pengembalian th:nth-child(4) { /* No. Pesanan */
            width: 10% !important;
            min-width: 80px !important;
            white-space: nowrap !important;
            text-align: center !important;
        }
        .table-pengembalian td:nth-child(5), .table-pengembalian th:nth-child(5) { /* Tgl Pengajuan */
            width: 12% !important;
            min-width: 90px !important;
            white-space: nowrap !important;
        }
        .table-pengembalian td:nth-child(6), .table-pengembalian th:nth-child(6) { /* Alasan */
            width: 20% !important;
            min-width: 150px !important;
            max-width: 200px !important; /* Batasi lebar agar tidak terlalu panjang */
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: normal !important; /* Izinkan wrapping */
        }
        .table-pengembalian td:nth-child(7), .table-pengembalian th:nth-child(7) { /* Penjual Terkait */
            width: 15% !important;
            min-width: 100px !important;
            white-space: nowrap !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }
        .table-pengembalian td:nth-child(8), .table-pengembalian th:nth-child(8) { /* Status */
            width: 10% !important;
            min-width: 80px !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-pengembalian td:nth-child(9), .table-pengembalian th:nth-child(9) { /* Aksi */
            width: 10% !important;
            min-width: 70px !important;
            text-align: center !important;
            white-space: normal !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 3px !important;
        }
        .table-pengembalian td a, .table-pengembalian td button {
            white-space: nowrap !important;
        }


        /* Penyesuaian tombol di kolom Aksi */
        .table-pengembalian .btn-sm {
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
            .table-pengembalian th,
            .table-pengembalian td {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.45rem !important;
            }
            .table-pengembalian td:nth-child(6) { /* Alasan */
                max-width: 180px !important;
            }
        }

        @media (max-width: 992px) { /* Untuk layar tablet */
            .table-pengembalian th,
            .table-pengembalian td {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.35rem !important;
            }
            .table-pengembalian td:nth-child(6) { /* Alasan */
                max-width: 150px !important;
            }
            .table-pengembalian td:nth-child(9) { /* Aksi */
                width: 12% !important;
                min-width: 80px !important;
            }
            .table-pengembalian .btn-sm {
                max-width: 50px !important;
            }
        }

        @media (max-width: 767px) { /* Untuk layar ponsel */
            .table-pengembalian th,
            .table-pengembalian td {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.25rem !important;
            }
            .table-pengembalian td:nth-child(6) { /* Alasan */
                max-width: 120px !important;
            }
            .table-pengembalian td:nth-child(9) { /* Aksi */
                width: 15% !important;
                min-width: 60px !important;
            }
            .table-pengembalian .btn-sm {
                font-size: 0.55rem !important;
                padding: 0.08rem 0.15rem !important;
                max-width: 40px !important;
            }
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
    <a class="nav-link active" href="pengembalian_barang.php">
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
        <h2 class="mb-4">Manajemen Pengembalian Barang</h2>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                Daftar Pengajuan Pengembalian Barang
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-pengembalian">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>No. Pesanan</th>
                                <th>Tgl Pengajuan</th>
                                <th>Alasan</th>
                                <th>Penjual Terkait</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($result_pengembalian) > 0) {
                                while ($row = mysqli_fetch_assoc($result_pengembalian)) {
                                    $status_info = $status_map[$row['status_pengembalian']] ?? ['text' => 'Tidak Diketahui', 'class' => 'badge-secondary'];
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['id'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['nomor_pesanan'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['tanggal_pengajuan'] ?? ''))); ?></td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 150px;">
                                                <?= htmlspecialchars($row['alasan_pengembalian'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 100px;">
                                                <?= htmlspecialchars($row['nama_penjual'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge <?= $status_info['class']; ?> status-badge"><?= htmlspecialchars($status_info['text']); ?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info mb-1" data-toggle="modal" data-target="#detailModal"
                                                data-id="<?= $row['id']; ?>"
                                                data-pesanan_id="<?= $row['pesanan_id']; ?>"
                                                data-nomor_pesanan="<?= htmlspecialchars($row['nomor_pesanan'] ?? 'N/A'); ?>"
                                                data-pelanggan_id="<?= $row['pelanggan_id']; ?>"
                                                data-nama_pelanggan="<?= htmlspecialchars($row['nama_pelanggan'] ?? 'N/A'); ?>"
                                                data-tanggal_pengajuan="<?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['tanggal_pengajuan'] ?? ''))); ?>"
                                                data-alasan_pengembalian="<?= htmlspecialchars($row['alasan_pengembalian'] ?? ''); ?>"
                                                data-bukti_video_cacat="<?= htmlspecialchars($row['bukti_video_cacat'] ?? ''); ?>"
                                                data-bukti_foto_cacat="<?= htmlspecialchars($row['bukti_foto_cacat'] ?? ''); ?>"
                                                data-status_pengembalian="<?= htmlspecialchars($row['status_pengembalian'] ?? ''); ?>"
                                                data-tanggal_persetujuan="<?= htmlspecialchars($row['tanggal_persetujuan'] ? date('d-m-Y H:i:s', strtotime($row['tanggal_persetujuan'])) : '-'); ?>"
                                                data-tanggal_penolakan="<?= htmlspecialchars($row['tanggal_penolakan'] ? date('d-m-Y H:i:s', strtotime($row['tanggal_penolakan'])) : '-'); ?>"
                                                data-catatan_admin="<?= htmlspecialchars($row['catatan_admin'] ?? ''); ?>"
                                                data-tindakan_pengembalian="<?= htmlspecialchars($row['tindakan_pengembalian'] ?? ''); ?>"
                                                data-detail_tindakan="<?= htmlspecialchars($row['detail_tindakan'] ?? ''); ?>"
                                                data-produk_terkait="<?= htmlspecialchars($row['produk_terkait'] ?? 'N/A'); ?>"
                                                data-nama_penjual="<?= htmlspecialchars($row['nama_penjual'] ?? 'N/A'); ?>">
                                                Detail
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning mb-1" data-toggle="modal" data-target="#editStatusModal"
                                                data-id="<?= $row['id']; ?>"
                                                data-status_pengembalian="<?= htmlspecialchars($row['status_pengembalian'] ?? ''); ?>"
                                                data-catatan_admin="<?= htmlspecialchars($row['catatan_admin'] ?? ''); ?>"
                                                data-tindakan_pengembalian="<?= htmlspecialchars($row['tindakan_pengembalian'] ?? ''); ?>"
                                                data-detail_tindakan="<?= htmlspecialchars($row['detail_tindakan'] ?? ''); ?>">
                                                Edit Status
                                            </button>
                                            <a href="pengembalian_barang.php?action=hapus&id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus pengajuan ini? Tindakan ini tidak dapat dibatalkan!');">Hapus</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center'>Tidak ada pengajuan pengembalian barang.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Detail Pengajuan Pengembalian Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informasi Umum</h6>
                        <p><strong>ID Pengajuan:</strong> <span id="detail_id"></span></p>
                        <p><strong>Pelanggan:</strong> <span id="detail_nama_pelanggan"></span></p>
                        <p><strong>No. Pesanan:</strong> <span id="detail_nomor_pesanan"></span></p>
                        <p><strong>Tanggal Pengajuan:</strong> <span id="detail_tanggal_pengajuan"></span></p>
                        <p><strong>Produk Terkait:</strong> <span id="detail_produk_terkait"></span></p>
                        <p><strong>Penjual Terkait:</strong> <span id="detail_nama_penjual"></span></p>
                        <p><strong>Status:</strong> <span id="detail_status_pengembalian"></span></p>
                        <p><strong>Tanggal Persetujuan:</strong> <span id="detail_tanggal_persetujuan"></span></p>
                        <p><strong>Tanggal Penolakan:</strong> <span id="detail_tanggal_penolakan"></span></p>
                        <p><strong>Alasan Pengembalian:</strong> <br><span id="detail_alasan_pengembalian"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Bukti</h6>
                        <div id="bukti_video_container" class="mb-3" style="display:none;">
                            <strong>Bukti Video:</strong>
                            <video id="detail_bukti_video_cacat" controls style="width: 100%; max-height: 250px;"></video>
                            <a id="link_video_bukti" href="#" target="_blank" class="btn btn-sm btn-outline-primary mt-1">Lihat Video Asli</a>
                        </div>
                        <div id="bukti_foto_container" class="mb-3" style="display:none;">
                            <strong>Bukti Foto:</strong>
                            <img id="detail_bukti_foto_cacat" src="" alt="Bukti Foto Cacat" class="img-fluid rounded" style="max-height: 250px;">
                            <a id="link_foto_bukti" href="#" target="_blank" class="btn btn-sm btn-outline-primary mt-1">Lihat Foto Asli</a>
                        </div>
                        <div id="no_bukti_msg" class="text-muted" style="display:none;">
                            Tidak ada bukti video atau foto yang diunggah.
                        </div>

                        <h6 class="mt-4">Tindakan & Catatan Admin</h6>
                        <p><strong>Tindakan Pengembalian:</strong> <span id="detail_tindakan_pengembalian"></span></p>
                        <p><strong>Detail Tindakan:</strong> <br><span id="detail_detail_tindakan"></span></p>
                        <p><strong>Catatan Admin:</strong> <br><span id="detail_catatan_admin"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="pengembalian_barang.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStatusModalLabel">Edit Status Pengajuan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="pengajuan_id" id="edit_pengajuan_id">

                    <div class="form-group">
                        <label for="edit_status_pengembalian">Status Pengembalian</label>
                        <select class="form-control" id="edit_status_pengembalian" name="status_pengembalian" required>
                            <option value="diajukan">Diajukan</option>
                            <option value="disetujui">Disetujui</option>
                            <option value="ditolak">Ditolak</option>
                            <option value="diproses">Diproses</option>
                            <option value="selesai">Selesai</option>
                            <option value="dikembalikan_pembeli">Dikembalikan Pembeli</option>
                        </select>
                    </div>

                    <div class="form-group" id="tindakan_pengembalian_group" style="display:none;">
                        <label for="edit_tindakan_pengembalian">Tindakan Pengembalian (jika disetujui)</label>
                        <select class="form-control" id="edit_tindakan_pengembalian" name="tindakan_pengembalian">
                            <option value="">Pilih Tindakan</option>
                            <option value="ganti_baru">Ganti Baru</option>
                            <option value="refund">Refund Dana</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group" id="detail_tindakan_group" style="display:none;">
                        <label for="edit_detail_tindakan">Detail Tindakan</label>
                        <textarea class="form-control" id="edit_detail_tindakan" name="detail_tindakan" rows="3" placeholder="Misal: Nomor resi pengiriman barang baru, detail refund ke rekening..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit_catatan_admin">Catatan Admin</label>
                        <textarea class="form-control" id="edit_catatan_admin" name="catatan_admin" rows="3" placeholder="Tambahkan catatan internal admin terkait pengajuan ini"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_status" class="btn btn-primary">Simpan Perubahan</button>
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

    // JavaScript untuk mengisi modal detail pengajuan
    $('#detailModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal

        var id = button.data('id');
        var nomor_pesanan = button.data('nomor_pesanan');
        var nama_pelanggan = button.data('nama_pelanggan');
        var tanggal_pengajuan = button.data('tanggal_pengajuan');
        var alasan_pengembalian = button.data('alasan_pengembalian');
        var bukti_video_cacat = button.data('bukti_video_cacat');
        var bukti_foto_cacat = button.data('bukti_foto_cacat');
        var status_pengembalian_raw = button.data('status_pengembalian');
        var tanggal_persetujuan = button.data('tanggal_persetujuan');
        var tanggal_penolakan = button.data('tanggal_penolakan');
        var catatan_admin = button.data('catatan_admin');
        var tindakan_pengembalian = button.data('tindakan_pengembalian');
        var detail_tindakan = button.data('detail_tindakan');
        var produk_terkait = button.data('produk_terkait');
        var nama_penjual = button.data('nama_penjual');


        var modal = $(this);
        modal.find('#detail_id').text(id);
        modal.find('#detail_nomor_pesanan').text(nomor_pesanan);
        modal.find('#detail_nama_pelanggan').text(nama_pelanggan);
        modal.find('#detail_tanggal_pengajuan').text(tanggal_pengajuan);
        modal.find('#detail_alasan_pengembalian').text(alasan_pengembalian || '-');
        modal.find('#detail_tanggal_persetujuan').text(tanggal_persetujuan);
        modal.find('#detail_tanggal_penolakan').text(tanggal_penolakan);
        modal.find('#detail_catatan_admin').text(catatan_admin || '-');
        modal.find('#detail_tindakan_pengembalian').text(tindakan_pengembalian ? tindakan_pengembalian.replace('_', ' ').toUpperCase() : '-'); // Format teks
        modal.find('#detail_detail_tindakan').text(detail_tindakan || '-');
        modal.find('#detail_produk_terkait').text(produk_terkait || '-');
        modal.find('#detail_nama_penjual').text(nama_penjual || '-');


        // Update status badge
        var statusMap = <?= json_encode($status_map); ?>;
        var statusInfo = statusMap[status_pengembalian_raw] || {text: 'Tidak Diketahui', class: 'badge-secondary'};
        modal.find('#detail_status_pengembalian').html('<span class="badge ' + statusInfo.class + '">' + statusInfo.text + '</span>');

        // Handle bukti video dan foto
        var buktiVideoContainer = modal.find('#bukti_video_container');
        var buktiFotoContainer = modal.find('#bukti_foto_container');
        var noBuktiMsg = modal.find('#no_bukti_msg');

        buktiVideoContainer.hide();
        buktiFotoContainer.hide();
        noBuktiMsg.hide();

        // --- PERUBAHAN DI SINI ---
        // Bersihkan path: hapus "bukti_pengembalian/" jika sudah menjadi bagian dari nilai yang tersimpan
        var clean_bukti_video = bukti_video_cacat.replace('bukti_pengembalian/', '');
        var clean_bukti_foto = bukti_foto_cacat.replace('bukti_pengembalian/', '');


        if (clean_bukti_video) {
            modal.find('#detail_bukti_video_cacat').attr('src', '../img/bukti_pengembalian/' + clean_bukti_video);
            modal.find('#link_video_bukti').attr('href', '../img/bukti_pengembalian/' + clean_bukti_video);
            buktiVideoContainer.show();
        }
        if (clean_bukti_foto) {
            modal.find('#detail_bukti_foto_cacat').attr('src', '../img/bukti_pengembalian/' + clean_bukti_foto);
            modal.find('#link_foto_bukti').attr('href', '../img/bukti_pengembalian/' + clean_bukti_foto);
            buktiFotoContainer.show();
        }

        if (!clean_bukti_video && !clean_bukti_foto) { // Periksa clean_bukti_video dan clean_bukti_foto
            noBuktiMsg.show();
        }
    });

    // JavaScript untuk mengisi modal edit status pengajuan
$('#editStatusModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); // Button that triggered the modal

    var id = button.data('id');
    var status_pengembalian = button.data('status_pengembalian');
    var catatan_admin = button.data('catatan_admin');
    var tindakan_pengembalian = button.data('tindakan_pengembalian');
    var detail_tindakan = button.data('detail_tindakan');

    var modal = $(this);
    modal.find('#edit_pengajuan_id').val(id);
    modal.find('#edit_status_pengembalian').val(status_pengembalian);
    modal.find('#edit_catatan_admin').val(catatan_admin);
    modal.find('#edit_tindakan_pengembalian').val(tindakan_pengembalian);
    modal.find('#edit_detail_tindakan').val(detail_tindakan);

    // Panggil fungsi untuk mengontrol visibilitas tindakan/detail
    toggleTindakanOptions(status_pengembalian);
});

// Tambahkan event listener untuk perubahan status di modal
$('#edit_status_pengembalian').on('change', function() {
    toggleTindakanOptions($(this).val());
});

    // Fungsi untuk menampilkan/menyembunyikan opsi tindakan pengembalian
    function toggleTindakanOptions(status) {
        var tindakanGroup = $('#tindakan_pengembalian_group');
        var detailTindakanGroup = $('#detail_tindakan_group');
        var tindakanSelect = $('#edit_tindakan_pengembalian');
        var detailTindakanTextarea = $('#edit_detail_tindakan');

        if (status === 'disetujui') {
            tindakanGroup.show();
            detailTindakanGroup.show();
            tindakanSelect.attr('required', true);
            detailTindakanTextarea.attr('required', true);
        } else {
            tindakanGroup.hide();
            detailTindakanGroup.hide();
            tindakanSelect.removeAttr('required');
            detailTindakanTextarea.removeAttr('required');
            tindakanSelect.val(''); // Reset nilai jika tidak disetujui
            detailTindakanTextarea.val(''); // Reset nilai jika tidak disetujui
        }
    }
</script>
</body>
</html>