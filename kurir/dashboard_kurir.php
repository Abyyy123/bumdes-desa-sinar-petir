<?php
session_start();
include('../koneksi/koneksi.php'); // Pastikan path ini benar

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../login.php'); // Redirect ke halaman login jika belum login
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID kurir yang login

// Ambil data profil kurir dari tabel 'kurir'
$query_kurir = "SELECT * FROM kurir WHERE id = ?";
$stmt_kurir = mysqli_prepare($conn, $query_kurir);
// Cek jika prepared statement berhasil
if (!$stmt_kurir) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_kurir, 'i', $user_id);
mysqli_stmt_execute($stmt_kurir);
$result_kurir = mysqli_stmt_get_result($stmt_kurir);
$user = mysqli_fetch_assoc($result_kurir);

// Jika kurir tidak ditemukan atau statusnya tidak 'aktif', redirect ke login
if (!$user || $user['status'] !== 'aktif') {
    session_destroy(); // Hancurkan sesi
    header('Location: ../login.php?error=invalid_kurir_status'); // Redirect dengan pesan error
    exit;
}

// --- LOGIKA UPDATE PROFIL KURIR ---
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $password_baru = $_POST['password'];
    $password_hash = $user['password']; // Default ke password lama yang sudah di-hash

    // Hanya update password jika ada input password baru
    if (!empty($password_baru)) {
        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
    }

    $foto_lama = $user['foto'];
    $foto_baru = $foto_lama; // Inisialisasi dengan foto lama

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../img/kurir/';
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Hapus foto lama jika ada, berbeda dengan yang baru, dan bukan 'default.png'
            if ($foto_lama && file_exists($upload_dir . $foto_lama) && $foto_lama != $foto_name && $foto_lama != 'default.png') {
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
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi error saat mengunggah foto: " . $_FILES['foto']['error'] . "');</script>";
    }

    // Update data di tabel 'kurir'
    // Perbaikan binding parameter: 'ssssssi' (nama, username, email, nomor_telepon, password, foto, id)
    $update = "UPDATE kurir SET nama=?, username=?, email=?, nomor_telepon=?, password=?, foto=? WHERE id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    if (!$stmt_update) {
        die("Error preparing update statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_update, 'ssssssi', $nama, $username, $email, $nomor_telepon, $password_hash, $foto_baru, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        // Refresh data pengguna setelah update
        $query_kurir_refresh = "SELECT * FROM kurir WHERE id = ?";
        $stmt_kurir_refresh = mysqli_prepare($conn, $query_kurir_refresh);
        mysqli_stmt_bind_param($stmt_kurir_refresh, 'i', $user_id);
        mysqli_stmt_execute($stmt_kurir_refresh);
        $result_kurir_refresh = mysqli_stmt_get_result($stmt_kurir_refresh);
        $user = mysqli_fetch_assoc($result_kurir_refresh);
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='dashboard_kurir.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- DATA SPESIFIK UNTUK DASHBOARD KURIR ---
// Total Pengiriman Hari Ini (Status Selesai)
// Menggunakan tanggal_pengiriman_selesai dan status 'selesai'
$sql_pengiriman_hari_ini = "SELECT COUNT(*) AS total FROM pesanan WHERE kurir_id = ? AND status_pesanan = 'selesai' AND DATE(tanggal_pesanan) = CURDATE()";
$stmt_ph_ini = mysqli_prepare($conn, $sql_pengiriman_hari_ini);
mysqli_stmt_bind_param($stmt_ph_ini, 'i', $user_id);
mysqli_stmt_execute($stmt_ph_ini);
$result_ph_ini = mysqli_stmt_get_result($stmt_ph_ini);
$total_pengiriman_hari_ini = mysqli_fetch_assoc($result_ph_ini)['total'] ?? 0;

// Total Pendapatan Hari Ini (Asumsi dari ongkos_kirim pesanan selesai)
// Menggunakan ongkos_kirim dan tanggal_pengiriman_selesai
$sql_pendapatan_hari_ini = "SELECT SUM(ongkos_kirim) AS total FROM pesanan WHERE kurir_id = ? AND status_pesanan = 'selesai' AND DATE(tanggal_pesanan) = CURDATE()";
$stmt_pd_ini = mysqli_prepare($conn, $sql_pendapatan_hari_ini);
mysqli_stmt_bind_param($stmt_pd_ini, 'i', $user_id);
mysqli_stmt_execute($stmt_pd_ini);
$result_pd_ini = mysqli_stmt_get_result($stmt_pd_ini);
$total_pendapatan_hari_ini = mysqli_fetch_assoc($result_pd_ini)['total'] ?? 0;

// Total Pesanan Aktif (Ditugaskan atau Dikirim)
// Menggunakan status_pesanan yang ada di ENUM atau yang baru ditambahkan
$sql_pesanan_aktif_count = "SELECT COUNT(*) AS total FROM pesanan WHERE kurir_id = ? AND status_pesanan IN ('diproses', 'dikirim')"; // 'ditugaskan' dan 'dalam_pengiriman' diasumsikan sebagai 'diproses'/'dikirim' jika ENUM tidak diubah
$stmt_pa_count = mysqli_prepare($conn, $sql_pesanan_aktif_count);
mysqli_stmt_bind_param($stmt_pa_count, 'i', $user_id);
mysqli_stmt_execute($stmt_pa_count);
$result_pa_count = mysqli_stmt_get_result($stmt_pa_count);
$total_pesanan_aktif = mysqli_fetch_assoc($result_pa_count)['total'] ?? 0;

// Total Pesanan Selesai (Keseluruhan)
$sql_pesanan_selesai_total = "SELECT COUNT(*) AS total FROM pesanan WHERE kurir_id = ? AND status_pesanan = 'selesai'";
$stmt_ps_total = mysqli_prepare($conn, $sql_pesanan_selesai_total);
mysqli_stmt_bind_param($stmt_ps_total, 'i', $user_id);
mysqli_stmt_execute($stmt_ps_total);
$result_ps_total = mysqli_stmt_get_result($stmt_ps_total);
$total_pesanan_selesai_overall = mysqli_fetch_assoc($result_ps_total)['total'] ?? 0;

// Daftar Pesanan Aktif (untuk tabel)
$query_pesanan_aktif = "SELECT
                            p.id AS pesanan_id,
                            p.tanggal_pesanan,
                            p.status_pesanan,
                            p.alamat_pengiriman,
                            p.ongkos_kirim, -- Menggunakan ongkos_kirim
                            pl.nama AS nama_pelanggan
                        FROM pesanan p
                        JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id -- Join ke pelanggan.pengguna_id
                        WHERE p.kurir_id = ? AND p.status_pesanan IN ('diproses', 'dikirim') -- Menggunakan status yang ada di ENUM
                        ORDER BY p.tanggal_pesanan ASC LIMIT 10";
$stmt_pesanan_aktif = mysqli_prepare($conn, $query_pesanan_aktif);
mysqli_stmt_bind_param($stmt_pesanan_aktif, 'i', $user_id);
mysqli_stmt_execute($stmt_pesanan_aktif);
$result_pesanan_aktif = mysqli_stmt_get_result($stmt_pesanan_aktif);

// Riwayat Pengiriman Terbaru
$query_riwayat_pengiriman = "SELECT
                                p.id AS pesanan_id,
                                p.tanggal_pesanan,
                                p.status_pesanan,
                                p.alamat_pengiriman,
                                p.ongkos_kirim, -- Menggunakan ongkos_kirim
                                pl.nama AS nama_pelanggan
                            FROM pesanan p
                            JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id -- Join ke pelanggan.pengguna_id
                            WHERE p.kurir_id = ? AND p.status_pesanan = 'selesai'
                            ORDER BY p.tanggal_pesanan DESC LIMIT 5"; // Menggunakan tanggal_pengiriman_selesai
$stmt_riwayat_pengiriman = mysqli_prepare($conn, $query_riwayat_pengiriman);
mysqli_stmt_bind_param($stmt_riwayat_pengiriman, 'i', $user_id);
mysqli_stmt_execute($stmt_riwayat_pengiriman);
$result_riwayat_pengiriman = mysqli_stmt_get_result($stmt_riwayat_pengiriman);

// Query untuk mendapatkan data jumlah pengiriman per bulan
$query_pengiriman_bulanan = "SELECT
                                MONTH(tanggal_pesanan) AS bulan,
                                YEAR(tanggal_pesanan) AS tahun, -- Tambahkan tahun untuk grafik multi-tahun
                                COUNT(*) AS total_pengiriman
                            FROM pesanan
                            WHERE kurir_id = ? AND status_pesanan = 'selesai' AND tanggal_pesanan IS NOT NULL
                            GROUP BY YEAR(tanggal_pesanan), MONTH(tanggal_pesanan)
                            ORDER BY tahun ASC, bulan ASC";

$stmt_grafik_pengiriman = mysqli_prepare($conn, $query_pengiriman_bulanan);
mysqli_stmt_bind_param($stmt_grafik_pengiriman, 'i', $user_id);
mysqli_stmt_execute($stmt_grafik_pengiriman);
$result_grafik_pengiriman = mysqli_stmt_get_result($stmt_grafik_pengiriman);
$bulan_grafik = [];
$total_pengiriman_grafik = [];

while ($row_grafik = mysqli_fetch_assoc($result_grafik_pengiriman)) {
    // Lebih baik tampilkan bulan dan tahun untuk kejelasan grafik
    $bulan_grafik[] = $row_grafik['bulan'] . '-' . $row_grafik['tahun'];
    $total_pengiriman_grafik[] = $row_grafik['total_pengiriman'];
}

// --- AKHIR DATA SPESIFIK UNTUK DASHBOARD KURIR ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kurir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Warna Mocha Cream */
        :root {
            --mocha: #4B3832;
            --cream: #FFF8DC;
            --light-mocha: #6F4E37;
            --beige: #D2B48C;
            --light-beige: #EDE0C7;
            --text-dark: #333333;
            --text-light: #ffffff;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: var(--cream); /* Latar belakang utama */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark); /* Warna teks umum */
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
            background-color: var(--mocha); /* Sidebar mocha */
            min-height: 100vh;
            padding: 20px 0;
            color: var(--text-light);
            transition: width 0.3s ease;
            position: sticky;
            top: 0;
            left: 0;
            overflow-y: auto;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: var(--cream);
            margin-bottom: 30px;
        }
        .sidebar a {
            color: var(--light-beige);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: var(--light-mocha);
            color: var(--text-light);
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: var(--beige);
        }
        .sidebar .submenu:hover {
            color: var(--text-light);
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
            background-color: var(--cream); /* Konten utama cream */
        }
        .toggle-btn {
            background: none;
            border: none;
            color: var(--cream); /* Warna tombol toggle */
            margin-left: 20px;
            font-size: 20px;
        }
        /* Adjusted for .ml-collapsed if needed */
        .content.ml-collapsed {
             margin-left: 80px; /* Adjust content position when sidebar is collapsed */
        }

        .navbar {
            background-color: var(--mocha) !important; /* Navbar mocha */
            color: var(--text-light);
        }
        .navbar .navbar-brand,
        .navbar .nav-link {
            color: var(--cream) !important;
        }
        .navbar .nav-link.dropdown-toggle {
            color: var(--cream) !important;
        }
        .navbar .dropdown-menu {
            background-color: var(--light-beige);
            color: var(--text-dark);
        }
        .navbar .dropdown-item {
            color: var(--text-dark);
        }
        .navbar .dropdown-item:hover {
            background-color: var(--beige);
        }

        .card-dashboard {
            border-radius: 10px;
            min-height: 120px; /* Memberikan tinggi minimum untuk konsistensi */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .card-dashboard .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .card-dashboard .card-title {
            margin-bottom: 5px;
            font-size: 1rem;
        }
        .card-dashboard .card-text {
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Warna kartu dashboard */
        .card-dashboard.bg-primary { background-color: var(--light-mocha) !important; }
        .card-dashboard.bg-success { background-color: var(--beige) !important; }
        .card-dashboard.bg-info { background-color: var(--mocha) !important; }
        .card-dashboard.bg-warning { background-color: var(--light-beige) !important; }

        .card-dashboard.bg-primary,
        .card-dashboard.bg-info {
            color: var(--text-light) !important;
        }
        .card-dashboard.bg-success,
        .card-dashboard.bg-warning {
            color: var(--text-dark) !important;
        }


        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative; /* Non-sticky on small screens */
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline; /* Tampilkan teks di sidebar jika collapsed di mobile */
            }
            .content.ml-collapsed {
                margin-left: 0; /* No margin-left on mobile */
            }
            .toggle-btn {
                margin-left: 10px;
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
            background: var(--light-beige); /* Profil menu cream */
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            color: var(--text-dark);
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
            color: var(--mocha); /* Link di profil menu */
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil,
        .card.shadow {
            background: var(--light-beige); /* Form edit profil dan kartu */
            color: var(--text-dark);
        }
        .table {
            color: var(--text-dark);
        }
        .table-hover tbody tr:hover {
            background-color: var(--beige);
        }
        .badge.bg-primary, .badge.bg-info {
            background-color: var(--mocha) !important;
            color: var(--text-light) !important;
        }
        .badge.bg-success {
            background-color: var(--beige) !important;
            color: var(--text-dark) !important;
        }
        .btn-primary {
            background-color: var(--mocha);
            border-color: var(--mocha);
            color: var(--text-light);
        }
        .btn-primary:hover {
            background-color: var(--light-mocha);
            border-color: var(--light-mocha);
        }
        .btn-outline-primary {
            color: var(--mocha);
            border-color: var(--mocha);
        }
        .btn-outline-primary:hover {
            background-color: var(--mocha);
            color: var(--text-light);
        }
        .btn-secondary {
            background-color: var(--beige);
            border-color: var(--beige);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
        }

        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        #deliveryChart {
            width: 100% !important;
            max-width: 600px !important; /* Sesuaikan jika perlu */
            height: 300px !important;
        }
        footer {
            background-color: var(--mocha) !important;
            color: var(--cream) !important;
        }
        footer a {
            color: var(--light-beige) !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto Profil" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline text-white">Profil</span>
        </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
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
                        <span aria-hidden="true">&times;</span>
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
                        <label>Nomor Telepon</label>
                        <input type="text" name="nomor_telepon" class="form-control" value="<?= htmlspecialchars($user['nomor_telepon'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($user['foto'] && $user['foto'] != 'default.png') : // Hanya tampilkan jika ada foto dan bukan default ?>
                            <img src="../img/kurir/<?= htmlspecialchars($user['foto']) ?>" width="80" class="mb-2 rounded"><br>
                        <?php else: ?>
                            <img src="../img/foto/default.png" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
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
                <a class="nav-link" href="dashboard_kurir.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pesanan/pesanan_kurir.php">
                    <i class="fas fa-box-open"></i>
                    <span class="ml-2">Tugas Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="riwayat/riwayat_pengiriman.php">
                    <i class="fas fa-history"></i>
                    <span class="ml-2">Riwayat Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="invoice/invoice_kurir.php"> <i class="fas fa-file-invoice"></i> <span class="ml-2">Invoice</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="laporan/laporan_kinerja.php">
                    <i class="fas fa-chart-bar"></i>
                    <span class="ml-2">Laporan Kinerja</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#editProfilModal">
                    <i class="fas fa-user-circle"></i>
                    <span class="ml-2">Pengaturan Profil</span>
                </a>
            </li>
        </ul>
        <a href="../logout.php" class="text-danger mt-3 d-block pl-3"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <h1>Dashboard Kurir</h1>
        <h3>Selamat datang, <?= htmlspecialchars($user['nama']); ?>!</h3>
        <p>Anda login sebagai <strong>Kurir</strong>.</p>

        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-truck mr-2"></i> Pengiriman Hari Ini</h5>
                        <p class="card-text"><?= $total_pengiriman_hari_ini ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dashboard bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-wallet mr-2"></i> Pendapatan Hari Ini</h5>
                        <p class="card-text">Rp. <?= number_format($total_pendapatan_hari_ini, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dashboard bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clipboard-list mr-2"></i> Pesanan Aktif</h5>
                        <p class="card-text"><?= $total_pesanan_aktif ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dashboard bg-warning text-dark shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-check-circle mr-2"></i> Pesanan Selesai (Total)</h5>
                        <p class="card-text"><?= $total_pesanan_selesai_overall ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks mr-2"></i> Pesanan Aktif Anda</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Pesanan</th>
                                        <th>Pelanggan</th>
                                        <th>Alamat</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result_pesanan_aktif) > 0) : ?>
                                        <?php while ($pesanan = mysqli_fetch_assoc($result_pesanan_aktif)) : ?>
                                            <tr>
                                                <td><?= htmlspecialchars($pesanan['pesanan_id']) ?></td>
                                                <td><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></td>
                                                <td><?= htmlspecialchars(substr($pesanan['alamat_pengiriman'], 0, 30)) ?>...</td>
                                                <td><span class="badge
                                                    <?php
                                                         // Sesuaikan kelas badge dengan status ENUM yang sebenarnya
                                                        if($pesanan['status_pesanan'] == 'diproses') echo 'bg-primary text-white';
                                                        else if($pesanan['status_pesanan'] == 'dikirim') echo 'bg-info text-white';
                                                        else echo 'bg-secondary text-white'; // Fallback
                                                    ?>">
                                                    <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['status_pesanan']))) ?>
                                                </span></td>
                                                <td>
                                                    <a href="pesanan/detail_pesanan_kurir.php?id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Tidak ada pesanan aktif saat ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="pesanan/pesanan_kurir.php" class="btn btn-primary btn-sm mt-3">Lihat Semua Tugas</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-history mr-2"></i> Riwayat Pengiriman Terbaru</h5>
                        <ul class="list-group list-group-flush">
                            <?php if (mysqli_num_rows($result_riwayat_pengiriman) > 0) : ?>
                                <?php while ($riwayat = mysqli_fetch_assoc($result_riwayat_pengiriman)) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>#<?= htmlspecialchars($riwayat['pesanan_id']) ?></strong> ke <?= htmlspecialchars($riwayat['nama_pelanggan']) ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars(substr($riwayat['alamat_pengiriman'], 0, 40)) ?>...</small>
                                        </div>
                                        <span class="badge bg-success text-white">Selesai</span>
                                    </li>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <li class="list-group-item text-center">Belum ada riwayat pengiriman.</li>
                            <?php endif; ?>
                        </ul>
                        <a href="riwayat/riwayat_pengiriman.php" class="btn btn-secondary btn-sm mt-3">Lihat Semua Riwayat</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line mr-2"></i> Grafik Jumlah Pengiriman Selesai per Bulan</h5>
                        <canvas id="deliveryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-auto">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        // Optional: Toggle a class on content to adjust its margin
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    $(document).ready(function() {
        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });
    });

    // Grafik Pengiriman Bulanan
    var bulan_grafik = <?php echo json_encode($bulan_grafik); ?>;
    var total_pengiriman_grafik = <?php echo json_encode($total_pengiriman_grafik); ?>;
    var ctx = document.getElementById('deliveryChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: bulan_grafik.map(function(m){
                // Memisahkan bulan dan tahun jika formatnya "MM-YYYY"
                const parts = m.split('-');
                const monthNum = parseInt(parts[0]);
                const year = parts[1];

                const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
                    "Juli", "Agustus", "September", "Oktober", "November", "Desember"
                ];
                return monthNames[monthNum - 1] + ' ' + year;
            }),
            datasets: [{
                label: 'Jumlah Pengiriman Selesai',
                data: total_pengiriman_grafik,
                backgroundColor: 'rgba(75, 192, 192, 0.8)', // Anda bisa ubah warna batang grafik di sini
                borderColor: 'rgba(75, 192, 192, 1)', // Warna border batang grafik
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Penting agar grafik bisa menyesuaikan ukuran
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (Number.isInteger(value)) { // Hanya tampilkan bilangan bulat untuk jumlah pengiriman
                                return value + ' pengiriman';
                            }
                            return ''; // Jangan tampilkan pecahan
                        }
                    }
                },
                x: {
                    grid: {
                        display: false // Sembunyikan grid vertikal
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: 'var(--text-dark)' // Warna teks legend
                    }
                }
            }
        }
    });
</script>
</body>
</html>