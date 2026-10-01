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

// --- LOGIKA UPDATE PROFIL KURIR (SAMA SEPERTI SEBELUMNYA) ---
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
        $upload_dir = '../../img/kurir/';
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
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='pesanan_kurir.php';</script>"; // Redirect ke halaman ini
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- LOGIKA PAGINASI ---
$limit_per_page = 5; // Batasan jumlah pesanan per halaman
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit_per_page;

// Query untuk menghitung total pesanan yang relevan
$count_query = "SELECT COUNT(p.id) AS total_pesanan
                FROM pesanan p
                WHERE p.kurir_id = ? AND p.status_pesanan IN ('diproses', 'dikirim')";
$stmt_count = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($stmt_count, 'i', $user_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_pesanan_row = mysqli_fetch_assoc($result_count);
$total_pesanan = $total_pesanan_row['total_pesanan'];
mysqli_stmt_close($stmt_count);

$total_pages = ceil($total_pesanan / $limit_per_page);

// --- LOGIKA UTAMA UNTUK HALAMAN PESANAN KURIR (DENGAN PAGINASI) ---
$query_tugas_pengiriman = "SELECT
                                p.id AS pesanan_id,
                                p.tanggal_pesanan,
                                p.status_pesanan,
                                p.metode_pengiriman,
                                p.alamat_pengiriman,
                                p.ongkos_kirim,
                                p.total_harga,
                                p.catatan_pelanggan,
                                p.nomor_resi,
                                pl.nama AS nama_pelanggan,
                                pl.nomor_telepon AS telepon_pelanggan
                            FROM pesanan p
                            JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
                            WHERE p.kurir_id = ? AND p.status_pesanan IN ('diproses', 'dikirim')
                            ORDER BY p.tanggal_pesanan ASC
                            LIMIT ? OFFSET ?";

$stmt_tugas_pengiriman = mysqli_prepare($conn, $query_tugas_pengiriman);
if (!$stmt_tugas_pengiriman) {
    die("Error preparing tugas pengiriman statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_tugas_pengiriman, 'iii', $user_id, $limit_per_page, $offset);
mysqli_stmt_execute($stmt_tugas_pengiriman);
$result_tugas_pengiriman = mysqli_stmt_get_result($stmt_tugas_pengiriman);

// Inisialisasi array untuk menyimpan pesanan dengan detail produk
$pesanan_kurir_dengan_detail = [];
while ($pesanan = mysqli_fetch_assoc($result_tugas_pengiriman)) {
    $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']] = $pesanan;
    $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']]['produk'] = [];

    // Ambil detail produk untuk setiap pesanan
    $query_detail_produk = "SELECT
                                dp.quantity,
                                dp.harga_satuan,
                                dp.nama_produk_saat_beli,
                                dp.variasi_detail_saat_beli,
                                pr.gambar AS gambar_produk
                            FROM detail_pesanan dp
                            JOIN produk pr ON dp.produk_id = pr.id
                            WHERE dp.pesanan_id = ?";
    $stmt_detail_produk = mysqli_prepare($conn, $query_detail_produk);
    if (!$stmt_detail_produk) {
        die("Error preparing detail produk statement: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt_detail_produk, 'i', $pesanan['pesanan_id']);
    mysqli_stmt_execute($stmt_detail_produk);
    $result_detail_produk = mysqli_stmt_get_result($stmt_detail_produk);

    while ($detail_produk = mysqli_fetch_assoc($result_detail_produk)) {
        $pesanan_kurir_dengan_detail[$pesanan['pesanan_id']]['produk'][] = $detail_produk;
    }
    mysqli_stmt_close($stmt_detail_produk);
}
mysqli_stmt_close($stmt_tugas_pengiriman);

// --- AKHIR LOGIKA UTAMA UNTUK HALAMAN PESANAN KURIR ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tugas Pengiriman | BUMDes Sinar Petir</title>
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

        /* Styles for order list table */
        .order-list-table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .order-detail-card {
            margin-bottom: 20px;
            border-radius: 10px;
            background-color: var(--light-beige); /* Kartu detail pesanan */
            color: var(--text-dark);
        }
        .order-detail-card .card-header {
            background-color: var(--mocha); /* Header kartu detail pesanan */
            color: var(--text-light);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-detail-card .card-body {
            padding: 20px;
        }
        .order-item-list {
            list-style: none;
            padding: 0;
        }
        .order-item-list li {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px dashed var(--beige); /* Garis pemisah item */
        }
        .order-item-list li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .order-item-list img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 5px;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-details strong {
            display: block;
            font-size: 1.1em;
            margin-bottom: 3px;
        }
        .order-item-details small {
            color: var(--text-dark);
        }

        /* Badge Status */
        .badge.bg-warning {
            background-color: var(--beige) !important;
            color: var(--text-dark) !important;
        }
        .badge.bg-info {
            background-color: var(--light-mocha) !important;
            color: var(--text-light) !important;
        }
        .badge.bg-secondary {
            background-color: #888888 !important; /* Default fallback */
            color: var(--text-light) !important;
        }

        /* Buttons */
        .btn-info {
            background-color: var(--light-mocha) !important;
            border-color: var(--light-mocha) !important;
            color: var(--text-light) !important;
        }
        .btn-info:hover {
            background-color: var(--mocha) !important;
            border-color: var(--mocha) !important;
        }
        .btn-success {
            background-color: var(--beige) !important;
            border-color: var(--beige) !important;
            color: var(--text-dark) !important;
        }
        .btn-success:hover {
            background-color: var(--light-beige) !important;
            border-color: var(--light-beige) !important;
        }
        .btn-warning {
            background-color: var(--light-beige) !important;
            border-color: var(--light-beige) !important;
            color: var(--text-dark) !important;
        }
        .btn-warning:hover {
            background-color: var(--beige) !important;
            border-color: var(--beige) !important;
        }
        .btn-primary { /* Digunakan untuk modal save */
            background-color: var(--mocha);
            border-color: var(--mocha);
            color: var(--text-light);
        }
        .btn-primary:hover {
            background-color: var(--light-mocha);
            border-color: var(--light-mocha);
        }
        .btn-secondary { /* Digunakan untuk modal cancel */
            background-color: var(--beige);
            border-color: var(--beige);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
        }

        /* Pagination */
        .page-link {
            color: var(--mocha);
        }
        .page-item.active .page-link {
            background-color: var(--mocha);
            border-color: var(--mocha);
            color: var(--text-light);
        }
        .page-item.disabled .page-link {
            color: #6c757d;
        }

        /* Footer */
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
                        <?php if ($user['foto'] && $user['foto'] != 'default.png') : ?>
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
                <a class="nav-link active" href="pesanan_kurir.php">
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
                <a class="nav-link" href="../laporan/laporan_kinerja.php">
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
        <h2>Tugas Pengiriman Anda</h2>
        <p>Berikut adalah daftar pesanan yang sedang dalam proses pengiriman atau siap untuk dikirim oleh Anda.</p>

        <?php if (!empty($pesanan_kurir_dengan_detail)) : ?>
            <?php foreach ($pesanan_kurir_dengan_detail as $pesanan_id => $pesanan) : ?>
                <div class="card shadow order-detail-card">
                    <div class="card-header">
                        <div>
                            <strong>Pesanan ID: <?= htmlspecialchars($pesanan['pesanan_id']) ?></strong><br>
                            <small>Tanggal Pesanan: <?= htmlspecialchars(date('d F Y H:i', strtotime($pesanan['tanggal_pesanan']))) ?></small>
                        </div>
                        <div>
                            <span class="badge
                                <?php
                                    if($pesanan['status_pesanan'] == 'diproses') echo 'bg-warning text-dark';
                                    else if($pesanan['status_pesanan'] == 'dikirim') echo 'bg-info text-white';
                                    else echo 'bg-secondary text-white';
                                ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['status_pesanan']))) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Informasi Pelanggan:</h6>
                                <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($pesanan['nama_pelanggan']) ?></p>
                                <p class="mb-1"><strong>Telepon:</strong> <?= htmlspecialchars($pesanan['telepon_pelanggan']) ?></p>
                                <p class="mb-1"><strong>Alamat Pengiriman:</strong> <?= htmlspecialchars($pesanan['alamat_pengiriman']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Detail Pesanan:</h6>
                                <p class="mb-1"><strong>Nomor Resi:</strong> <?= htmlspecialchars($pesanan['nomor_resi'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Metode Pengiriman:</strong> <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['metode_pengiriman']))) ?></p>
                                <p class="mb-1"><strong>Ongkos Kirim:</strong> Rp. <?= number_format($pesanan['ongkos_kirim'], 0, ',', '.') ?></p>
                                <p class="mb-1"><strong>Total Harga:</strong> Rp. <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></p>
                                <?php if (!empty($pesanan['catatan_pelanggan'])) : ?>
                                    <p class="mb-1"><strong>Catatan Pelanggan:</strong> <?= htmlspecialchars($pesanan['catatan_pelanggan']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                        <h6>Produk dalam Pesanan:</h6>
                        <ul class="order-item-list">
                            <?php foreach ($pesanan['produk'] as $produk_item) : ?>
                                <li>
                                    <img src="../../img/barang/<?= htmlspecialchars($produk_item['gambar_produk'] ?: 'default_product.png') ?>" alt="Gambar Produk">
                                    <div class="order-item-details">
                                        <strong><?= htmlspecialchars($produk_item['nama_produk_saat_beli']) ?></strong>
                                        <small>Jumlah: <?= htmlspecialchars($produk_item['quantity']) ?></small><br>
                                        <small>Harga Satuan: Rp. <?= number_format($produk_item['harga_satuan'], 0, ',', '.') ?></small>
                                        <?php if (!empty($produk_item['variasi_detail_saat_beli'])) : ?>
                                            <small>Variasi: <?= htmlspecialchars($produk_item['variasi_detail_saat_beli']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="text-right mt-3">
                            <?php if ($pesanan['status_pesanan'] == 'diproses') : ?>
                                <a href="mulai_pengiriman_kurir.php?action=start_delivery&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-info me-2">Mulai Pengiriman</a>
                            <?php elseif ($pesanan['status_pesanan'] == 'dikirim') : ?>
                                <a href="mulai_pengiriman_kurir.php?action=finish_delivery&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-success me-2">Selesaikan Pengiriman</a>
                                <a href="mulai_pengiriman_kurir.php?action=report_issue&id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-warning">Laporkan Masalah</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <nav aria-label="Page navigation example" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $current_page - 1 ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <li class="page-item <?= ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $current_page + 1 ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php else : ?>
            <div class="alert alert-info text-center" role="alert">
                Tidak ada tugas pengiriman aktif saat ini.
            </div>
        <?php endif; ?>

    </div>
</div>

<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }

    $(document).ready(function() {
        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });
    });
</script>
</body>
</html>