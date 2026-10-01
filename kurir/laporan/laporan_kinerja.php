<?php
session_start();
include('../../koneksi/koneksi.php'); // Pastikan path ini benar

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php'); // Redirect ke halaman login jika belum login
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
    header('Location: ../../login.php?error=invalid_kurir_status'); // Redirect dengan pesan error
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
        $upload_dir = '../../img/kurir/'; // Sesuaikan path ini dengan lokasi penyimpanan foto kurir
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
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='laporan_kinerja.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- DATA SPESIFIK UNTUK LAPORAN KINERJA KURIR ---

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

// Tambahan: Query untuk mendapatkan data pendapatan per bulan
$query_pendapatan_bulanan = "SELECT
                                MONTH(tanggal_pesanan) AS bulan,
                                YEAR(tanggal_pesanan) AS tahun,
                                SUM(ongkos_kirim) AS total_pendapatan
                            FROM pesanan
                            WHERE kurir_id = ? AND status_pesanan = 'selesai' AND tanggal_pesanan IS NOT NULL
                            GROUP BY YEAR(tanggal_pesanan), MONTH(tanggal_pesanan)
                            ORDER BY tahun ASC, bulan ASC";

$stmt_grafik_pendapatan = mysqli_prepare($conn, $query_pendapatan_bulanan);
mysqli_stmt_bind_param($stmt_grafik_pendapatan, 'i', $user_id);
mysqli_stmt_execute($stmt_grafik_pendapatan);
$result_grafik_pendapatan = mysqli_stmt_get_result($stmt_grafik_pendapatan);
$pendapatan_grafik = [];

while ($row_pendapatan = mysqli_fetch_assoc($result_grafik_pendapatan)) {
    // Pastikan urutan bulan sama dengan bulan_grafik agar sinkron
    // Jika tidak, perlu penyesuaian logika atau penggabungan data di PHP
    $pendapatan_grafik[] = $row_pendapatan['total_pendapatan'];
}


