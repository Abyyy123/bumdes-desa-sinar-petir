<?php
session_start();
include('../../koneksi/koneksi.php'); // Path ke koneksi.php sudah benar jika riwayat_pengiriman.php ada di folder 'riwayat/'

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php'); // Redirect ke halaman login jika belum login
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID kurir yang login

// Ambil data profil kurir dari tabel 'kurir'
$query_kurir = "SELECT * FROM kurir WHERE id = ?";
$stmt_kurir = mysqli_prepare($conn, $query_kurir);
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

// Fungsi format_rupiah (jika Anda memiliki ini di file lain, Anda bisa menghapusnya dari sini)
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        $hasil_rupiah = "Rp. " . number_format($angka,0,',','.');
        return $hasil_rupiah;
    }
}

// --- LOGIKA UPDATE PROFIL KURIR (Disalin dari dashboard_kurir.php, sesuaikan path) ---
// Pastikan semua path dalam logika ini sudah disesuaikan dengan lokasi file riwayat_pengiriman.php
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
        $upload_dir = '../../img/kurir/'; // PERHATIKAN PATH RELATIF INI: Dari riwayat/ ke img/kurir/
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
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='riwayat_pengiriman.php';</script>"; // Redirect ke halaman ini
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- LOGIKA DAFTAR RIWAYAT PENGIRIMAN ---
// Default pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Ambil total data untuk pagination
$sql_count = "SELECT COUNT(*) AS total FROM pesanan WHERE kurir_id = ? AND status_pesanan = 'selesai'";
$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, 'i', $user_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_rows = mysqli_fetch_assoc($result_count)['total'];
$total_pages = ceil($total_rows / $limit);

// Ambil data riwayat pengiriman
$query_riwayat_pengiriman = "SELECT
                                p.id AS pesanan_id,
                                p.tanggal_pesanan,
                                p.status_pesanan,
                                p.alamat_pengiriman,
                                p.ongkos_kirim,
                                pl.nama AS nama_pelanggan
                            FROM pesanan p
                            JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
                            WHERE p.kurir_id = ? AND p.status_pesanan = 'selesai'
                            ORDER BY p.tanggal_pesanan DESC
                            LIMIT ? OFFSET ?";
$stmt_riwayat_pengiriman = mysqli_prepare($conn, $query_riwayat_pengiriman);
mysqli_stmt_bind_param($stmt_riwayat_pengiriman, 'iii', $user_id, $limit, $offset);
mysqli_stmt_execute($stmt_riwayat_pengiriman);
$result_riwayat_pengiriman = mysqli_stmt_get_result($stmt_riwayat_pengiriman);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pengiriman - Kurir</title>
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

        /* Table styles */
        .table {
            background-color: var(--cream); /* Background tabel */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .table thead th {
            background-color: var(--mocha); /* Header tabel */
            color: var(--text-light);
            border-bottom: none;
        }
        .table tbody tr {
            background-color: var(--light-beige);
        }
        .table tbody tr:nth-of-type(even) {
            background-color: var(--beige); /* Warna selang-seling */
        }
        .table tbody tr:hover {
            background-color: var(--cream);
        }
        .table td, .table th {
            border-top: 1px solid var(--beige); /* Garis border tabel */
            padding: 12px;
        }
        .table .btn-outline-info {
            color: var(--mocha);
            border-color: var(--mocha);
        }
        .table .btn-outline-info:hover {
            background-color: var(--mocha);
            color: var(--text-light);
        }

        /* Style for status badge */
        .badge.bg-success {
            background-color: #28a745 !important; /* Hijau untuk 'Selesai' */
            color: white;
        }

        /* Pagination */
        .pagination .page-link {
            color: var(--mocha);
            background-color: var(--light-beige);
            border: 1px solid var(--beige);
        }
        .pagination .page-link:hover {
            background-color: var(--beige);
            color: var(--text-dark);
        }
        .pagination .page-item.active .page-link {
            background-color: var(--mocha);
            border-color: var(--mocha);
            color: var(--text-light);
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: var(--cream);
            border-color: var(--beige);
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
                <a class="nav-link" href="../pesanan/pesanan_kurir.php">
                    <i class="fas fa-box-open"></i>
                    <span class="ml-2">Tugas Pengiriman</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="riwayat_pengiriman.php">
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
        <h2 class="mb-4">Riwayat Pengiriman Saya</h2>

        <div class="card shadow">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-list-alt mr-2"></i> Daftar Pengiriman Selesai</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Tanggal Selesai</th>
                                <th>Pelanggan</th>
                                <th>Alamat Pengiriman</th>
                                <th>Ongkos Kirim</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_riwayat_pengiriman) > 0) : ?>
                                <?php while ($pesanan = mysqli_fetch_assoc($result_riwayat_pengiriman)) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pesanan['pesanan_id']) ?></td>
                                        <td><?= htmlspecialchars(date('d F Y', strtotime($pesanan['tanggal_pesanan']))) ?></td>
                                        <td><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></td>
                                        <td><?= htmlspecialchars(substr($pesanan['alamat_pengiriman'], 0, 50)) ?>...</td>
                                        <td><?= format_rupiah($pesanan['ongkos_kirim']) ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars(str_replace('_', ' ', strtoupper($pesanan['status_pesanan']))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../pesanan/detail_pesanan_kurir.php?id=<?= $pesanan['pesanan_id'] ?>" class="btn btn-sm btn-outline-info">Detail</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada riwayat pengiriman yang ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation example" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1) : ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages) : ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
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
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }
</script>
</body>
</html>