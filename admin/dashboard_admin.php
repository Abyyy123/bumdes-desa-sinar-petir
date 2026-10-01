<?php
session_start();
include('../koneksi/koneksi.php');

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

// Proses update profil
if (isset($_POST['simpan'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'] != '' ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user['password'];
    $foto_lama = $user['foto'];
    $foto_baru = $foto_lama; // Inisialisasi dengan foto lama

    if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../img/foto/';
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Hapus foto lama jika ada dan berbeda dengan yang baru
            if ($foto_lama && file_exists($upload_dir . $foto_lama) && $foto_lama != $foto_name) {
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
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "<script>alert('Terjadi error saat mengunggah foto: " . $_FILES['foto']['error'] . "');</script>";
    }

    $update = "UPDATE pengguna SET nama=?, username=?, email=?, password=?, foto=? WHERE id=?";
    $stmt_update = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt_update, 'sssssi', $nama, $username, $email, $password, $foto_baru, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        echo "<script>alert('Profil berhasil diperbarui'); window.location.href='dashboard_admin.php';</script>";
    } else {
        echo "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// Ambil total pendapatan
$sql_pendapatan = "SELECT SUM(jumlah) AS total FROM transaksi_keuangan WHERE jenis_transaksi = 'pemasukan'";
$result_pendapatan = mysqli_query($conn, $sql_pendapatan);
$row_pendapatan = mysqli_fetch_assoc($result_pendapatan);
$total_pendapatan = $row_pendapatan['total'] ?? 0;

// Ambil total pengeluaran
$sql_pengeluaran = "SELECT SUM(jumlah) AS total FROM transaksi_keuangan WHERE jenis_transaksi = 'pengeluaran'";
$result_pengeluaran = mysqli_query($conn, $sql_pengeluaran);
$row_pengeluaran = mysqli_fetch_assoc($result_pengeluaran);
$total_pengeluaran = $row_pengeluaran['total'] ?? 0;

// Hitung keuntungan bersih
$keuntungan_bersih = $total_pendapatan - $total_pengeluaran;

// Query untuk mendapatkan data penjualan per bulan
$query_penjualan_bulanan = "SELECT
                            MONTH(tanggal_transaksi) AS bulan,
                            SUM(jumlah) AS total_penjualan
                         FROM transaksi_keuangan
                         WHERE jenis_transaksi = 'pemasukan'
                         GROUP BY MONTH(tanggal_transaksi)
                         ORDER BY bulan ASC";

$result_penjualan_bulanan = mysqli_query($conn, $query_penjualan_bulanan);
$bulan = [];
$total_penjualan = [];

while ($row_penjualan = mysqli_fetch_assoc($result_penjualan_bulanan)) {
    $bulan[] = $row_penjualan['bulan'];
    $total_penjualan[] = $row_penjualan['total_penjualan'];
}

// Ringkasan data
$total_bumdes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bumdes"))['total'];
$total_unit_usaha = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM unit_usaha"))['total'];
$total_anggota = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM anggota"))['total'];
$total_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna"))['total'];
$total_pelanggan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pelanggan"))['total'];
$total_penjual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM penjual"))['total'];
$total_kurir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kurir"))['total'];
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$total_pesanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan"))['total'];
$total_pembayaran = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pembayaran"))['total'];
$total_transaksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi_keuangan"))['total'];
$pesanan_baru = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pesanan WHERE status_pesanan = 'menunggu_pembayaran'"));
$pembayaran_menunggu = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pembayaran WHERE status_pembayaran = 'menunggu_konfirmasi'"));
$pengguna_baru = mysqli_query($conn, "SELECT nama FROM pengguna ORDER BY created_at DESC LIMIT 5");
$stok_rendah = mysqli_query($conn, "SELECT nama, stok FROM produk WHERE stok <= 5 ORDER BY stok ASC LIMIT 5");
$grafik_penjualan = mysqli_query($conn, "SELECT MONTH(tanggal_pesanan) AS bulan, COUNT(*) AS total FROM pesanan GROUP BY bulan");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
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
            width: 280px; /* Lebarkan dari 250px menjadi 280px */
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

        /* Specific card colors for dashboard overview */
        .card-dashboard.bg-primary { background-color: #A7D9ED !important; color: #343A40 !important; } /* Kartu (Aksen Utama - Biru Pastel) */
        .card-dashboard.bg-success { background-color: #C8E6C9 !important; color: #343A40 !important; } /* Kartu (Aksen Hijau Pastel) */
        .card-dashboard.bg-info { background-color: #D1C4E9 !important; color: #343A40 !important; } /* Kartu (Aksen Ungu Pastel) */
        .card-dashboard.bg-warning { background-color: #FFD700 !important; color: #343A40 !important;} /* Standard gold for warning, good contrast with dark text */
        .card-dashboard.bg-danger { background-color: #FF6347 !important; color: #F8FBFD !important; } /* Standard tomato for danger, good contrast with light text */
        .card-dashboard.bg-secondary { background-color: #A9A9A9 !important; color: #F8FBFD !important; } /* Dark grey for secondary, good contrast with light text */
        .card-dashboard.bg-dark { background-color: #343A40 !important; color: #F8FBFD !important; } /* Teks: Dark Grayish Black for dark card, light text */


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
        height: 80px;        /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;    /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;      /* jarak luar card */
    }
    #salesChart {
        width: 100% !important;
        max-width: 600px !important;
        height: 300px !important;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background-color: #E0F2F7;">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#" style="color: #343A40;">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Foto" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline" style="color: #343A40;">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../img/foto/<?= $user['foto'] ?: 'default.png' ?>" alt="Profile">
                </div>
                <h5 class="mb-1" style="color: #343A40;"><?= htmlspecialchars($user['nama']); ?></h5>
                <p class="mb-0 small" style="color: #343A40;">Username: <?= htmlspecialchars($user['username']); ?></p>
                <p class="mb-0 small" style="color: #343A40;">Email: <?= htmlspecialchars($user['email']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <div class="text-left">
                    <a class="btn btn-link p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal" style="color: #A7D9ED;">Edit Profil</a>
                    <a class="btn btn-link p-0 d-block" href="../logout.php" style="color: #FF6347;">Logout</a>
                </div>
            </div>
        </li>
    </ul>
</nav>

<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #F8FBFD; color: #343A40;">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel" style="color: #343A40;">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #343A40;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" style="background-color: #FFFFFF; border-color: #A7D9ED; color: #343A40;">
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($user['foto']) : ?>
                            <img src="../img/foto/<?= $user['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file" style="color: #343A40;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn" style="background-color: #A7D9ED; color: #343A40;">Simpan</button>
                    <button type="button" class="btn" data-dismiss="modal" style="background-color: #D1C4E9; color: #343A40;">Batal</button>
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
                        <a class="nav-link submenu" href="pengguna.php?type=user">Data Pengguna</a>
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
            <a class="nav-link collapsed" data-toggle="collapse" href="#manajemenEcommerce" role="button" aria-expanded="false" aria-controls="manajemenEcommerce">
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
        </ul>
        <a href="../logout.php" class="text-danger" style="color: #FF6347 !important;"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a>
    </div>

    <div class="content">
        <h1 style="color: #343A40;">Dashboard Admin BUMDes</h1>
        <h3 style="color: #343A40;">Selamat datang, <?= htmlspecialchars($user['nama']); ?>!</h3>
        <p style="color: #343A40;">Anda login sebagai <strong style="color: #A7D9ED;"><?= $user['role']; ?></strong>.</p>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-building mr-2"></i> Total BUMDes</h5>
                        <p class="card-text"><?= $total_bumdes ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-store mr-2"></i> Total Unit Usaha</h5>
                        <p class="card-text"><?= $total_unit_usaha ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users mr-2"></i> Total Anggota</h5>
                        <p class="card-text"><?= $total_anggota ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-warning text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-cog mr-2"></i> Total Pengguna</h5>
                        <p class="card-text"><?= $total_pengguna ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-danger text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tag mr-2"></i> Total Pelanggan</h5>
                        <p class="card-text"><?= $total_pelanggan ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-secondary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tie mr-2"></i> Total Penjual</h5>
                        <p class="card-text"><?= $total_penjual ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-dark text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shipping-fast mr-2"></i> Total Kurir</h5>
                        <p class="card-text"><?= $total_kurir ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-primary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-box-open mr-2"></i> Total Produk</h5>
                        <p class="card-text"><?= $total_produk ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shopping-cart mr-2"></i> Total Pesanan</h5>
                        <p class="card-text"><?= $total_pesanan ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i> Total Pembayaran</h5>
                        <p class="card-text"><?= $total_pembayaran ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-warning text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line mr-2"></i> Total Transaksi</h5>
                        <p class="card-text"><?= $total_transaksi ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-danger text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i> Pesanan Baru</h5>
                        <p class="card-text"><?= $pesanan_baru ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card card-dashboard bg-secondary text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clock mr-2"></i> Menunggu Pembayaran</h5>
                        <p class="card-text"><?= $pembayaran_menunggu ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow" style="background-color: #F0F4F7; color: #343A40;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: #343A40;">Pendapatan & Pengeluaran</h5>
                        <p class="card-text">Total Pendapatan: <strong style="color: #C8E6C9;">Rp. <?= number_format($total_pendapatan) ?></strong></p>
                        <p class="card-text">Total Pengeluaran: <strong style="color: #FF6347;">Rp. <?= number_format($total_pengeluaran) ?></strong></p>
                        <p class="card-text">Keuntungan Bersih: <strong style="color: #A7D9ED;">Rp. <?= number_format($keuntungan_bersih) ?></strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow" style="background-color: #F0F4F7; color: #343A40;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: #343A40;">Pengguna Baru</h5>
                        <ul class="list-unstyled">
                            <?php while ($row = mysqli_fetch_assoc($pengguna_baru)) : ?>
                                <li style="color: #343A40;"><?= htmlspecialchars($row['nama']) ?></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow" style="background-color: #F0F4F7; color: #343A40;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: #343A40;">Stok Produk Rendah</h5>
                        <ul class="list-unstyled">
                            <?php while ($row_stok = mysqli_fetch_assoc($stok_rendah)) : ?>
                                <li style="color: #343A40;"><?= htmlspecialchars($row_stok['nama']) ?> (Stok: <span style="color: #FF6347;"><?= htmlspecialchars($row_stok['stok']) ?></span>)</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow" style="background-color: #F0F4F7; color: #343A40;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: #343A40;">Grafik Penjualan Bulanan</h5>
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="text-center py-3 mt-auto" style="background-color: #E0F2F7; color: #343A40; position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" style="color: #343A40;">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    // Grafik Penjualan Bulanan
    var bulan = <?php echo json_encode($bulan); ?>;
    var total_penjualan = <?php echo json_encode($total_penjualan); ?>;
    var ctx = document.getElementById('salesChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: bulan.map(function(m){
                const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
                  "Juli", "Agustus", "September", "Oktober", "November", "Desember"
                ];
                return monthNames[m - 1];
            }),
            datasets: [{
                label: 'Total Penjualan',
                data: total_penjualan,
                backgroundColor: '#A7D9ED', /* Grafik Penjualan: Light Cerulean */
                borderColor: '#A7D9ED', /* Sama dengan background untuk solid color */
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index, values) {
                            return 'Rp. ' + value.toLocaleString();
                        },
                        color: '#343A40' /* Dark Grayish Black for Y-axis ticks */
                    },
                    grid: {
                        color: 'rgba(52, 58, 64, 0.1)' /* Light Dark Grayish Black for grid lines */
                    }
                },
                x: {
                    ticks: {
                        color: '#343A40' /* Dark Grayish Black for X-axis ticks */
                    },
                    grid: {
                        color: 'rgba(52, 58, 64, 0.1)' /* Light Dark Grayish Black for grid lines */
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#343A40' /* Dark Grayish Black for legend text */
                    }
                }
            }
        }
    });
</script>
</body>
</html>