// --- AKHIR DATA SPESIFIK UNTUK LAPORAN KINERJA KURIR ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kinerja Kurir</title>
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
            margin-left: 0; /* Remove default margin for responsiveness */
        }
        .toggle-btn {
            background: none;
            border: none;
            color: var(--cream); /* Warna tombol toggle */
            margin-left: 20px;
            font-size: 20px;
        }
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
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        #deliveryChart, #revenueChart { /* Tambahkan ID untuk grafik pendapatan */
            width: 100% !important;
            max-width: 600px !important; /* Sesuaikan jika perlu */
            height: 300px !important;
        }
        .card-title {
            color: var(--mocha);
        }

        /* Table styles within card */
        .card .table {
            background-color: var(--light-beige); /* Background tabel */
        }
        .card .table thead th {
            background-color: var(--light-mocha); /* Header tabel */
            color: var(--text-light);
            border-bottom: 1px solid var(--mocha);
        }
        .card .table tbody tr {
            background-color: var(--light-beige);
        }
        .card .table tbody tr:nth-of-type(even) {
            background-color: var(--beige); /* Warna selang-seling */
        }
        .card .table tbody tr:hover {
            background-color: var(--cream);
        }
        .card .table td, .card .table th {
            border-top: 1px solid var(--beige); /* Garis border tabel */
            padding: 12px;
        }
        .card .table tfoot th {
            background-color: var(--mocha);
            color: var(--text-light);
            border-top: 2px solid var(--light-mocha);
        }

        /* Form elements */
        .form-control, .form-control-file, .btn {
            border-radius: 5px;
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
        .btn-secondary {
            background-color: var(--beige);
            border-color: var(--beige);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
        }
        .modal-content {
            background-color: var(--light-beige);
            color: var(--text-dark);
        }
        .modal-header {
            background-color: var(--mocha);
            color: var(--text-light);
            border-bottom: 1px solid var(--light-mocha);
        }
        .modal-footer {
            border-top: 1px solid var(--beige);
        }
        .modal-title {
            color: var(--text-light);
        }
        .breadcrumb {
            background-color: var(--light-beige);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--beige);
        }
        .breadcrumb-item a {
            color: var(--mocha);
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: var(--text-dark);
        }
        footer {
            background-color: var(--mocha) !important;
            color: var(--cream) !important;
        }
        footer a {
            color: var(--light-beige) !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
            .content.ml-collapsed {
                margin-left: 0;
            }
            .toggle-btn {
                margin-left: 10px;
            }
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
            <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto Profil" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Profile">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                <a class="btn btn-link text-danger p-0 d-block" href="../../logout.php">Logout</a>
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
                            <img src="../../img/kurir/<?= htmlspecialchars($user['foto']) ?>" width="80" class="mb-2 rounded"><br>
                        <?php else: ?>
                            <img src="../../img/kurir/default.png" width="80" class="mb-2 rounded"><br>
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
                <a class="nav-link" href="../dashboard_kurir.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../pesanan/pesanan_kurir.php">
                    <i class="fas fa-box-open"></i>
                    <span class="ml-2">Tugas Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../riwayat/riwayat_pengiriman.php">
                    <i class="fas fa-history"></i>
                    <span class="ml-2">Riwayat Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../invoice/invoice_kurir.php"> <i class="fas fa-file-invoice"></i> <span class="ml-2">Invoice</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="laporan_kinerja.php">
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
        <a href="../../logout.php" class="text-danger mt-3 d-block pl-3"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard_kurir.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Laporan Kinerja</li>
            </ol>
        </nav>
        <h1 class="mb-4">Laporan Kinerja Kurir</h1>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line mr-2"></i> Grafik Jumlah Pengiriman Selesai per Bulan</h5>
                        <div style="height: 300px;"><canvas id="deliveryChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i> Grafik Pendapatan per Bulan</h5>
                        <div style="height: 300px;"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        ---

        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-table mr-2"></i> Ringkasan Kinerja Tahunan</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Tahun</th>
                                        <th>Total Pengiriman Selesai</th>
                                        <th>Total Pendapatan (Rp)</th>
                                        <th>Rata-rata Pengiriman per Bulan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Query untuk ringkasan kinerja tahunan
                                    $query_kinerja_tahunan = "SELECT
                                                                YEAR(tanggal_pesanan) AS tahun,
                                                                COUNT(*) AS total_pengiriman,
                                                                SUM(ongkos_kirim) AS total_pendapatan
                                                            FROM pesanan
                                                            WHERE kurir_id = ? AND status_pesanan = 'selesai'
                                                            GROUP BY YEAR(tanggal_pesanan)
                                                            ORDER BY tahun DESC";
                                    $stmt_kinerja_tahunan = mysqli_prepare($conn, $query_kinerja_tahunan);
                                    mysqli_stmt_bind_param($stmt_kinerja_tahunan, 'i', $user_id);
                                    mysqli_stmt_execute($stmt_kinerja_tahunan);
                                    $result_kinerja_tahunan = mysqli_stmt_get_result($stmt_kinerja_tahunan);

                                    if (mysqli_num_rows($result_kinerja_tahunan) > 0) {
                                        while ($row_tahunan = mysqli_fetch_assoc($result_kinerja_tahunan)) {
                                            $total_pengiriman = $row_tahunan['total_pengiriman'];
                                            $total_pendapatan = $row_tahunan['total_pendapatan'];

                                            // Hitung rata-rata pengiriman per bulan untuk tahun tersebut
                                            // Asumsi: hitung rata-rata berdasarkan jumlah bulan yang ada data pengiriman di tahun tersebut
                                            $query_distinct_months = "SELECT COUNT(DISTINCT MONTH(tanggal_pesanan)) AS num_months
                                                                        FROM pesanan
                                                                        WHERE kurir_id = ? AND status_pesanan = 'selesai' AND YEAR(tanggal_pesanan) = ?";
                                            $stmt_distinct_months = mysqli_prepare($conn, $query_distinct_months);
                                            mysqli_stmt_bind_param($stmt_distinct_months, 'ii', $user_id, $row_tahunan['tahun']);
                                            mysqli_stmt_execute($stmt_distinct_months);
                                            $result_distinct_months = mysqli_stmt_get_result($stmt_distinct_months);
                                            $num_months = mysqli_fetch_assoc($result_distinct_months)['num_months'];
                                            $rata_rata_pengiriman = ($num_months > 0) ? round($total_pengiriman / $num_months, 2) : 0;

                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row_tahunan['tahun']) . "</td>";
                                            echo "<td>" . htmlspecialchars($total_pengiriman) . "</td>";
                                            echo "<td>Rp. " . number_format($total_pendapatan, 0, ',', '.') . "</td>";
                                            echo "<td>" . htmlspecialchars($rata_rata_pengiriman) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center'>Tidak ada data kinerja tahunan.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-list-alt mr-2"></i> Detail Pengiriman Selesai</h5>
                        <form method="GET" action="">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="filter_bulan">Bulan:</label>
                                    <select name="filter_bulan" id="filter_bulan" class="form-control">
                                        <option value="">Semua Bulan</option>
                                        <?php
                                        $bulan_nama = [
                                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                        ];
                                        foreach ($bulan_nama as $num => $name) {
                                            $selected = (isset($_GET['filter_bulan']) && $_GET['filter_bulan'] == $num) ? 'selected' : '';
                                            echo "<option value='{$num}' {$selected}>{$name}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filter_tahun">Tahun:</label>
                                    <select name="filter_tahun" id="filter_tahun" class="form-control">
                                        <option value="">Semua Tahun</option>
                                        <?php
                                        // Ambil tahun unik dari pesanan yang sudah selesai
                                        $query_years = "SELECT DISTINCT YEAR(tanggal_pesanan) AS year FROM pesanan WHERE kurir_id = ? AND status_pesanan = 'selesai' ORDER BY year DESC";
                                        $stmt_years = mysqli_prepare($conn, $query_years);
                                        mysqli_stmt_bind_param($stmt_years, 'i', $user_id);
                                        mysqli_stmt_execute($stmt_years);
                                        $result_years = mysqli_stmt_get_result($stmt_years);
                                        while($row_year = mysqli_fetch_assoc($result_years)) {
                                            $selected = (isset($_GET['filter_tahun']) && $_GET['filter_tahun'] == $row_year['year']) ? 'selected' : '';
                                            echo "<option value='{$row_year['year']}' {$selected}>{$row_year['year']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>ID Pesanan</th>
                                        <th>Tanggal Pesanan</th>
                                        <th>Status</th>
                                        <th>Alamat Pengiriman</th>
                                        <th>Ongkos Kirim</th>
                                        <th>Nama Pelanggan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // --- LOGIKA FILTER UNTUK DETAIL PENGIRIMAN SELESAI ---
                                    $types_detail = 'i'; // Default untuk kurir_id
                                    $values_to_bind_detail = [$user_id];
                                    $where_clauses_detail = ["p.kurir_id = ? AND p.status_pesanan = 'selesai'"];

                                    if (!empty($_GET['filter_bulan'])) {
                                        $where_clauses_detail[] = "MONTH(p.tanggal_pesanan) = ?";
                                        $types_detail .= 'i';
                                        $values_to_bind_detail[] = (int)$_GET['filter_bulan'];
                                    }
                                    if (!empty($_GET['filter_tahun'])) {
                                        $where_clauses_detail[] = "YEAR(p.tanggal_pesanan) = ?";
                                        $types_detail .= 'i';
                                        $values_to_bind_detail[] = (int)$_GET['filter_tahun'];
                                    }

                                    $query_detail_pengiriman_filtered = "SELECT
                                                                            p.id AS pesanan_id,
                                                                            p.tanggal_pesanan,
                                                                            p.status_pesanan,
                                                                            p.alamat_pengiriman,
                                                                            p.ongkos_kirim,
                                                                            pl.nama AS nama_pelanggan
                                                                        FROM pesanan p
                                                                        JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
                                                                        WHERE " . implode(' AND ', $where_clauses_detail) . "
                                                                        ORDER BY p.tanggal_pesanan DESC";

                                    $stmt_detail_pengiriman = mysqli_prepare($conn, $query_detail_pengiriman_filtered);

                                    if (!$stmt_detail_pengiriman) {
                                        die("Error preparing detail statement: " . mysqli_error($conn));
                                    }

                                    // --- BAGIAN INI ADALAH PERBAIKAN UTAMA UNTUK BARIS 720 ---
                                    // Siapkan array argumen untuk mysqli_stmt_bind_param.
                                    // Elemen pertama adalah objek statement, elemen kedua adalah string tipe data.
                                    $bind_params_array_detail = [$stmt_detail_pengiriman, $types_detail];

                                    // Loop melalui nilai-nilai yang akan diikat dan buat referensi untuk setiap nilai.
                                    // Ini adalah langkah krusial untuk mengatasi peringatan "expected to be a reference".
                                    for ($i = 0; $i < count($values_to_bind_detail); $i++) {
                                        $bind_params_array_detail[] = &$values_to_bind_detail[$i]; // '&' membuat referensi!
                                    }

                                    // Panggil mysqli_stmt_bind_param menggunakan call_user_func_array
                                    call_user_func_array('mysqli_stmt_bind_param', $bind_params_array_detail);
                                    // --- AKHIR PERBAIKAN ---

                                    mysqli_stmt_execute($stmt_detail_pengiriman);
                                    $result_detail_pengiriman = mysqli_stmt_get_result($stmt_detail_pengiriman);

                                    if (mysqli_num_rows($result_detail_pengiriman) > 0) {
                                        while ($row_detail = mysqli_fetch_assoc($result_detail_pengiriman)) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row_detail['pesanan_id']) . "</td>";
                                            echo "<td>" . htmlspecialchars(date('d F Y', strtotime($row_detail['tanggal_pesanan']))) . "</td>";
                                            echo "<td>" . htmlspecialchars(ucfirst($row_detail['status_pesanan'])) . "</td>";
                                            echo "<td>" . htmlspecialchars($row_detail['alamat_pengiriman']) . "</td>";
                                            echo "<td>Rp. " . number_format($row_detail['ongkos_kirim'], 0, ',', '.') . "</td>";
                                            echo "<td>" . htmlspecialchars($row_detail['nama_pelanggan']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center'>Tidak ada data pengiriman selesai untuk filter ini.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.querySelector('.content').classList.toggle('ml-collapsed');
    }

    // Data untuk grafik pengiriman
    const bulanGrafik = <?= json_encode($bulan_grafik); ?>;
    const totalPengirimanGrafik = <?= json_encode($total_pengiriman_grafik); ?>;

    const ctxDelivery = document.getElementById('deliveryChart').getContext('2d');
    const deliveryChart = new Chart(ctxDelivery, {
        type: 'bar',
        data: {
            labels: bulanGrafik,
            datasets: [{
                label: 'Jumlah Pengiriman Selesai',
                data: totalPengirimanGrafik,
                backgroundColor: ' #6F4E37',
                borderColor: ' #6F4E37',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Pengiriman'
                    },
                    ticks: {
                        stepSize: 1 // Agar sumbu y menampilkan angka bulat untuk jumlah pengiriman
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Bulan - Tahun'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            // Split 'MM-YYYY' and format to 'Nama Bulan YYYY'
                            const label = context[0].label;
                            const parts = label.split('-');
                            const monthNum = parseInt(parts[0]);
                            const year = parts[1];
                            const monthNames = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                            return monthNames[monthNum] + ' ' + year;
                        }
                    }
                }
            }
        }
    });

    // Data untuk grafik pendapatan
    const pendapatanGrafik = <?= json_encode($pendapatan_grafik); ?>;

    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctxRevenue, {
        type: 'line', // Bisa juga 'bar' jika diinginkan
        data: {
            labels: bulanGrafik, // Menggunakan label bulan yang sama
            datasets: [{
                label: 'Total Pendapatan (Rp)',
                data: pendapatanGrafik,
                backgroundColor: 'rgba(255, 159, 64, 0.6)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1,
                fill: false // Untuk grafik garis agar tidak mengisi area di bawah garis
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Pendapatan (Rp)'
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp. ' + value.toLocaleString('id-ID'); // Format mata uang Rupiah
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Bulan - Tahun'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const label = context[0].label;
                            const parts = label.split('-');
                            const monthNum = parseInt(parts[0]);
                            const year = parts[1];
                            const monthNames = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                            return monthNames[monthNum] + ' ' + year;
                        },
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += 'Rp. ' + context.parsed.y.toLocaleString('id-ID');
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>