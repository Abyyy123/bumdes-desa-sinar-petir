<?php
session_start();
include('../../koneksi/koneksi.php'); // Pastikan path ini benar (naik dua tingkat)

// --- Cek Login dan Peran Kurir ---
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../login.php'); // Redirect ke halaman login jika belum login
    exit;
}

$user_id = $_SESSION['pengguna_id']; // ID kurir yang login

// Ambil data profil kurir
$query_kurir = "SELECT * FROM kurir WHERE id = ?";
$stmt_kurir = mysqli_prepare($conn, $query_kurir);
if (!$stmt_kurir) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_kurir, 'i', $user_id);
mysqli_stmt_execute($stmt_kurir);
$result_kurir = mysqli_stmt_get_result($stmt_kurir);
$user = mysqli_fetch_assoc($result_kurir);

// Jika kurir tidak ditemukan atau statusnya tidak 'aktif', redirect
if (!$user || $user['status'] !== 'aktif') {
    session_destroy();
    header('Location: ../../login.php?error=invalid_kurir_status');
    exit;
}

// --- LOGIKA UNTUK MENAMPILKAN DETAIL INVOICE ---
$pesanan_id = $_GET['id'] ?? null;

if (!$pesanan_id) {
    echo "<script>alert('ID Invoice tidak ditemukan!'); window.location.href='invoice_kurir.php';</script>";
    exit;
}

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

// Ambil detail pesanan yang berfungsi sebagai invoice
// Pastikan hanya kurir yang bersangkutan yang bisa melihat invoice ini
$query_detail_invoice = "SELECT
                            p.id AS pesanan_id,
                            p.tanggal_pesanan,
                            p.status_pesanan,
                            p.ongkos_kirim,
                            p.alamat_pengiriman,
                            pl.nama AS nama_pelanggan,
                            pl.email AS email_pelanggan,
                            pl.nomor_telepon AS telepon_pelanggan,
                            p.total_harga,     -- Ambil dari tabel pembayaran
                            pm.status_pembayaran,     -- Ambil dari tabel pembayaran
                            pm.tanggal_pembayaran,    -- Asumsi ada tanggal pembayaran
                            (SELECT GROUP_CONCAT(CONCAT(nama, ' (', quantity, 'x @Rp.', FORMAT(harga_satuan, 0)) SEPARATOR '; ')
                             FROM detail_pesanan dp WHERE dp.pesanan_id = p.id) AS detail_produk
                        FROM pesanan p
                        JOIN pelanggan pl ON p.pelanggan_id = pl.pengguna_id
                        LEFT JOIN pembayaran pm ON p.id = pm.pesanan_id -- JOIN ke tabel pembayaran
                        WHERE p.id = ? AND p.kurir_id = ? AND p.status_pesanan = 'selesai'"; // Hanya pesanan 'selesai'
$stmt_detail_invoice = mysqli_prepare($conn, $query_detail_invoice);
mysqli_stmt_bind_param($stmt_detail_invoice, 'ii', $pesanan_id, $user_id);
mysqli_stmt_execute($stmt_detail_invoice);
$result_detail_invoice = mysqli_stmt_get_result($stmt_detail_invoice);
$invoice = mysqli_fetch_assoc($result_detail_invoice);

if (!$invoice) {
    echo "<script>alert('Invoice tidak ditemukan atau Anda tidak memiliki akses.'); window.location.href='invoice_kurir.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Invoice #<?= htmlspecialchars($invoice['pesanan_id']) ?></title>
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
            background-color: var(--cream);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark);
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
            background-color: var(--mocha);
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
            background-color: var(--cream);
        }
        .toggle-btn {
            background: none;
            border: none;
            color: var(--cream);
            margin-left: 20px;
            font-size: 20px;
        }
        .content.ml-collapsed {
             margin-left: 80px;
        }

        .navbar {
            background-color: var(--mocha) !important;
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
            background: var(--light-beige);
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
            color: var(--mocha);
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil,
        .card.shadow {
            background: var(--light-beige);
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
            <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" alt="Foto Profil" class="rounded-circle mr-2" width="40" height="40">
            <span class="d-none d-md-inline text-white">Profil</span>
        </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../img/kurir/<?= htmlspecialchars($user['foto'] ?: 'default.png') ?>" width="80" class="mb-2 rounded" alt="Profile">
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
                            <img src="../../img/foto/default.png" width="80" class="mb-2 rounded"><br>
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
                <a class="nav-link active" href="invoice_kurir.php">
                    <i class="fas fa-file-invoice"></i>
                    <span class="ml-2">Invoice</span>
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
        <h1>Detail Invoice #<?= htmlspecialchars($invoice['pesanan_id']) ?></h1>
        <p>Informasi lengkap mengenai invoice pesanan ini.</p>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Umum Invoice</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID Invoice/Pesanan:</strong> <?= htmlspecialchars($invoice['pesanan_id']) ?></p>
                        <p><strong>Tanggal Pesanan:</strong> <?= date('d M Y H:i', strtotime($invoice['tanggal_pesanan'])) ?></p>
                        <p><strong>Ongkos Kirim:</strong> Rp. <?= number_format($invoice['ongkos_kirim'], 0, ',', '.') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status Pesanan:</strong> <span class="badge
                            <?php
                                 if($invoice['status_pesanan'] == 'selesai') echo 'bg-success text-white';
                                 else echo 'bg-secondary text-white';
                            ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', strtoupper($invoice['status_pesanan']))) ?>
                        </span></p>
                        <p><strong>Tanggal Pembayaran:</strong> <?= htmlspecialchars($invoice['tanggal_pembayaran'] ? date('d M Y H:i', strtotime($invoice['tanggal_pembayaran'])) : 'Belum Dibayar') ?></p>
                        <p><strong>Status Pembayaran:</strong> <span class="badge
                            <?php
                                 if($invoice['status_pembayaran'] == 'lunas') echo 'bg-success text-white';
                                 else if($invoice['status_pembayaran'] == 'belum_lunas') echo 'bg-danger text-white';
                                 else echo 'bg-secondary text-white';
                            ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', strtoupper($invoice['status_pembayaran'] ?? 'N/A'))) ?>
                        </span></p>
                        <p><strong>Total Pembayaran:</strong> Rp. <?= number_format($invoice['total_harga'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Detail Pelanggan</h5>
            </div>
            <div class="card-body">
                <p><strong>Nama Pelanggan:</strong> <?= htmlspecialchars($invoice['nama_pelanggan']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($invoice['email_pelanggan']) ?></p>
                <p><strong>Telepon:</strong> <?= htmlspecialchars($invoice['telepon_pelanggan']) ?></p>
                <p><strong>Alamat Pengiriman:</strong> <?= nl2br(htmlspecialchars($invoice['alamat_pengiriman'])) ?></p>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Detail Produk/Layanan</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($invoice['detail_produk'])) : ?>
                    <ul class="list-group list-group-flush">
                        <?php
                        $products = explode('; ', $invoice['detail_produk']);
                        foreach ($products as $product) {
                            echo '<li class="list-group-item">' . htmlspecialchars($product) . ')</li>';
                        }
                        ?>
                    </ul>
                <?php else : ?>
                    <p>Tidak ada detail produk untuk pesanan ini.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="invoice_kurir.php" class="btn btn-primary me-2">Kembali ke Daftar Invoice</a>
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
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }
</script>
</body>
</html>